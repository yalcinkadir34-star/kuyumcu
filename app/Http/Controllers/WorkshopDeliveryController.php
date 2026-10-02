<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkshopDeliveryRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\WorkOrderDelivery;
use App\Support\Amount;
use App\Support\Balances;
use App\Support\Workshop;
use App\Support\WorkshopTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Atölyeden müşteriye çıkışlar. Bir giriş fişine bağlı değildir:
 * müşteri seçilir, gram ve çıkış milyemi girilir.
 */
class WorkshopDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'cari' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
        ]);

        $deliveries = WorkOrderDelivery::query()
            ->with('account')
            ->when($filters['cari'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
            ->when($filters['baslangic'] ?? null, fn ($q, $d) => $q->where('delivered_at', '>=', $d))
            ->when($filters['bitis'] ?? null, fn ($q, $d) => $q->where('delivered_at', '<', Carbon::parse($d)->addDay()->toDateString()))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                    ->orWhere('product', 'like', "%{$term}%")
                    ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%{$term}%")));
            })
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('workshop-deliveries.index', [
            'deliveries' => $deliveries,
            'accounts' => Account::query()->whereHas('workshopDeliveries')->orderBy('name')->get(['id', 'name']),
            'summary' => WorkOrderController::summary(),
        ]);
    }

    public function create(Request $request): View
    {
        $totals = WorkshopTotals::forAccounts();

        // Carisi kayıtlı tüm aktif müşteriler: atölyede ürünü olmayana da (satış) çıkış yapılabilir
        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $gr = new Currency(['symbol' => 'gr', 'decimals' => 3]);

        // Formda müşteri seçilince gösterilecek bilgiler: atölyede kalan gram ve son çıkış milyemi
        $lastPurities = WorkOrderDelivery::query()
            ->whereIn('id', WorkOrderDelivery::query()->selectRaw('MAX(id)')->groupBy('account_id'))
            ->pluck('purity_out', 'account_id');

        $info = $accounts->mapWithKeys(function (Account $a) use ($totals, $gr, $lastPurities) {
            $ramat = max($totals[$a->id]['ramat_gram'] ?? 0, 0);

            return [$a->id => [
                'kalan' => Amount::formatMilli($ramat, $gr),
                'kalanSayi' => Amount::fromMilli($ramat),
                'sonMilyem' => isset($lastPurities[$a->id]) ? Workshop::formatPurity($lastPurities[$a->id]) : null,
            ]];
        });

        return view('workshop-deliveries.create', [
            'accounts' => $accounts,
            'info' => $info,
            'selected' => $request->integer('cari') ?: null,
        ]);
    }

    public function store(WorkshopDeliveryRequest $request): RedirectResponse
    {
        $delivery = new WorkOrderDelivery($request->deliveryData());
        $delivery->saveWithTransactions($request->user()->id);

        $gr = new Currency(['symbol' => 'gr', 'decimals' => 3]);
        $kalan = WorkshopTotals::forAccount($delivery->account_id)['ramat_gram'];

        $message = "{$delivery->number} çıkışı kaydedildi: ".Amount::format($delivery->gross_out, $gr)
            .' × '.Workshop::formatPurity($delivery->purity_out)
            .' = '.Amount::format($delivery->has_out, $gr).' has müşterinin carisine borç yazıldı.'
            .($delivery->isSale() ? ' (Satış: ramatı etkilemez.)' : ' Müşterinin atölyede kalanı: '.Amount::formatMilli($kalan, $gr).'.');

        return redirect()->route('workshop-deliveries.index')
            ->with('success', $message)
            ->with('receipt_delivery_id', $delivery->id);
    }

    public function destroy(Request $request, WorkOrderDelivery $delivery): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $delivery->delete();

        return redirect()->route('workshop-deliveries.index')
            ->with('success', "{$delivery->number} çıkışı silindi, cari kaydı geri alındı.");
    }

    /** Müşteriye verilen bilgi fişi (yazdırılabilir). ?boyut=80 → 80 mm fiş yazıcısı */
    public function receipt(Request $request, WorkOrderDelivery $delivery): View
    {
        $delivery->load('account');

        // Müşterinin bu çıkıştan hemen sonraki bakiyesi (fiş sonradan yazdırılsa da değişmez)
        $balances = $delivery->out_transaction_id
            ? Balances::forAccountUntil($delivery->account_id, $delivery->delivered_at->format('Y-m-d H:i:s'), $delivery->out_transaction_id)
            : $delivery->account->balances();

        return view('workshop-deliveries.receipt', [
            'delivery' => $delivery,
            'balances' => $balances,
            'currencies' => Currency::query()->orderBy('sort')->get()->keyBy('id'),
            'size' => $request->query('boyut') === '80' ? '80' : 'a5',
            'firma' => config('kuyumcu.firma'),
        ]);
    }
}
