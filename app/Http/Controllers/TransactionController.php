<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Models\Account;
use App\Models\CashRegister;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\WorkOrder;
use App\Support\Amount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
            'tur' => ['nullable', 'string'],
            'birim' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $transactions = Transaction::query()
            ->with(['account', 'cashRegister', 'currency', 'creator'])
            ->when($filters['baslangic'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['bitis'] ?? null, fn ($q, $d) => $q->where('date', '<', Carbon::parse($d)->addDay()->toDateString()))
            ->when($filters['tur'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['birim'] ?? null, fn ($q, $c) => $q->where('currency_id', $c))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('description', 'like', "%{$term}%")
                        ->orWhere('document_no', 'like', "%{$term}%")
                        ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'types' => TransactionType::cases(),
            'currencies' => Currency::query()->orderBy('sort')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $type = TransactionType::tryFrom((string) $request->query('tur')) ?? TransactionType::Tahsilat;

        $transaction = new Transaction([
            'date' => now()->format('Y-m-d H:i:s'),
            'type' => $type,
            'account_id' => $request->integer('cari') ?: null,
            'cash_register_id' => $request->integer('kasa') ?: CashRegister::query()->active()->value('id'),
            'currency_id' => Currency::query()->active()->value('id'),
        ]);

        return view('transactions.create', $this->formData($transaction) + [
            'back' => $this->backUrl($transaction),
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $transaction = new Transaction($request->transactionData());
        $transaction->created_by = $request->user()->id;
        $transaction->updated_by = $request->user()->id;
        $transaction->save();

        $message = $transaction->type->label().' kaydedildi: '
            .Amount::format($transaction->amount, $transaction->currency);

        if ($request->boolean('yeni')) {
            return redirect()->route('transactions.create', [
                'tur' => $transaction->type->value,
                'cari' => $transaction->account_id,
                'kasa' => $transaction->cash_register_id,
            ])->with('success', $message);
        }

        return redirect($this->backUrl($transaction))->with('success', $message);
    }

    public function edit(Transaction $transaction): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfLinked($transaction)) {
            return $redirect;
        }

        return view('transactions.edit', $this->formData($transaction) + [
            'back' => $this->backUrl($transaction),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        if ($redirect = $this->redirectIfLinked($transaction)) {
            return $redirect;
        }

        $transaction->fill($request->transactionData());
        $transaction->updated_by = $request->user()->id;
        $transaction->save();

        return redirect($this->backUrl($transaction))->with('success', 'Hareket güncellendi.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($redirect = $this->redirectIfLinked($transaction)) {
            return $redirect;
        }

        $back = $this->backUrl($transaction);
        $transaction->delete();

        return redirect($back)->with('success', 'Hareket silindi.');
    }

    /** Atölye fişinden oluşan kayıtlar sadece fiş üzerinden değiştirilebilir. */
    private function redirectIfLinked(Transaction $transaction): ?RedirectResponse
    {
        $order = WorkOrder::linkedTo($transaction);

        return $order
            ? redirect()->route('work-orders.show', $order)
                ->with('error', "Bu kayıt {$order->number} atölye fişinden otomatik oluşturuldu. Değişikliği fiş üzerinden yapın.")
            : null;
    }

    private function formData(Transaction $transaction): array
    {
        return [
            'transaction' => $transaction,
            'types' => TransactionType::cases(),
            'accounts' => Account::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $transaction->account_id))
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'registers' => CashRegister::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $transaction->cash_register_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'currencies' => Currency::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $transaction->currency_id))
                ->orderBy('sort')
                ->get(),
        ];
    }

    /** İşlemden sonra dönülecek sayfa: varsa carinin, yoksa kasanın ekstresi. */
    private function backUrl(Transaction $transaction): string
    {
        if ($transaction->account_id) {
            return route('accounts.show', $transaction->account_id);
        }

        if ($transaction->cash_register_id && $transaction->type->requiresCashRegister()) {
            return route('cash-registers.show', $transaction->cash_register_id);
        }

        return route('transactions.index');
    }
}
