<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Support\Amount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = TransactionType::tryFrom((string) $this->input('type'));

        return [
            'date' => ['required', 'date'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'account_id' => [
                Rule::requiredIf($type?->requiresAccount() ?? false),
                'nullable',
                Rule::exists('accounts', 'id'),
            ],
            'cash_register_id' => [
                Rule::requiredIf($type?->requiresCashRegister() ?? false),
                'nullable',
                Rule::exists('cash_registers', 'id'),
            ],
            'currency_id' => ['required', Rule::exists('currencies', 'id')->where('is_active', true)],
            'amount' => ['required', $this->validAmount(...)],
            'document_no' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function validAmount(string $attribute, mixed $value, Closure $fail): void
    {
        $parsed = Amount::parse((string) $value);

        if ($parsed === null) {
            $fail('Tutar geçersiz. Ondalık için virgül kullanın (ör. 1500 veya 1.500,50 veya 2,5).');

            return;
        }

        if (Amount::toMilli($parsed) <= 0) {
            $fail('Tutar sıfırdan büyük olmalıdır.');

            return;
        }

        $currency = Currency::find($this->input('currency_id'));

        if ($currency && Amount::decimalsOf($parsed) > $currency->decimals) {
            $fail("{$currency->name} için en fazla {$currency->decimals} ondalık basamak girilebilir.");
        }
    }

    /** Doğrulanmış veriler; tutar veritabanı biçimine çevrilmiş olarak. */
    public function transactionData(): array
    {
        return [
            ...$this->validated(),
            'amount' => Amount::parse($this->input('amount')),
        ];
    }
}
