<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\WorkOrder;
use App\Models\WorkOrderDelivery;
use App\Support\Amount;
use App\Support\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Müşteri raporu (has hesabı): müşteriye verilebilecek, yazdırılabilir dönem dökümü.
 * Dönem: bir yıl (varsayılan bu yıl) veya özel tarih aralığı.
 * Ramat bilgisi atölyenin iç bilgisi olduğu için raporda yer almaz.
 */
class ReportController extends Controller
{
    public function customer(Request $request): View
    {
        $filters = $request->validate([
            'cari' => ['nullable', 'integer', 'exists:accounts,id'],
            'yil' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date', 'after_or_equal:baslangic'],
        ]);

        $accounts = Account::query()->orderBy('name')->get(['id', 'code', 'name']);
        $years = $this->availableYears();
        $year = (int) ($filters['yil'] ?? now()->year);

        // Özel aralık verilmişse o, yoksa seçilen yılın tamamı
        $custom = ! empty($filters['baslangic']) || ! empty($filters['bitis']);
        $from = $custom ? ($filters['baslangic'] ?? '2000-01-01') : "{$year}-01-01";
        $to = $custom ? ($filters['bitis'] ?? now()->toDateString()) : "{$year}-12-31";

        $data = [
            'accounts' => $accounts,
            'years' => $years,
            'year' => $year,
            'custom' => $custom,
            'from' => Carbon::parse($from),
            'to' => Carbon::parse($to),
            'account' => null,
            'firma' => config('kuyumcu.firma'),
        ];

        if (empty($filters['cari'])) {
            return view('reports.customer', $data);
        }

        $account = Account::findOrFail($filters['cari']);
        $has = Currency::firstWhere('code', 'HAS');

        $ledger = Ledger::build(
            Transaction::query()->where('account_id', $account->id)->where('currency_id', $has->id),
            'account_direction',
            $from,
            $to,
        );

        $rows = $this->withWorkshopDetails($ledger['rows']);

        return view('reports.customer', [
            ...$data,
            'account' => $account,
            'has' => $has,
            'rows' => $rows,
            'opening' => $ledger['opening'][$has->id] ?? 0,
            'closing' => $ledger['closing'][$has->id] ?? ($ledger['opening'][$has->id] ?? 0),
            'totals' => $this->totals($rows),
            'months' => $custom ? null : $this->monthly($rows, $year),
        ]);
    }

    /**
     * Hareket satırlarına atölye bilgisi ekler: giriş/çıkış gramı, milyem, fiş no.
     * Elle girilen cari hareketlerde gram ve milyem boş kalır.
     */
    private function withWorkshopDetails($rows)
    {
        $ids = $rows->pluck('id');
        $entries = WorkOrder::query()->whereIn('in_transaction_id', $ids)->get()->keyBy('in_transaction_id');
        $exits = WorkOrderDelivery::query()->whereIn('out_transaction_id', $ids)->get()->keyBy('out_transaction_id');

        return $rows->map(function (Transaction $row) use ($entries, $exits) {
            // Yön: -1 = müşteri bize has verdi (giriş), +1 = müşteriye has verildi (çıkış)
            $row->is_in = $row->account_direction < 0;
            $row->gram_milli = null;
            $row->purity = null;
            $row->label = $row->description ?: $row->type->label();

            if ($order = $entries->get($row->id)) {
                $row->gram_milli = Amount::toMilli($order->gross_in);
                $row->purity = $order->purity;
                $row->label = 'Atölye girişi · '.$order->product;
                $row->document_no = $order->number;
            } elseif ($delivery = $exits->get($row->id)) {
                $row->gram_milli = Amount::toMilli($delivery->gross_out);
                $row->purity = $delivery->purity_out;
                $row->label = ($delivery->isSale() ? 'Satış' : 'Teslim').($delivery->product ? ' · '.$delivery->product : '');
                $row->document_no = $delivery->number;
            }

            return $row;
        });
    }

    private function totals($rows): array
    {
        return [
            'in_gram' => $rows->where('is_in', true)->sum('gram_milli'),
            'in_has' => $rows->where('is_in', true)->sum(fn ($r) => abs($r->effect_milli)),
            'out_gram' => $rows->where('is_in', false)->sum('gram_milli'),
            'out_has' => $rows->where('is_in', false)->sum(fn ($r) => abs($r->effect_milli)),
            'count' => $rows->count(),
        ];
    }

    /** Yıllık raporda ay ay giriş/çıkış toplamları (1–12). */
    private function monthly($rows, int $year): array
    {
        $months = [];

        foreach (range(1, 12) as $m) {
            $monthRows = $rows->filter(fn ($r) => (int) $r->date->format('n') === $m);
            $months[$m] = [
                'name' => Carbon::create($year, $m, 1)->locale('tr')->translatedFormat('F'),
            ] + $this->totals($monthRows);
        }

        return $months;
    }

    /** Raporlanabilecek yıllar: ilk hareketten bu yıla. */
    private function availableYears(): array
    {
        $first = Transaction::query()->min('date');
        $start = $first ? (int) Carbon::parse($first)->format('Y') : now()->year;

        return range(now()->year, min($start, now()->year));
    }
}
