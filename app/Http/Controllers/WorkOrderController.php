<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliverWorkOrderRequest;
use App\Http\Requests\WorkOrderRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\WorkOrder;
use App\Support\Amount;
use App\Support\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'durum' => ['nullable', 'in:atolyede,teslim_edildi,tumu'],
            'cari' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
        ]);
        $status = $filters['durum'] ?? 'atolyede';

        $orders = WorkOrder::query()
            ->with(['account', 'laborCurrency'])
            ->when($status !== 'tumu', fn ($q) => $q->where('status', $status))
            ->when($filters['cari'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
            ->when($filters['baslangic'] ?? null, fn ($q, $d) => $q->where('received_at', '>=', $d))
            ->when($filters['bitis'] ?? null, fn ($q, $d) => $q->where('received_at', '<=', $d))
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
            'status' => $status,
            'accounts' => Account::query()->whereHas('workOrders')->orderBy('name')->get(['id', 'name']),
            'summary' => $this->summary(),
        ]);
    }

    public function create(Request $request): View
    {
        $order = new WorkOrder([
            'account_id' => $request->integer('cari') ?: null,
            'received_at' => now()->toDateString(),
        ]);

        return view('work-orders.create', $this->formData($order));
    }

    public function store(WorkOrderRequest $request): RedirectResponse
    {
        $order = new WorkOrder($request->orderData());
        $order->created_by = $request->user()->id;
        $order->saveWithTransactions($request->user());

        $message = "{$order->number} atölyeye alındı: {$order->product}. "
            .'Firmanın carisine '.Amount::format($order->has_in, Currency::firstWhere('code', 'HAS')).' has alacak yazıldı.';

        if ($request->boolean('yeni')) {
            return redirect()->route('work-orders.create', ['cari' => $order->account_id])->with('success', $message);
        }

        return redirect()->route('work-orders.show', $order)->with('success', $message);
    }

    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load(['account', 'laborCurrency', 'transaction', 'creator']);

        // Teslim formu için varsayılanlar: bu firmanın son işçilik ayarı
        $lastDelivered = WorkOrder::query()
            ->where('account_id', $workOrder->account_id)
            ->where('status', WorkOrder::STATUS_TESLIM)
            ->where('id', '!=', $workOrder->id)
            ->latest('delivered_at')
            ->first();

        return view('work-orders.show', [
            'order' => $workOrder,
            'currencies' => Currency::activeList(),
            'defaults' => [
                'fire_bearer' => $workOrder->fire_bearer ?? $lastDelivered?->fire_bearer ?? WorkOrder::FIRE_FIRMA,
                'labor_basis' => $workOrder->labor_basis ?? $lastDelivered?->labor_basis ?? 'gram',
                'labor_rate' => $workOrder->labor_rate ?? $lastDelivered?->labor_rate,
                'labor_currency_id' => $workOrder->labor_currency_id
                    ?? $lastDelivered?->labor_currency_id
                    ?? Currency::firstWhere('code', 'TRY')?->id,
            ],
        ]);
    }

    public function edit(WorkOrder $workOrder): View
    {
        return view('work-orders.edit', $this->formData($workOrder));
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $workOrder->fill($request->orderData());
        $workOrder->saveWithTransactions($request->user());

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', 'Giriş bilgileri güncellendi, cari has kayıtları yeniden hesaplandı.');
    }

    public function deliver(DeliverWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $wasDelivered = $workOrder->isDelivered();
        $workOrder->deliver($request->deliveryData(), $request->user());

        $message = $wasDelivered ? 'Teslim bilgileri güncellendi.' : "{$workOrder->number} teslim edildi.";
        $message .= ' Cariye işlendi: '.Amount::format($workOrder->has_out, Currency::firstWhere('code', 'HAS')).' has teslim';

        if ($workOrder->fire_bearer === WorkOrder::FIRE_FIRMA && $workOrder->fire_transaction_id) {
            $message .= ', '.Amount::format($workOrder->fire_has, Currency::firstWhere('code', 'HAS')).' has fire';
        }

        if ($workOrder->transaction_id) {
            $message .= ', '.Amount::format($workOrder->labor_total, $workOrder->laborCurrency).' işçilik';
        }

        $message .= '.';

        return redirect()->route('work-orders.show', $workOrder)->with('success', $message);
    }

    public function undeliver(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $workOrder->undeliver();

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', 'Teslim geri alındı, ürün tekrar atölyede. Teslim, fire ve işçilik cari kayıtları silindi.');
    }

    public function destroy(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($workOrder->isDelivered()) {
            return back()->with('error', 'Teslim edilmiş fiş silinemez. Önce teslimi geri alın.');
        }

        $workOrder->delete();

        return redirect()->route('work-orders.index')->with('success', "{$workOrder->number} silindi.");
    }

    private function formData(WorkOrder $order): array
    {
        return [
            'order' => $order,
            'accounts' => Account::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $order->account_id))
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            // Her firmanın son kullandığı milyem, formda öneri olarak gösterilir
            'lastPurities' => WorkOrder::query()
                ->select('account_id', 'purity')
                ->whereIn('id', WorkOrder::query()->selectRaw('MAX(id)')->groupBy('account_id'))
                ->pluck('purity', 'account_id'),
        ];
    }

    /** Özet kartları: atölyedeki ürünler ve bu ayın fire/işçilik toplamları. */
    private function summary(): array
    {
        $inWorkshop = WorkOrder::query()->inWorkshop()
            ->selectRaw('COUNT(*) as adet, COALESCE(SUM(gross_in), 0) as gram, COALESCE(SUM(has_in), 0) as has')
            ->toBase()->first();

        $month = WorkOrder::query()
            ->where('status', WorkOrder::STATUS_TESLIM)
            ->whereBetween('delivered_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('COUNT(*) as adet, COALESCE(SUM(gross_in), 0) as giris, COALESCE(SUM(fire_gram), 0) as fire, COALESCE(SUM(fire_has), 0) as fire_has')
            ->toBase()->first();

        $fireMilli = Amount::toMilli($month->fire);

        return [
            'atolyede_adet' => (int) $inWorkshop->adet,
            'atolyede_gram' => Amount::toMilli($inWorkshop->gram),
            'atolyede_has' => Amount::toMilli($inWorkshop->has),
            'ay_adet' => (int) $month->adet,
            'ay_fire' => $fireMilli,
            'ay_fire_has' => Amount::toMilli($month->fire_has),
            'ay_fire_orani' => Workshop::fireRate($fireMilli, Amount::toMilli($month->giris)),
        ];
    }
}
