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

/**
 * Atölye iş emri (fason işçilik fişi).
 *
 * Cariye etkisi (hepsi otomatik, bağlı hareketlerle):
 *   Giriş   → has karşılığı kadar CARİ ALACAK (biz firmaya has borçlanırız)
 *   Teslim  → geri verilen has kadar CARİ BORÇ (borcumuz düşer)
 *   Fire    → firma üstlenirse fire has'ı kadar CARİ BORÇ; atölye üstlenirse firmaya borç olarak kalır
 *   İşçilik → işçilik tutarı kadar CARİ BORÇ (TL / döviz / has)
 */
#[Fillable(['account_id', 'product', 'received_at', 'gross_in', 'purity', 'notes'])]
class WorkOrder extends Model
{
    public const STATUS_ATOLYEDE = 'atolyede';

    public const STATUS_TESLIM = 'teslim_edildi';

    public const FIRE_FIRMA = 'firma';

    public const FIRE_ATOLYE = 'atolye';

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

        static::saving(fn (WorkOrder $order) => $order->recalculate());

        // Fiş silinince giriş kaydı da silinir (teslim edilmiş fiş zaten silinemez)
        static::deleted(function (WorkOrder $order) {
            Transaction::whereIn('id', array_filter([
                $order->in_transaction_id, $order->out_transaction_id, $order->fire_transaction_id, $order->transaction_id,
            ]))->delete();
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
        return static::query()
            ->where('transaction_id', $transaction->id)
            ->orWhere('in_transaction_id', $transaction->id)
            ->orWhere('out_transaction_id', $transaction->id)
            ->orWhere('fire_transaction_id', $transaction->id)
            ->first();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function laborCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'labor_currency_id');
    }

    /** İşçilik cari kaydı */
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

    /** Has ve fire değerlerini gram ve milyemden yeniden hesaplar. */
    public function recalculate(): void
    {
        $this->has_in = Amount::fromMilli(Workshop::hasMilli(Amount::toMilli($this->gross_in), $this->purity));

        if ($this->gross_out !== null) {
            $fire = Amount::toMilli($this->gross_in) - Amount::toMilli($this->gross_out);
            $this->has_out = Amount::fromMilli(Workshop::hasMilli(Amount::toMilli($this->gross_out), $this->purity));
            $this->fire_gram = Amount::fromMilli($fire);
            $this->fire_has = Amount::fromMilli(Workshop::hasMilli($fire, $this->purity));
        }
    }

    /** Giriş bilgilerini kaydeder ve cari has kayıtlarını günceller. */
    public function saveWithTransactions(?User $user = null): void
    {
        DB::transaction(function () use ($user) {
            $this->save();
            $this->syncGoldTransactions($user);

            // Firma değiştiyse işçilik kaydı da yeni firmaya taşınır
            if ($this->transaction && $this->transaction->account_id !== $this->account_id) {
                $this->transaction->update(['account_id' => $this->account_id]);
            }

            $this->saveQuietly();
        });
    }

    /**
     * Ürünü teslim eder: çıkış gramı, fire ve işçiliği kaydeder, hepsini carinin
     * hesabına işler. Tekrar çağrılırsa (düzeltme) cari kayıtları güncellenir.
     *
     * @param  array{delivered_at:string, gross_out:string, fire_bearer:string, labor_basis:string, labor_rate:string, labor_currency_id:int}  $data
     */
    public function deliver(array $data, User $user): void
    {
        DB::transaction(function () use ($data, $user) {
            $currency = Currency::findOrFail($data['labor_currency_id']);

            $this->status = self::STATUS_TESLIM;
            $this->delivered_at = $data['delivered_at'];
            $this->gross_out = $data['gross_out'];
            $this->fire_bearer = $data['fire_bearer'];
            $this->labor_basis = $data['labor_basis'];
            $this->labor_rate = $data['labor_rate'];
            $this->labor_currency_id = $currency->id;
            $this->recalculate();

            $laborMilli = Workshop::laborMilli(
                $data['labor_basis'],
                Amount::toMilli($data['labor_rate']),
                Amount::toMilli($data['gross_out']),
                $currency->decimals,
            );
            $this->labor_total = Amount::fromMilli($laborMilli);

            $this->syncLaborTransaction($laborMilli, $currency, $user);
            $this->syncGoldTransactions($user);
            $this->save();
        });
    }

