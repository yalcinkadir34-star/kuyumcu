<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Support\Amount;
use App\Support\LinkedTransaction;
use App\Support\Workshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Atölyeden müşteriye çıkış (teslim). Bir giriş fişine bağlı değildir.
 * Tek çıkışta birden fazla satır olabilir (farklı ürün, milyem, işçilik): bkz. WorkOrderDeliveryLine.
 * Her satırın has'ı = gram × çıkış milyemi, müşterinin carisine ayrı BORÇ kaydı olarak yazılır.
 *
 * kind: KIND_ATOLYE = müşterinin atölyedeki ürününden (ramattan düşer),
 *       KIND_SATIS  = atölyenin kendi ürünü, ör. müşterinin verdiği has karşılığı (ramatı etkilemez).
 */
#[Fillable(['account_id', 'kind', 'delivered_at', 'notes'])]
class WorkOrderDelivery extends Model
{
    public const KIND_ATOLYE = 'atolye';

    public const KIND_SATIS = 'satis';

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrderDelivery $delivery) {
            $delivery->number ??= static::nextNumber();
            $delivery->kind ??= self::KIND_ATOLYE;
        });

        // Çıkış silinince satırların cari kayıtları da silinir (satırlar veritabanında zincirleme silinir)
        static::deleting(function (WorkOrderDelivery $delivery) {
            Transaction::whereIn('id', $delivery->lines()->pluck('out_transaction_id')->filter())->delete();
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

    public function lines(): HasMany
    {
        return $this->hasMany(WorkOrderDeliveryLine::class)->orderBy('id');
    }

    public function isSale(): bool
    {
        return $this->kind === self::KIND_SATIS;
    }

    public function kindLabel(): string
    {
        return $this->isSale() ? 'Satış' : 'Atölye';
    }

    public function grossOutMilli(): int
    {
        return $this->lines->sum(fn (WorkOrderDeliveryLine $l) => Amount::toMilli($l->gross_out));
    }

    public function hasOutMilli(): int
    {
        return $this->lines->sum(fn (WorkOrderDeliveryLine $l) => Amount::toMilli($l->has_out));
    }

    /** Satırlardaki ürün adları (listeler için): "14 ayar zincir, 18 ayar yüzük" */
    public function productsLabel(): string
    {
        return $this->lines->pluck('product')->filter()->unique()->join(', ');
    }

    /**
     * Çıkışı satırlarıyla kaydeder; her satırın has'ını hesaplayıp müşterinin carisine borç yazar.
     *
     * @param  array<int, array{product:?string, gross_out:string, purity_out:string}>  $lines
     */
    public function saveWithLines(array $lines, ?int $userId = null): void
    {
        DB::transaction(function () use ($lines, $userId) {
            $this->created_by ??= $userId;
            $this->save(); // numara oluşsun

            $has = Currency::firstWhere('code', 'HAS');
            $gr = new Currency(['symbol' => 'gr', 'decimals' => 3]);

            foreach ($lines as $data) {
                $line = $this->lines()->make($data);
                $hasMilli = Workshop::hasMilli(Amount::toMilli($line->gross_out), $line->purity_out);
                $line->has_out = Amount::fromMilli($hasMilli);
                $line->save();

                $what = $line->product ? "{$line->product} " : '';

                $line->out_transaction_id = LinkedTransaction::sync(
                    null,
                    TransactionType::CariBorc,
                    $hasMilli,
                    $has,
                    $this->delivered_at,
                    $this->account_id,
                    $this->number,
                    ($this->isSale() ? 'Satış: ' : 'Atölye çıkışı: ')
                        ."{$what}(".Amount::format($line->gross_out, $gr).' × '.Workshop::formatPurity($line->purity_out).')',
                    $userId,
                );
                $line->saveQuietly();
            }

            $this->unsetRelation('lines');
        });
    }
}
