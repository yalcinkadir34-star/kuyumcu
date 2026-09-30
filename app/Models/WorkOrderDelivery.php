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
 * Cariye etkisi: çıkan gramın has karşılığı CARİ BORÇ (firmaya has borcumuz düşer),
 * işçilik CARİ BORÇ (TL / döviz / has).
 */
#[Fillable(['delivered_at', 'gross_out', 'labor_basis', 'labor_rate', 'labor_currency_id', 'notes'])]
class WorkOrderDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'delivered_at' => 'date',
            'gross_out' => 'decimal:3',
            'has_out' => 'decimal:3',
            'labor_rate' => 'decimal:3',
            'labor_total' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (WorkOrderDelivery $delivery) {
            Transaction::whereIn('id', array_filter([$delivery->out_transaction_id, $delivery->labor_transaction_id]))->delete();
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function laborCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'labor_currency_id');
    }

    /** Has ve işçiliği hesaplar, cari kayıtlarını eşitler ve kaydeder. */
    public function saveWithTransactions(?int $userId = null): void
    {
        $order = $this->workOrder;
        $currency = Currency::findOrFail($this->labor_currency_id);
        $grossOut = Amount::toMilli($this->gross_out);
        $hasOut = Workshop::hasMilli($grossOut, $order->purity);
        $labor = Workshop::laborMilli($this->labor_basis, Amount::toMilli($this->labor_rate), $grossOut, $currency->decimals);

        $this->has_out = Amount::fromMilli($hasOut);
        $this->labor_total = Amount::fromMilli($labor);
        $this->created_by ??= $userId;

        $grams = Amount::format($this->gross_out, new Currency(['symbol' => 'gr', 'decimals' => 3]));
        $purity = Workshop::formatPurity($order->purity);

        $this->out_transaction_id = LinkedTransaction::sync(
            $this->out_transaction_id,
            TransactionType::CariBorc,
            $hasOut,
            Currency::firstWhere('code', 'HAS'),
            $this->delivered_at,
            $order->account_id,
            $order->number,
            "Atölye çıkışı: {$order->product} ({$grams} × {$purity})",
            $userId,
        );

        $this->labor_transaction_id = LinkedTransaction::sync(
            $this->labor_transaction_id,
            TransactionType::CariBorc,
            $labor,
            $currency,
            $this->delivered_at,
            $order->account_id,
            $order->number,
            "Atölye işçiliği: {$order->product} ({$grams})",
            $userId,
        );

        $this->save();
    }
}
