<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Support\Balances;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'name', 'contact_person', 'phone', 'email', 'tax_office', 'tax_number', 'address', 'notes', 'is_active'])]
class Account extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Account $account) {
            $account->code ??= static::nextCode();
        });
    }

    /** Sıradaki cari kodu: C00001, C00002, ... */
    public static function nextCode(): string
    {
        $last = static::query()->where('code', 'like', 'C%')->orderByDesc('code')->value('code');
        $number = $last ? (int) substr($last, 1) + 1 : 1;

        return 'C'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('contact_person', 'like', "%{$term}%");
        });
    }

    /**
     * Birim bazında bakiye (binde bir birimli tam sayı).
     * Pozitif: cari bize borçlu. Negatif: biz cariye borçluyuz.
     *
     * @return array<int, int> currency_id => milli
     */
    public function balances(): array
    {
        return Balances::forAccounts([$this->id])[$this->id] ?? [];
    }
}
