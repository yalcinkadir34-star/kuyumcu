<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\Transaction;

/**
 * Başka bir kayda (ör. atölye fişi) bağlı otomatik cari hareketini
 * oluşturur, günceller ya da tutar sıfırsa siler.
 */
class LinkedTransaction
{
    /**
     * @return int|null hareketin id'si (silindiyse null)
     */
    public static function sync(
        ?int $id,
        TransactionType $type,
        int $milli,
        Currency $currency,
        mixed $date,
        int $accountId,
        string $documentNo,
        string $description,
        ?int $userId,
    ): ?int {
        $transaction = $id ? Transaction::find($id) : null;

        if ($milli <= 0) {
            $transaction?->delete();

            return null;
        }

        $transaction ??= new Transaction;
        $transaction->fill([
            'type' => $type,
            'date' => $date,
            'account_id' => $accountId,
            'currency_id' => $currency->id,
            'amount' => Amount::fromMilli($milli),
            'document_no' => $documentNo,
            'description' => $description,
        ]);
        $transaction->created_by ??= $userId;
        $transaction->updated_by = $userId;
        $transaction->save();

        return $transaction->id;
    }
}
