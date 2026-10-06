<?php

return [

    /*
    |--------------------------------------------------------------------------
    | İlk Yönetici Hesabı
    |--------------------------------------------------------------------------
    |
    | "php artisan db:seed" çalıştırıldığında bu bilgilerle yönetici hesabı
    | oluşturulur. Hesap zaten varsa değiştirilmez.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Yönetici'),
        'username' => env('ADMIN_USERNAME', 'admin'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firma Bilgileri
    |--------------------------------------------------------------------------
    |
    | Müşteriye verilen fişlerin başlığında görünür.
    |
    */

    'firma' => [
        'name' => env('FIRMA_ADI', env('APP_NAME', 'Kuyumcu Atölye')),
        'phone' => env('FIRMA_TELEFON'),
        'address' => env('FIRMA_ADRES'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ayar → Milyem
    |--------------------------------------------------------------------------
    |
    | Atölye girişinde ürün adına "14 ayar" gibi yazılınca milyem kutusu
    | bu değerlerle otomatik dolar (elle değiştirilebilir).
    |
    */

    'ayar_milyem' => [
        8 => '0,333',
        10 => '0,417',
        14 => '0,585',
        18 => '0,750',
        22 => '0,916',
    ],

    /*
    |--------------------------------------------------------------------------
    | Yedekleme
    |--------------------------------------------------------------------------
    |
    | Otomatik yedek saatleri (günde 3 kez) ve Google Drive bağlantısı.
    | Google bilgileri Google Cloud Console'da oluşturulan OAuth istemcisinden gelir.
    |
    */

    'backup' => [
        'times' => ['10:00', '15:00', '20:00'],
        'drive_keep' => 90, // Drive'da saklanacak en fazla yedek sayısı (~30 gün)
        'drive_folder_name' => 'Kuyumcu Yedekleri',
        'google_client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'google_client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    ],

];
