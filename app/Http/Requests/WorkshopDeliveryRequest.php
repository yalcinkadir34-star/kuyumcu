<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Models\WorkOrderDelivery;
use App\Support\Amount;
use App\Support\Workshop;
use App\Support\WorkshopTotals;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Atölyeden müşteriye çıkış: müşteri, tür, tarih ve bir veya birden fazla satır
 * (her satırda ürün, gram, çıkış milyemi). Giriş fişine bağlı değildir.
 */
class WorkshopDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Tamamen boş bırakılan satırlar (ör. fazladan eklenmiş) yok sayılır. */
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line) => is_array($line) && collect($line)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', Rule::exists('accounts', 'id')],
            'kind' => ['required', Rule::in([WorkOrderDelivery::KIND_ATOLYE, WorkOrderDelivery::KIND_SATIS])],
            'delivered_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product' => ['nullable', 'string', 'max:255'],
            'lines.*.gross_out' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $parsed = Amount::parse((string) $value);

                if ($parsed === null || Amount::toMilli($parsed) <= 0) {
                    $fail('Gram geçersiz. Örnek: 6,97 veya 160');
                } elseif (Amount::decimalsOf($parsed) > 3) {
                    $fail('Gram en fazla 3 ondalık basamak olabilir.');
                }
            }],
            'lines.*.purity_out' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (Workshop::parsePurity((string) $value) === null) {
                    $fail('Milyem geçersiz. Örnek: 0,625 veya 625');
                }
            }],
        ];
    }

    /** Atölyedeki üründen çıkışta satırların toplam gramı müşterinin kalanını aşamaz (satışta sınır yok). */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('kind') !== WorkOrderDelivery::KIND_ATOLYE) {
                return;
            }

            $total = collect($this->input('lines'))->sum(fn ($line) => Amount::toMilli(Amount::parse($line['gross_out'])));
            $ramat = WorkshopTotals::forAccount((int) $this->input('account_id'))['ramat_gram'];

            if ($total > $ramat) {
                $kalan = Amount::formatMilli(max($ramat, 0), new Currency(['symbol' => 'gr', 'decimals' => 3]));
                $validator->errors()->add('lines', "Çıkışın toplam gramı, bu müşterinin atölyede kalan ürününden ({$kalan}) fazla olamaz. Kendi ürününüzse türü \"Kendi ürünüm (satış)\" seçin.");
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'account_id' => 'müşteri',
            'kind' => 'çıkış türü',
            'delivered_at' => 'çıkış tarihi',
            'notes' => 'not',
            'lines' => 'satırlar',
            'lines.*.product' => 'ürün',
            'lines.*.gross_out' => 'gram',
            'lines.*.purity_out' => 'milyem',
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'En az bir satır girin (gram ve milyem).',
            'lines.min' => 'En az bir satır girin (gram ve milyem).',
        ];
    }

    public function deliveryData(): array
    {
        return collect($this->validated())->only(['account_id', 'kind', 'delivered_at', 'notes'])->all();
    }

    /** @return array<int, array{product:?string, gross_out:string, purity_out:string}> */
    public function linesData(): array
    {
        return collect($this->validated('lines'))->map(fn ($line) => [
            'product' => filled($line['product'] ?? null) ? trim($line['product']) : null,
            'gross_out' => Amount::parse($line['gross_out']),
            'purity_out' => Workshop::parsePurity($line['purity_out']),
        ])->all();
    }
}
