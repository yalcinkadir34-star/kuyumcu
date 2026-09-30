<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Support\Balances;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $currencies = Currency::activeList();
        $summary = Balances::summary();

        $try = $currencies->firstWhere('code', 'TRY');
        $has = $currencies->firstWhere('code', 'HAS');

        $stats = [
            ['label' => 'Aktif Cari', 'value' => number_format(Account::where('is_active', true)->count(), 0, ',', '.'), 'hint' => 'Toplam '.Account::count().' cari'],
            ['label' => 'Kasa (TL)', 'milli' => $try ? ($summary[$try->id]['kasa'] ?? 0) : 0, 'currency' => $try, 'hint' => 'Tüm kasaların TL mevcudu'],
            ['label' => 'Kasa (Has Altın)', 'milli' => $has ? ($summary[$has->id]['kasa'] ?? 0) : 0, 'currency' => $has, 'hint' => 'Tüm kasaların has altın mevcudu'],
            ['label' => 'Bugünkü İşlemler', 'value' => Transaction::whereDate('date', today())->count(), 'hint' => 'Bugün tarihli hareket sayısı'],
        ];

        return view('dashboard', [
            'stats' => $stats,
            'currencies' => $currencies,
            'summary' => $summary,
            'recent' => Transaction::with(['account', 'cashRegister', 'currency'])->latest('id')->limit(8)->get(),
            'today' => Carbon::now()->locale('tr')->translatedFormat('d F Y, l'),
        ]);
    }
}
