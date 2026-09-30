<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Support\Balances;
use App\Support\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = Account::query()
            ->search($request->query('q'))
            ->when($request->query('tur'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('durum') === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($request->query('durum') === 'pasif', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('accounts.index', [
            'accounts' => $accounts,
            'balances' => Balances::forAccounts($accounts->pluck('id')->all()),
            'currencies' => Currency::activeList(),
            'types' => AccountType::cases(),
        ]);
    }

    public function create(): View
    {
        return view('accounts.create', [
            'account' => new Account(['type' => AccountType::Musteri, 'is_active' => true]),
            'types' => AccountType::cases(),
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $account = Account::create($request->validated());

        return redirect()->route('accounts.show', $account)
            ->with('success', "{$account->name} carisi oluşturuldu.");
    }

    public function show(Request $request, Account $account): View
    {
        $filters = $request->validate([
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
        ]);

        $ledger = Ledger::build(
            Transaction::query()->where('account_id', $account->id),
            'account_direction',
            $filters['baslangic'] ?? null,
            $filters['bitis'] ?? null,
        );

        return view('accounts.show', [
            'account' => $account,
            'balances' => $account->balances(),
            'ledger' => $ledger,
            'currencies' => Currency::query()->orderBy('sort')->get()->keyBy('id'),
            'quickTypes' => [TransactionType::Tahsilat, TransactionType::Odeme, TransactionType::CariBorc, TransactionType::CariAlacak],
        ]);
    }

    public function edit(Account $account): View
    {
        return view('accounts.edit', [
            'account' => $account,
            'types' => AccountType::cases(),
        ]);
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->validated());

        return redirect()->route('accounts.show', $account)
            ->with('success', 'Cari bilgileri güncellendi.');
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($account->transactions()->exists()) {
            return back()->with('error', 'Hareketi olan cari silinemez. Bunun yerine pasif yapabilirsiniz.');
        }

        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', "{$account->name} carisi silindi.");
    }
}
