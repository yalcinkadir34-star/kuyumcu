<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    public const TRIGGER_MANUEL = 'manuel';

    public const TRIGGER_OTOMATIK = 'otomatik';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'basarili';
    }

    /** Okunabilir boyut: "3,9 KB", "1,2 MB" */
    public function sizeLabel(): string
    {
        if (! $this->size) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, $i === 0 ? 0 : 1, ',', '.').' '.$units[$i];
    }
}
