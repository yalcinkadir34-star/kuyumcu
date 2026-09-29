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

];
