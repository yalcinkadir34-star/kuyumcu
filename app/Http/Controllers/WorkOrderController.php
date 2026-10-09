<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrderRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\WorkOrder;
use App\Support\Amount;
use App\Support\Workshop;
use App\Support\WorkshopTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Atölyeye girişler (müşterinin getirdiği ürünler). Çıkışlar: WorkshopDeliveryController. */
class WorkOrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'cari' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
        ]);

        $orders = WorkOrder::query()
            ->with('account')
            ->when($filters['cari'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
            ->when($filters['baslangic'] ?? null, fn ($q, $d) => $q->where('received_at', '>=', $d))
            ->when($filters['bitis'] ?? null, fn ($q, $d) => $q->where('received_at', '<', Carbon::parse($d)->addDay()->toDateString()))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                    ->orWhere('product', 'like', "%{$term}%")
                    ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%{$term}%")));
            })
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('work-orders.index', [
            'orders' => $orders,
            'accounts' => Account::query()->whereHas('workOrders')->orderBy('name')->get(['id', 'name']),
            'summary' => self::summary(),
        ]);
    }

    public function create(Request $request): View
    {
        $order = new WorkOrder([
            'account_id' => $request->integer('cari') ?: null,
            'received_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return view('work-orders.create', $this->formData($order));
    }

    public function store(WorkOrderRequest $request): RedirectResponse
    {
        $order = new WorkOrder($request->orderData());
        $order->created_by = $request->user()->id;
        $order->saveWithTransactions($request->user());

        $message = "{$order->number} atölyeye alındı: {$order->product}. "
            .'Müşterinin carisine '.Amount::format($order->has_in, Currency::firstWhere('code', 'HAS')).' has alacak yazıldı.';

        if ($request->boolean('yeni')) {
            return redirect()->route('work-orders.create', ['cari' => $order->account_id])->with('success', $message)->with('ses', 'giris');
        }

        return redirect()->route('work-orders.show', $order)->with('success', $message)->with('ses', 'giris');
    }

    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load(['account', 'creator']);

        return view('work-orders.show', [
            'order' => $workOrder,
            'totals' => WorkshopTotals::forAccount($workOrder->account_id),
        ]);
    }

    public function edit(WorkOrder $workOrder): View
    {
        return view('work-orders.edit', $this->formData($workOrder));
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->orderData();

        if ($error = $this->checkRamatAfterChange($workOrder, (int) $data['account_id'], Amount::toMilli($data['gross_in']))) {
            return back()->withInput()->withErrors(['gross_in' => $error]);
        }

        $workOrder->fill($data);
        $workOrder->saveWithTransactions($request->user());

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', 'Giriş bilgileri güncellendi, cari has kaydı yeniden hesaplandı.');
    }

    public function destroy(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($error = $this->checkRamatAfterChange($workOrder, $workOrder->account_id, 0)) {
            return back()->with('error', $error);
        }

        $workOrder->delete();

        return redirect()->route('work-orders.index')->with('success', "{$workOrder->number} silindi.");
    }

    /**
     * Giriş değiştirilir/silinirse müşterinin girişleri çıkışlarının altına düşmemeli
     * (atölyeden, müşterinin getirdiğinden fazlası çıkmış olamaz).
     */
    private function checkRamatAfterChange(WorkOrder $order, int $newAccountId, int $newGramMilli): ?string
    {
        $oldGram = Amount::toMilli($order->gross_in);

        // Eski müşteriden bu giriş düşülür; müşteri değişmiyorsa yeni gram eklenir
        $ramat = WorkshopTotals::forAccount($order->account_id)['ramat_gram'] - $oldGram
            + ($newAccountId === $order->account_id ? $newGramMilli : 0);

        return $ramat < 0
            ? 'Bu değişiklikle müşterinin girişleri, yapılmış çıkışlarının altına düşer. Önce çıkışları kontrol edin.'
            : null;
    }

    private function formData(WorkOrder $order): array
    {
        return [
            'order' => $order,
            'accounts' => Account::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $order->account_id))
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            // Her müşterinin son giriş milyemi, formda öneri olarak gösterilir
            'lastPurities' => WorkOrder::query()
                ->whereIn('id', WorkOrder::query()->selectRaw('MAX(id)')->groupBy('account_id'))
                ->get(['account_id', 'purity'])
                ->mapWithKeys(fn (WorkOrder $o) => [$o->account_id => Workshop::formatPurity($o->purity)]),
        ];
    }

    /** Atölye özet kartları (giriş ve çıkış sayfalarında ortak). */
    public static function summary(): array
    {
        $total = WorkshopTotals::sum(WorkshopTotals::forAccounts());
        $month = WorkshopTotals::sum(WorkshopTotals::forAccounts(
            null,
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ));

        return [
            'ramat_gram' => $total['ramat_gram'],
            'ramat_has' => $total['ramat_has'],
            'ramat_orani' => $total['oran'],
            'ay_giris_adet' => $month['giris_adet'],
            'ay_giris_gram' => $month['giris_gram'],
            'ay_giris_has' => $month['giris_has'],
            'ay_cikis_adet' => $month['cikis_adet'],
            'ay_cikis_gram' => $month['cikis_gram'],
            'ay_cikis_has' => $month['cikis_has'],
        ];
    }
}
