<?php

namespace App\Http\Controllers;

use App\Enums\CashRegisterType;
use App\Enums\TransactionType;
use App\Http\Requests\CashRegisterRequest;
use App\Models\CashRegister;
use App\Models\Currency;
use App\Models\Transaction;
use App\Support\Balances;
use App\Support\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function index(): View
    {
        $registers = CashRegister::query()->orderByDesc('is_active')->orderBy('name')->get();

        return view('cash-registers.index', [
            'registers' => $registers,
            'balances' => Balances::forCashRegisters($registers->pluck('id')->all()),
            'currencies' => Currency::activeList(),
        ]);
    }

    public function create(): View
    {
        return view('cash-registers.create', [
            'register' => new CashRegister(['type' => CashRegisterType::Nakit, 'is_active' => true]),
            'types' => CashRegisterType::cases(),
        ]);
    }

    public function store(CashRegisterRequest $request): RedirectResponse
    {
        $register = CashRegister::create($request->validated());

        return redirect()->route('cash-registers.show', $register)
            ->with('success', "{$register->name} oluşturuldu.");
    }

    public function show(Request $request, CashRegister $cashRegister): View
    {
        $filters = $request->validate([
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
        ]);

        $ledger = Ledger::build(
            Transaction::query()->where('cash_register_id', $cashRegister->id),
            'cash_direction',
            $filters['baslangic'] ?? null,
            $filters['bitis'] ?? null,
        );

        return view('cash-registers.show', [
            'register' => $cashRegister,
            'balances' => $cashRegister->balances(),
            'ledger' => $ledger,
            'currencies' => Currency::query()->orderBy('sort')->get()->keyBy('id'),
            'quickTypes' => [TransactionType::Tahsilat, TransactionType::Odeme, TransactionType::KasaGiris, TransactionType::KasaCikis],
        ]);
    }

    public function edit(CashRegister $cashRegister): View
    {
        return view('cash-registers.edit', [
            'register' => $cashRegister,
            'types' => CashRegisterType::cases(),
        ]);
    }

    public function update(CashRegisterRequest $request, CashRegister $cashRegister): RedirectResponse
    {
        $cashRegister->update($request->validated());

        return redirect()->route('cash-registers.show', $cashRegister)
            ->with('success', 'Kasa bilgileri güncellendi.');
    }

    public function destroy(Request $request, CashRegister $cashRegister): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($cashRegister->transactions()->exists()) {
            return back()->with('error', 'Hareketi olan kasa silinemez. Bunun yerine pasif yapabilirsiniz.');
        }

        $cashRegister->delete();

        return redirect()->route('cash-registers.index')
            ->with('success', "{$cashRegister->name} silindi.");
    }
}
