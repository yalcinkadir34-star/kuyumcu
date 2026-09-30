<?php

namespace App\Http\Requests;

use App\Support\Amount;
use App\Support\Workshop;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Atölye girişi: firma, ürün, brüt gram, milyem. */
class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', Rule::exists('accounts', 'id')],
            'product' => ['required', 'string', 'max:255'],
            'received_at' => ['required', 'date'],
            'gross_in' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $parsed = Amount::parse((string) $value);

                if ($parsed === null || Amount::toMilli($parsed) <= 0) {
                    $fail('Giriş gramı geçersiz. Örnek: 200 veya 200,450');
                } elseif (Amount::decimalsOf($parsed) > 3) {
                    $fail('Gram en fazla 3 ondalık basamak olabilir.');
                }
            }],
            'purity' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (Workshop::parsePurity((string) $value) === null) {
                    $fail('Milyem geçersiz. Örnek: 0,585 veya 585');
                }
            }],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_id' => 'firma',
            'product' => 'ürün',
            'received_at' => 'giriş tarihi',
            'gross_in' => 'giriş gramı',
            'purity' => 'milyem',
        ];
    }

    public function orderData(): array
    {
        return [
            ...$this->validated(),
            'gross_in' => Amount::parse($this->input('gross_in')),
            'purity' => Workshop::parsePurity($this->input('purity')),
        ];
    }
}
