<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Support\Amount;
use App\Support\Workshop;
use App\Support\WorkshopTotals;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Atölyeden müşteriye çıkış: müşteri, gram, çıkış milyemi. Bir giriş fişine bağlı değildir. */
class WorkshopDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', Rule::exists('accounts', 'id')],
            'product' => ['nullable', 'string', 'max:255'],
            'delivered_at' => ['required', 'date'],
            'gross_out' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $parsed = Amount::parse((string) $value);

                if ($parsed === null || Amount::toMilli($parsed) <= 0) {
                    $fail('Çıkış gramı geçersiz. Örnek: 6,97 veya 160');
                } elseif (Amount::decimalsOf($parsed) > 3) {
                    $fail('Gram en fazla 3 ondalık basamak olabilir.');
                } elseif ($this->filled('account_id')) {
                    $ramat = WorkshopTotals::forAccount((int) $this->input('account_id'))['ramat_gram'];

                    if (Amount::toMilli($parsed) > $ramat) {
                        $kalan = Amount::formatMilli(max($ramat, 0), new Currency(['symbol' => 'gr', 'decimals' => 3]));
                        $fail("Çıkış, bu müşterinin atölyede kalan ürününden ({$kalan}) fazla olamaz.");
                    }
                }
            }],
            'purity_out' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (Workshop::parsePurity((string) $value) === null) {
                    $fail('Çıkış milyemi geçersiz. Örnek: 0,625 veya 625');
                }
            }],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_id' => 'müşteri',
            'product' => 'ürün',
            'delivered_at' => 'çıkış tarihi',
            'gross_out' => 'çıkış gramı',
            'purity_out' => 'çıkış milyemi',
            'notes' => 'not',
        ];
    }

    public function deliveryData(): array
    {
        return [
            ...$this->validated(),
            'gross_out' => Amount::parse($this->input('gross_out')),
            'purity_out' => Workshop::parsePurity($this->input('purity_out')),
        ];
    }
}
