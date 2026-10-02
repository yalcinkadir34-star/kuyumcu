<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Support\Amount;
use App\Support\LinkedTransaction;
use App\Support\Workshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Atölye iş emri (fason işçilik fişi).
 *
 * purity: giriş milyemi, işçilik dahil olarak doğrudan girilir (ör. 0,595).
 *
 * Giriş: gram × giriş milyemi has, firmanın carisine ALACAK yazılır
 *        (ör. 26,25 × 0,595 = 15,618 → firmaya has borçlanırız).
 * Çıkışlar (bir veya birden fazla): bkz. WorkOrderDelivery.
 * Giriş − çıkışlar = atölyede kalan (gram). Fire cariye işlenmez; fiş kapatılınca
 * kalan miktar fire olarak raporlanır.
 */
#[Fillable(['account_id', 'product', 'received_at', 'gross_in', 'purity', 'notes'])]
class WorkOrder extends Model
{
    public const STATUS_ATOLYEDE = 'atolyede';

    public const STATUS_TAMAMLANDI = 'tamamlandi';

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'closed_at' => 'date',
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

        // Fiş silinince giriş kaydı ve çıkışlar (kendi cari kayıtlarıyla) silinir
        static::deleting(function (WorkOrder $order) {
            $order->deliveries->each->delete();
        });

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

    /** Bu cari hareketi bir atölye fişinden otomatik oluşturulduysa o fiş. */
    public static function linkedTo(Transaction $transaction): ?self
    {
        $delivery = WorkOrderDelivery::firstWhere('out_transaction_id', $transaction->id);

        return $delivery?->workOrder ?? static::firstWhere('in_transaction_id', $transaction->id);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WorkOrderDelivery::class)->orderBy('delivered_at')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeInWorkshop(Builder $query): void
    {
        $query->where('status', self::STATUS_ATOLYEDE);
    }

    /** Liste sorgularında çıkış toplamlarını tek sorguda getirir. */
    public function scopeWithDeliveryTotals(Builder $query): void
    {
        $query->withSum('deliveries as delivered_gram', 'gross_out')
            ->withSum('deliveries as delivered_has', 'has_out')
            ->withCount('deliveries');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_TAMAMLANDI;
    }

    public function statusLabel(): string
    {
        return $this->isClosed() ? 'Tamamlandı' : 'Atölyede';
    }

    /** Toplam çıkan gram (listelerde withDeliveryTotals ile tek sorguda gelir). */
    public function deliveredMilli(): int
    {
        if (array_key_exists('delivered_gram', $this->attributes)) {
            return Amount::toMilli($this->attributes['delivered_gram'] ?? 0);
        }

        return $this->deliveries->sum(fn (WorkOrderDelivery $d) => Amount::toMilli($d->gross_out));
    }

    /** Atölyede kalan gram (giriş − çıkışlar). Fiş kapandıysa bu miktar firedir. */
    public function remainingMilli(): int
    {
        return Amount::toMilli($this->gross_in) - $this->deliveredMilli();
    }

    /** Atölyede kalan gramın has karşılığı (giriş milyemiyle). */
    public function remainingHasMilli(): int
    {
        return Workshop::hasMilli($this->remainingMilli(), $this->purity);
    }

    /** Kalanın girişe oranı (%) — fiş kapandıysa fire oranı. */
    public function remainingRate(): float
    {
        return Workshop::fireRate($this->remainingMilli(), Amount::toMilli($this->gross_in));
    }

    /** Giriş bilgilerini kaydeder, giriş ve çıkış cari kayıtlarını günceller. */
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

            // Milyem veya firma değişmiş olabilir: çıkışları yeniden hesapla
            foreach ($this->deliveries()->get() as $delivery) {
                $delivery->setRelation('workOrder', $this);
                $delivery->saveWithTransactions($user?->id ?? $delivery->created_by);
            }
        });
    }

    /** Yeni çıkış (teslim) ekler ve cariye işler. */
    public function addDelivery(array $data, User $user): WorkOrderDelivery
    {
        return DB::transaction(function () use ($data, $user) {
            $delivery = $this->deliveries()->make($data);
            $delivery->setRelation('workOrder', $this);
            $delivery->saveWithTransactions($user->id);

            return $delivery;
        });
    }

    /** Fişi kapatır: atölyede kalan miktar fire sayılır (cariye işlenmez). */
    public function close(): void
    {
        $this->forceFill(['status' => self::STATUS_TAMAMLANDI, 'closed_at' => now()->toDateString()])->save();
    }

    public function reopen(): void
    {
        $this->forceFill(['status' => self::STATUS_ATOLYEDE, 'closed_at' => null])->save();
    }
}
