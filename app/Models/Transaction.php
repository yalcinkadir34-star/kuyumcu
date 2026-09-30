<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['date', 'type', 'account_id', 'cash_register_id', 'currency_id', 'amount', 'document_no', 'description'])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TransactionType::class,
            'amount' => 'decimal:3',
            'account_direction' => 'integer',
            'cash_direction' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Yönler her zaman hareket türünden hesaplanır; elle girilmez.
        static::saving(function (Transaction $transaction) {
            $transaction->account_direction = $transaction->type->accountDirection();
            $transaction->cash_direction = $transaction->type->cashDirection();

            if (! $transaction->type->requiresAccount()) {
                $transaction->account_id = null;
            }

            if (! $transaction->type->requiresCashRegister()) {
                $transaction->cash_register_id = null;
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
