<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Support\Amount;
use App\Support\LinkedTransaction;
use App\Support\Workshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Atölyeden müşteriye çıkış (teslim). Bir giriş fişine bağlı değildir: müşteri ürünlerini getirir,
 * atölye başka bir zaman, istediği kadarını çıkar ("hangi fişten çıktığımın önemi yok").
 *
 * purity_out: çıkış milyemi, işçilik dahil (ör. 0,625). Has = gram × çıkış milyemi.
 * Bu has müşterinin carisine BORÇ yazılır (müşteriye has borcumuz düşer).
 */
#[Fillable(['account_id', 'product', 'delivered_at', 'gross_out', 'purity_out', 'notes'])]
class WorkOrderDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'gross_out' => 'decimal:3',
            'purity_out' => 'decimal:4',
            'has_out' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrderDelivery $delivery) {
            $delivery->number ??= static::nextNumber();
        });

        static::deleted(function (WorkOrderDelivery $delivery) {
            Transaction::whereKey($delivery->out_transaction_id)->delete();
        });
    }

    /** Sıradaki çıkış numarası: T00001, T00002, ... */
    public static function nextNumber(): string
    {
        $last = static::query()->where('number', 'like', 'T%')->orderByDesc('number')->value('number');
        $number = $last ? (int) substr($last, 1) + 1 : 1;

        return 'T'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Has'ı hesaplar, kaydeder ve müşterinin carisine borç olarak işler. */
    public function saveWithTransactions(?int $userId = null): void
    {
        DB::transaction(function () use ($userId) {
            $hasOut = Workshop::hasMilli(Amount::toMilli($this->gross_out), $this->purity_out);

            $this->has_out = Amount::fromMilli($hasOut);
            $this->created_by ??= $userId;
            $this->save(); // numara oluşsun

            $grams = Amount::format($this->gross_out, new Currency(['symbol' => 'gr', 'decimals' => 3]));
            $what = $this->product ? "{$this->product} " : '';

            $this->out_transaction_id = LinkedTransaction::sync(
                $this->out_transaction_id,
                TransactionType::CariBorc,
                $hasOut,
                Currency::firstWhere('code', 'HAS'),
                $this->delivered_at,
                $this->account_id,
                $this->number,
                "Atölye çıkışı: {$what}({$grams} × ".Workshop::formatPurity($this->purity_out).')',
                $userId,
            );

            $this->saveQuietly();
        });
    }
}
