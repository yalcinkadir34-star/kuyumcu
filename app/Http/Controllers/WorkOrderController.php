<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliverWorkOrderRequest;
use App\Http\Requests\WorkOrderRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\WorkOrder;
use App\Models\WorkOrderDelivery;
use App\Support\Amount;
use App\Support\Balances;
use App\Support\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'durum' => ['nullable', 'in:atolyede,tamamlandi,tumu'],
            'cari' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
        ]);
        $status = $filters['durum'] ?? 'atolyede';

        $orders = WorkOrder::query()
            ->with('account')
            ->withDeliveryTotals()
            ->when($status !== 'tumu', fn ($q) => $q->where('status', $status))
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
            'status' => $status,
            'accounts' => Account::query()->whereHas('workOrders')->orderBy('name')->get(['id', 'name']),
            'summary' => $this->summary(),
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
            .'Firmanın carisine '.Amount::format($order->has_in, Currency::firstWhere('code', 'HAS')).' has alacak yazıldı.';

        if ($request->boolean('yeni')) {
            return redirect()->route('work-orders.create', ['cari' => $order->account_id])->with('success', $message);
        }

        return redirect()->route('work-orders.show', $order)->with('success', $message);
    }

    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load(['account', 'creator', 'deliveries']);

        // Çıkış işçiliği varsayılanı: bu fişin ya da firmanın son çıkışındaki işçilik milyemi
        $lastLabor = $workOrder->deliveries->last()?->labor_purity
            ?? WorkOrderDelivery::query()
                ->whereHas('workOrder', fn ($q) => $q->where('account_id', $workOrder->account_id))
                ->latest('id')
                ->value('labor_purity');

        return view('work-orders.show', [
            'order' => $workOrder,
            'defaultLaborPurity' => $lastLabor,
        ]);
    }

    public function edit(WorkOrder $workOrder): View
    {
        return view('work-orders.edit', $this->formData($workOrder));
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->orderData();

        if (Amount::toMilli($data['gross_in']) < $workOrder->deliveredMilli()) {
            return back()->withInput()->withErrors(['gross_in' => 'Giriş gramı, yapılmış çıkışların toplamından az olamaz.']);
        }

        $workOrder->fill($data);
        $workOrder->saveWithTransactions($request->user());

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', 'Giriş bilgileri güncellendi, cari has kayıtları yeniden hesaplandı.');
    }

    /** Yeni çıkış (parçalı teslim). */
    public function deliver(DeliverWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $delivery = $workOrder->addDelivery($request->deliveryData(), $request->user());
        $gr = new Currency(['symbol' => 'gr', 'decimals' => 3]);

        $message = 'Çıkış kaydedildi: '.Amount::format($delivery->gross_out, $gr)
            .' × '.Workshop::formatPurity($delivery->outPurity())
            .' = '.Amount::format($delivery->has_out, $gr).' has cariye borç yazıldı';

        $workOrder->unsetRelation('deliveries');
        $message .= '. Atölyede kalan: '.Amount::formatMilli($workOrder->remainingMilli(), $gr).'.';

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', $message)
            ->with('receipt_delivery_id', $delivery->id);
    }

    /** Müşteriye verilen bilgi fişi (yazdırılabilir). ?boyut=80 → 80 mm fiş yazıcısı */
    public function receipt(Request $request, WorkOrder $workOrder, WorkOrderDelivery $delivery): View
    {
        $workOrder->load(['account', 'deliveries']);

        // Bu çıkışın fişteki sırası
        $sequence = $workOrder->deliveries->takeUntil(fn ($d) => $d->id === $delivery->id)->count() + 1;

        // Müşterinin bu çıkıştan hemen sonraki bakiyesi (fiş sonradan yazdırılsa da değişmez)
        $balances = $delivery->out_transaction_id
            ? Balances::forAccountUntil($workOrder->account_id, $delivery->delivered_at->format('Y-m-d H:i:s'), $delivery->out_transaction_id)
            : $workOrder->account->balances();

        return view('work-orders.receipt', [
            'order' => $workOrder,
            'delivery' => $delivery,
            'sequence' => $sequence,
            'balances' => $balances,
            'currencies' => Currency::query()->orderBy('sort')->get()->keyBy('id'),
            'size' => $request->query('boyut') === '80' ? '80' : 'a5',
            'firma' => config('kuyumcu.firma'),
        ]);
    }

    public function destroyDelivery(Request $request, WorkOrder $workOrder, WorkOrderDelivery $delivery): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        DB::transaction(function () use ($workOrder, $delivery) {
            $delivery->delete();

            if ($workOrder->isClosed()) {
                $workOrder->reopen();
            }
        });

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', 'Çıkış silindi, cari kayıtları geri alındı.');
    }

    /** Fişi kapat: atölyede kalan miktar fire sayılır (cariye işlenmez). */
    public function close(WorkOrder $workOrder): RedirectResponse
    {
        $workOrder->close();

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', "{$workOrder->number} kapatıldı.");
    }

    public function reopen(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $workOrder->reopen();

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', "{$workOrder->number} tekrar atölyede.");
    }

    public function destroy(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($workOrder->deliveries()->exists()) {
            return back()->with('error', 'Çıkışı olan fiş silinemez. Önce çıkışları silin.');
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
            // Her firmanın son kullandığı ayar ve giriş işçiliği, formda öneri olarak gösterilir
            'lastPurities' => WorkOrder::query()
                ->whereIn('id', WorkOrder::query()->selectRaw('MAX(id)')->groupBy('account_id'))
                ->get(['account_id', 'purity', 'labor_purity_in'])
                ->mapWithKeys(fn (WorkOrder $o) => [$o->account_id => [
                    'purity' => Workshop::formatPurity($o->purity),
                    'labor' => Workshop::formatPurity($o->labor_purity_in),
                ]]),
        ];
    }

    /** Özet kartları: atölyede kalan, bu ayın çıkışları ve kapanan fişlerin firesi. */
    private function summary(): array
    {
        $open = WorkOrder::query()->inWorkshop()->withDeliveryTotals()->get();

        $monthStart = now()->startOfMonth()->format('Y-m-d H:i:s');
        $monthEnd = now()->endOfMonth()->format('Y-m-d H:i:s');

        $monthOut = WorkOrderDelivery::query()
            ->whereBetween('delivered_at', [$monthStart, $monthEnd])
            ->selectRaw('COUNT(*) as adet, COALESCE(SUM(gross_out), 0) as gram, COALESCE(SUM(has_out), 0) as has')
            ->toBase()->first();

        $closed = WorkOrder::query()
            ->where('status', WorkOrder::STATUS_TAMAMLANDI)
            ->whereBetween('closed_at', [$monthStart, $monthEnd])
            ->withDeliveryTotals()
            ->get();

        $fire = $closed->sum(fn (WorkOrder $o) => $o->remainingMilli());
        $closedIn = $closed->sum(fn (WorkOrder $o) => Amount::toMilli($o->gross_in));

        return [
            'atolyede_adet' => $open->count(),
            'atolyede_gram' => $open->sum(fn (WorkOrder $o) => $o->remainingMilli()),
            'atolyede_has' => $open->sum(fn (WorkOrder $o) => $o->remainingHasMilli()),
            'ay_cikis_adet' => (int) $monthOut->adet,
            'ay_cikis_gram' => Amount::toMilli($monthOut->gram),
            'ay_cikis_has' => Amount::toMilli($monthOut->has),
            'ay_kapanan' => $closed->count(),
            'ay_fire' => $fire,
            'ay_fire_has' => $closed->sum(fn (WorkOrder $o) => $o->remainingHasMilli()),
            'ay_fire_orani' => Workshop::fireRate($fire, $closedIn),
        ];
    }
}