    /** Teslimi geri alır: ürün tekrar atölyede görünür; teslim, fire ve işçilik cari kayıtları silinir. */
    public function undeliver(): void
    {
        DB::transaction(function () {
            $ids = array_filter([$this->out_transaction_id, $this->fire_transaction_id, $this->transaction_id]);

            $this->forceFill([
                'status' => self::STATUS_ATOLYEDE,
                'delivered_at' => null,
                'gross_out' => null,
                'has_out' => null,
                'fire_gram' => null,
                'fire_has' => null,
                'fire_bearer' => null,
                'labor_basis' => null,
                'labor_rate' => null,
                'labor_total' => null,
                'labor_currency_id' => null,
                'transaction_id' => null,
                'out_transaction_id' => null,
                'fire_transaction_id' => null,
            ])->save();

            Transaction::whereIn('id', $ids)->delete();
        });
    }

    /**
     * Cari has kayıtlarını fişin güncel durumuyla eşitler (giriş, teslim, fire).
     * Kaydetme işlemi çağırana aittir.
     */
    public function syncGoldTransactions(?User $user = null): void
    {
        $has = Currency::firstWhere('code', 'HAS');
        $grams = fn ($value) => Amount::format($value, new Currency(['symbol' => 'gr', 'decimals' => 3]));
        $purity = Workshop::formatPurity($this->purity);

        $this->in_transaction_id = $this->syncTransaction(
            $this->in_transaction_id,
            TransactionType::CariAlacak,
            Amount::toMilli($this->has_in),
            $has,
            $this->received_at,
            "Atölye girişi: {$this->product} ({$grams($this->gross_in)} × {$purity})",
            $user,
        );

        $delivered = $this->isDelivered();

        $this->out_transaction_id = $this->syncTransaction(
            $this->out_transaction_id,
            TransactionType::CariBorc,
            $delivered ? Amount::toMilli($this->has_out) : 0,
            $has,
            $this->delivered_at,
            "Atölye teslimi: {$this->product} ({$grams($this->gross_out)} × {$purity})",
            $user,
        );

        $this->fire_transaction_id = $this->syncTransaction(
            $this->fire_transaction_id,
            TransactionType::CariBorc,
            $delivered && $this->fire_bearer === self::FIRE_FIRMA ? Amount::toMilli($this->fire_has) : 0,
            $has,
            $this->delivered_at,
            "Atölye firesi: {$this->product} ({$grams($this->fire_gram)} × {$purity})",
            $user,
        );
    }

    private function syncLaborTransaction(int $laborMilli, Currency $currency, User $user): void
    {
        $this->transaction_id = $this->syncTransaction(
            $this->transaction_id,
            TransactionType::CariBorc,
            $laborMilli,
            $currency,
            $this->delivered_at,
            'Atölye işçiliği: '.$this->product,
            $user,
        );
    }

    /**
     * Bağlı cari hareketini oluşturur, günceller ya da (tutar sıfırsa) siler.
     *
     * @return int|null hareketin id'si
     */
    private function syncTransaction(?int $id, TransactionType $type, int $milli, Currency $currency, $date, string $description, ?User $user): ?int
    {
        $transaction = $id ? Transaction::find($id) : null;

        if ($milli <= 0) {
            $transaction?->delete();

            return null;
        }

        $transaction ??= new Transaction;
        $transaction->fill([
            'type' => $type,
            'date' => $date,
            'account_id' => $this->account_id,
            'currency_id' => $currency->id,
            'amount' => Amount::fromMilli($milli),
            'document_no' => $this->number,
            'description' => $description,
        ]);
        $transaction->created_by ??= $user?->id ?? $this->created_by;
        $transaction->updated_by = $user?->id ?? $this->created_by;
        $transaction->save();

        return $transaction->id;
    }
}
