<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Support\Amount;
use App\Support\LinkedTransaction;
use App\Support\Workshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atölye fişinden yapılan bir çıkış (teslim). Bir fişin birden fazla çıkışı olabilir.
 *
 * Çıkış has = gram × (ayar milyemi + çıkış işçiliği milyemi), ör. 6,97 × (0,585 + 0,040) = 4,356.
 * Bu has firmanın carisine BORÇ yazılır (firmaya has borcumuz düşer). İşçilik bu hesabın içindedir.
 */
#[Fillable(['delivered_at', 'gross_out', 'labor_purity', 'notes'])]
class WorkOrderDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'delivered_at' => 'date',
            'gross_out' => 'decimal:3',
            'labor_purity' => 'decimal:4',
            'has_out' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (WorkOrderDelivery $delivery) {
            Transaction::whereKey($delivery->out_transaction_id)->delete();
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** Çıkış hesap milyemi: ayar + çıkış işçiliği (ör. 0,585 + 0,040 = 0,625). */
    public function outPurity(): string
    {
        return Workshop::addPurity($this->workOrder->purity, $this->labor_purity);
    }

    /** Has'ı hesaplar, cari kaydını eşitler ve kaydeder. */
    public function saveWithTransactions(?int $userId = null): void
    {
        $order = $this->workOrder;
        $this->labor_purity ??= '0.0000';
        $hasOut = Workshop::hasMilli(Amount::toMilli($this->gross_out), $this->outPurity());

        $this->has_out = Amount::fromMilli($hasOut);
        $this->created_by ??= $userId;

        $grams = Amount::format($this->gross_out, new Currency(['symbol' => 'gr', 'decimals' => 3]));

        $this->out_transaction_id = LinkedTransaction::sync(
            $this->out_transaction_id,
            TransactionType::CariBorc,
            $hasOut,
            Currency::firstWhere('code', 'HAS'),
            $this->delivered_at,
            $order->account_id,
            $order->number,
            "Atölye çıkışı: {$order->product} ({$grams} × ".Workshop::formatPurity($this->outPurity()).')',
            $userId,
        );

        $this->save();
    }
}
