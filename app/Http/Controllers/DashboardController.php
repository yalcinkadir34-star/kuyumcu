<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // Özet kartları; ilgili modüller eklendikçe gerçek verilerle doldurulacak.
        $stats = [
            ['label' => 'Toplam Cari', 'value' => null, 'hint' => 'Cari modülü ile gelecek'],
            ['label' => 'Kasa (TL)', 'value' => null, 'hint' => 'Kasa modülü ile gelecek'],
            ['label' => 'Has Altın Bakiyesi', 'value' => null, 'hint' => 'Atölye hesapları ile gelecek'],
            ['label' => 'Bugünkü İşlemler', 'value' => null, 'hint' => 'Hareket kayıtları ile gelecek'],
        ];

        return view('dashboard', [
            'stats' => $stats,
            'today' => Carbon::now()->locale('tr')->translatedFormat('d F Y, l'),
        ]);
    }
}
