<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Models\WorkOrder;
use App\Support\Amount;
use App\Support\Workshop;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Atölyeden çıkış: tartıdaki gram ve çıkış işçiliği (milyem). Bir fişin birden fazla çıkışı olabilir. */
class DeliverWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var WorkOrder $order */
        $order = $this->route('workOrder');

        return [
            'delivered_at' => ['required', 'date', 'after_or_equal:'.$order->received_at->format('Y-m-d H:i:s')],
            'gross_out' => ['required', function (string $attribute, mixed $value, Closure $fail) use ($order) {
                $parsed = Amount::parse((string) $value);

                if ($parsed === null || Amount::toMilli($parsed) <= 0) {
                    $fail('Çıkış gramı geçersiz. Örnek: 6,97 veya 160');
                } elseif (Amount::decimalsOf($parsed) > 3) {
                    $fail('Gram en fazla 3 ondalık basamak olabilir.');
                } elseif (Amount::toMilli($parsed) > $order->remainingMilli()) {
                    $kalan = Amount::formatMilli($order->remainingMilli(), new Currency(['symbol' => 'gr', 'decimals' => 3]));
                    $fail("Çıkış, atölyede kalan miktardan ({$kalan}) fazla olamaz.");
                }
            }],
            'labor_purity' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if (Workshop::parseLaborPurity((string) $value) === null) {
                    $fail('Çıkış işçiliği geçersiz. Örnek: 0,040 (en fazla 0,200)');
                }
            }],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'delivered_at' => 'çıkış tarihi',
            'gross_out' => 'çıkış gramı',
            'labor_purity' => 'çıkış işçiliği',
            'notes' => 'not',
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
            'labor_purity' => Workshop::parseLaborPurity($this->input('labor_purity')),
        ];
    }
}
