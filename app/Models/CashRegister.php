<?php

namespace App\Models;

use App\Enums\CashRegisterType;
use App\Support\Balances;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'notes', 'is_active'])]
class CashRegister extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CashRegisterType::class,
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('name');
    }

    /** @return array<int, int> currency_id => milli */
    public function balances(): array
    {
        return Balances::forCashRegisters([$this->id])[$this->id] ?? [];
    }
}
