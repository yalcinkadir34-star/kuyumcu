<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Models\WorkOrder;
use App\Support\Amount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Atölye çıkışı: tartıdaki net gram ve işçilik. */
class DeliverWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $order = $this->route('workOrder');

        return [
            'delivered_at' => ['required', 'date', 'after_or_equal:'.$order->received_at->toDateString()],
            'gross_out' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $parsed = Amount::parse((string) $value);

                if ($parsed === null || Amount::toMilli($parsed) <= 0) {
                    $fail('Çıkış gramı geçersiz. Örnek: 160 veya 160,250');
                } elseif (Amount::decimalsOf($parsed) > 3) {
                    $fail('Gram en fazla 3 ondalık basamak olabilir.');
                }
            }],
            'fire_bearer' => ['required', Rule::in([WorkOrder::FIRE_FIRMA, WorkOrder::FIRE_ATOLYE])],
            'labor_basis' => ['required', Rule::in(['gram', 'toplam'])],
            'labor_currency_id' => ['required', Rule::exists('currencies', 'id')->where('is_active', true)],
            'labor_rate' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $parsed = Amount::parse((string) $value);

                if ($parsed === null) {
                    $fail('İşçilik tutarı geçersiz. İşçilik yoksa 0 yazın.');

                    return;
                }

                // Toplam tutar girildiyse birimin ondalık sınırına uymalı
                $currency = Currency::find($this->input('labor_currency_id'));
                if ($this->input('labor_basis') === 'toplam' && $currency && Amount::decimalsOf($parsed) > $currency->decimals) {
                    $fail("{$currency->name} için en fazla {$currency->decimals} ondalık basamak girilebilir.");
                }
            }],
        ];
    }

    public function attributes(): array
    {
        return [
            'delivered_at' => 'çıkış tarihi',
            'gross_out' => 'çıkış gramı',
            'fire_bearer' => 'fireyi üstlenen',
            'labor_basis' => 'işçilik tipi',
            'labor_currency_id' => 'işçilik birimi',
            'labor_rate' => 'işçilik',
        ];
    }

    public function messages(): array
    {
        return [
            'delivered_at.after_or_equal' => 'Çıkış tarihi giriş tarihinden önce olamaz.',
        ];
    }

    public function deliveryData(): array
    {
        return [
            ...$this->validated(),
            'gross_out' => Amount::parse($this->input('gross_out')),
            'labor_rate' => Amount::parse($this->input('labor_rate')),
        ];
    }
}
