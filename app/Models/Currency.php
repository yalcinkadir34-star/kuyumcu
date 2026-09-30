<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'symbol', 'decimals', 'sort', 'is_active'])]
class Currency extends Model
{
    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }

    /** Aktif birimler, id'ye göre anahtarlı. */
    public static function activeList(): Collection
    {
        return static::query()->active()->get()->keyBy('id');
    }
}
