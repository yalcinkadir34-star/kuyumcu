<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\WorkOrder;
use App\Support\Amount;
use App\Support\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Ramat hesabı: atölyeye gelen ürünlerden müşteriye geri çıkmayan, atölyede (ramatta)
 * kalan altının müşteri bazında dökümü. Müşteriyle hesap kapatılmaz; ramat burada izlenir.
 *
 *   Ramat gram  = giriş gramı − çıkış gramları
 *   Ramat has   = ramat gram × giriş milyemi
 *   Has borcu   = giriş has − çıkış has (müşterinin carisinde biriken borcumuz)
 */
class RamatController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
            'cari' => ['nullable', 'integer'],
        ]);

        $orders = WorkOrder::query()
            ->with('account')
            ->withDeliveryTotals()
            ->when($filters['baslangic'] ?? null, fn ($q, $d) => $q->where('received_at', '>=', $d))
            ->when($filters['bitis'] ?? null, fn ($q, $d) => $q->where('received_at', '<', Carbon::parse($d)->addDay()->toDateString()))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $customers = $orders->groupBy('account_id')
            ->map(fn ($group) => $this->totals($group) + ['account' => $group->first()->account])
            ->sortByDesc('ramat_gram')
            ->values();

        $selected = isset($filters['cari']) ? Account::find($filters['cari']) : null;

        return view('ramat.index', [
            'customers' => $customers,
            'total' => $this->totals($orders),
            'selected' => $selected,
            'selectedOrders' => $selected ? $orders->where('account_id', $selected->id)->values() : collect(),
        ]);
    }

    /** Bir grup fişin giriş, çıkış ve ramat toplamları (binde bir birimli tam sayılar). */
    private function totals($orders): array
    {
        $girisGram = $orders->sum(fn (WorkOrder $o) => Amount::toMilli($o->gross_in));
        $ramatGram = $orders->sum(fn (WorkOrder $o) => $o->remainingMilli());

        return [
            'fis' => $orders->count(),
            'giris_gram' => $girisGram,
            'giris_has' => $orders->sum(fn (WorkOrder $o) => Amount::toMilli($o->has_in)),
            'cikis_gram' => $orders->sum(fn (WorkOrder $o) => $o->deliveredMilli()),
            'cikis_has' => $orders->sum(fn (WorkOrder $o) => Amount::toMilli($o->delivered_has ?? 0)),
            'ramat_gram' => $ramatGram,
            'ramat_has' => $orders->sum(fn (WorkOrder $o) => $o->remainingHasMilli()),
            'oran' => Workshop::fireRate($ramatGram, $girisGram),
        ];
    }
}
