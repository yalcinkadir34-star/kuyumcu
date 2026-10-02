<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\WorkOrder;
use App\Models\WorkOrderDelivery;
use App\Support\WorkshopTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Ramat hesabı: müşterinin atölyeye getirip geri almadığı, atölyede (ramatta) kalan altın.
 * Müşteriyle hesap kapatılmaz; ramat burada izlenir.
 *
 *   Ramat gram = müşterinin giriş gramları − çıkış gramları
 *   Ramat has  = ramat gram × müşterinin ortalama giriş milyemi
 *   Has borcu  = giriş has − çıkış has (müşterinin carisinde biriken borcumuz)
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
        $from = $filters['baslangic'] ?? null;
        $to = $filters['bitis'] ?? null;

        $totals = WorkshopTotals::forAccounts(null, $from, $to);
        $accounts = Account::query()->whereIn('id', array_keys($totals))->get()->keyBy('id');

        $customers = collect($totals)
            ->map(fn (array $row, int $id) => $row + ['account' => $accounts[$id]])
            ->sortByDesc('ramat_gram')
            ->values();

        $selected = isset($filters['cari']) ? Account::find($filters['cari']) : null;

        return view('ramat.index', [
            'customers' => $customers,
            'total' => WorkshopTotals::sum($totals),
            'selected' => $selected,
            'movements' => $selected ? $this->movements($selected, $from, $to) : collect(),
        ]);
    }

    /** Seçili müşterinin giriş ve çıkışları, tarih sırasıyla, yürüyen ramat ile. */
    private function movements(Account $account, ?string $from, ?string $to)
    {
        $until = $to ? Carbon::parse($to)->addDay()->toDateString() : null;

        $in = WorkOrder::query()->where('account_id', $account->id)
            ->when($from, fn ($q) => $q->where('received_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('received_at', '<', $until))
            ->get()
            ->map(fn (WorkOrder $o) => (object) [
                'date' => $o->received_at, 'type' => 'giris', 'number' => $o->number, 'product' => $o->product,
                'gram' => $o->gross_in, 'purity' => $o->purity, 'has' => $o->has_in,
                'url' => route('work-orders.show', $o), 'sort' => 'a'.$o->id,
            ]);

        // Satış çıkışları ramatı etkilemediği için burada gösterilmez
        $out = WorkOrderDelivery::query()->where('account_id', $account->id)
            ->where('kind', WorkOrderDelivery::KIND_ATOLYE)
            ->when($from, fn ($q) => $q->where('delivered_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('delivered_at', '<', $until))
            ->get()
            ->map(fn (WorkOrderDelivery $d) => (object) [
                'date' => $d->delivered_at, 'type' => 'cikis', 'number' => $d->number, 'product' => $d->product,
                'gram' => $d->gross_out, 'purity' => $d->purity_out, 'has' => $d->has_out,
                'url' => route('workshop-deliveries.receipt', $d), 'sort' => 'b'.$d->id,
            ]);

        return $in->concat($out)->sortBy([['date', 'asc'], ['sort', 'asc']])->values();
    }
}
