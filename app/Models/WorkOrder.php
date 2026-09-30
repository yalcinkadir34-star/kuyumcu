<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Support\Amount;
use App\Support\Workshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['account_id', 'product', 'received_at', 'gross_in', 'purity', 'notes'])]
class WorkOrder extends Model
{
    public const STATUS_ATOLYEDE = 'atolyede';

    public const STATUS_TESLIM = 'teslim_edildi';

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'delivered_at' => 'date',
            'gross_in' => 'decimal:3',
            'purity' => 'decimal:4',
            'has_in' => 'decimal:3',
            'gross_out' => 'decimal:3',
            'has_out' => 'decimal:3',
            'fire_gram' => 'decimal:3',
            'fire_has' => 'decimal:3',
            'labor_rate' => 'decimal:3',
            'labor_total' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $order) {
            $order->number ??= static::nextNumber();
        });

        // Has ve fire değerleri her kayıtta gram ve milyemden yeniden hesaplanır.
        static::saving(function (WorkOrder $order) {
            $order->has_in = Amount::fromMilli(Workshop::hasMilli(Amount::toMilli($order->gross_in), $order->purity));

            if ($order->gross_out !== null) {
                $fire = Amount::toMilli($order->gross_in) - Amount::toMilli($order->gross_out);
                $order->has_out = Amount::fromMilli(Workshop::hasMilli(Amount::toMilli($order->gross_out), $order->purity));
                $order->fire_gram = Amount::fromMilli($fire);
                $order->fire_has = Amount::fromMilli(Workshop::hasMilli($fire, $order->purity));
            }
        });
    }

    /** Sıradaki fiş numarası: A00001, A00002, ... */
    public static function nextNumber(): string
    {
        $last = static::query()->where('number', 'like', 'A%')->orderByDesc('number')->value('number');
        $number = $last ? (int) substr($last, 1) + 1 : 1;

        return 'A'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function laborCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'labor_currency_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeInWorkshop(Builder $query): void
    {
        $query->where('status', self::STATUS_ATOLYEDE);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_TESLIM;
    }

    public function statusLabel(): string
    {
        return $this->isDelivered() ? 'Teslim edildi' : 'Atölyede';
    }

    public function fireRate(): float
    {
        return Workshop::fireRate(Amount::toMilli($this->fire_gram), Amount::toMilli($this->gross_in));
    }

    /**
     * Ürünü teslim eder: çıkış gramını ve işçiliği kaydeder, işçiliği carinin
     * hesabına borç olarak işler. Tekrar çağrılırsa (düzeltme) cari kaydı güncellenir.
     *
     * @param  array{delivered_at:string, gross_out:string, labor_basis:string, labor_rate:string, labor_currency_id:int}  $data
     */
    public function deliver(array $data, User $user): void
    {
        DB::transaction(function () use ($data, $user) {
            $currency = Currency::findOrFail($data['labor_currency_id']);

            $this->status = self::STATUS_TESLIM;
            $this->delivered_at = $data['delivered_at'];
            $this->gross_out = $data['gross_out'];
            $this->labor_basis = $data['labor_basis'];
            $this->labor_rate = $data['labor_rate'];
            $this->labor_currency_id = $currency->id;

            $laborMilli = Workshop::laborMilli(
                $data['labor_basis'],
                Amount::toMilli($data['labor_rate']),
                Amount::toMilli($data['gross_out']),
                $currency->decimals,
            );
            $this->labor_total = Amount::fromMilli($laborMilli);

            $this->syncLaborTransaction($laborMilli, $currency, $user);
            $this->save();
        });
    }

    /** Teslimi geri alır: ürün tekrar atölyede görünür, işçilik cari kaydı silinir. */
    public function undeliver(): void
    {
        DB::transaction(function () {
            $transaction = $this->transaction;

            $this->forceFill([
                'status' => self::STATUS_ATOLYEDE,
                'delivered_at' => null,
                'gross_out' => null,
                'has_out' => null,
                'fire_gram' => null,
                'fire_has' => null,
                'labor_basis' => null,
                'labor_rate' => null,
                'labor_total' => null,
                'labor_currency_id' => null,
                'transaction_id' => null,
            ])->save();

            $transaction?->delete();
        });
    }

    private function syncLaborTransaction(int $laborMilli, Currency $currency, User $user): void
    {
        $transaction = $this->transaction;

        if ($laborMilli <= 0) {
            $transaction?->delete();
            $this->transaction_id = null;

            return;
        }

        $transaction ??= new Transaction(['type' => TransactionType::CariBorc]);
        $transaction->fill([
            'type' => TransactionType::CariBorc,
            'date' => $this->delivered_at,
            'account_id' => $this->account_id,
            'currency_id' => $currency->id,
            'amount' => Amount::fromMilli($laborMilli),
            'document_no' => $this->number,
            'description' => 'Atölye işçiliği: '.$this->product,
        ]);
        $transaction->created_by ??= $user->id;
        $transaction->updated_by = $user->id;
        $transaction->save();

        $this->transaction_id = $transaction->id;
    }
}
