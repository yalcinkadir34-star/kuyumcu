<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atölye çıkışının bir satırı: ürün, gram, çıkış milyemi (işçilik dahil), has.
 * Her satır müşterinin carisine ayrı bir borç kaydı olarak işlenir.
 */
#[Fillable(['product', 'gross_out', 'purity_out'])]
class WorkOrderDeliveryLine extends Model
{
    protected function casts(): array
    {
        return [
            'gross_out' => 'decimal:3',
            'purity_out' => 'decimal:4',
            'has_out' => 'decimal:3',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WorkOrderDelivery::class, 'work_order_delivery_id');
    }
}
