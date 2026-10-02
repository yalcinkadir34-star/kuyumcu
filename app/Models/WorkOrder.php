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
 * Atölyeye giriş fişi: müşterinin getirdiği ürün.
 *
 * purity: giriş milyemi, işçilik dahil olarak doğrudan girilir (ör. 0,595).
 * Has = gram × milyem, müşterinin carisine ALACAK yazılır (müşteriye has borçlanırız).
 *
 * Çıkışlar bir girişe bağlı değildir, doğrudan müşteriye yapılır (bkz. WorkOrderDelivery).
 * Ramat müşteri bazında hesaplanır: tüm girişler − tüm çıkışlar (bkz. WorkshopTotals).
 */
#[Fillable(['account_id', 'product', 'received_at', 'gross_in', 'purity', 'notes'])]
class WorkOrder extends Model
{
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'gross_in' => 'decimal:3',
            'purity' => 'decimal:4',
            'has_in' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $order) {
            $order->number ??= static::nextNumber();
        });

        static::saving(function (WorkOrder $order) {
            $order->has_in = Amount::fromMilli(Workshop::hasMilli(Amount::toMilli($order->gross_in), $order->purity));
        });

        // Fiş silinince cariye işlenmiş giriş kaydı da silinir
        static::deleted(function (WorkOrder $order) {
            Transaction::whereKey($order->in_transaction_id)->delete();
        });
    }

    /** Sıradaki fiş numarası: A00001, A00002, ... */
    public static function nextNumber(): string
    {
        $last = static::query()->where('number', 'like', 'A%')->orderByDesc('number')->value('number');
        $number = $last ? (int) substr($last, 1) + 1 : 1;

        return 'A'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Fişi kaydeder ve giriş has'ını müşterinin carisine alacak olarak işler. */
    public function saveWithTransactions(?User $user = null): void
    {
        DB::transaction(function () use ($user) {
            $this->save();

            $grams = Amount::format($this->gross_in, new Currency(['symbol' => 'gr', 'decimals' => 3]));

            $this->in_transaction_id = LinkedTransaction::sync(
                $this->in_transaction_id,
                TransactionType::CariAlacak,
                Amount::toMilli($this->has_in),
                Currency::firstWhere('code', 'HAS'),
                $this->received_at,
                $this->account_id,
                $this->number,
                "Atölye girişi: {$this->product} ({$grams} × ".Workshop::formatPurity($this->purity).')',
                $user?->id ?? $this->created_by,
            );
            $this->saveQuietly();
        });
    }
}
