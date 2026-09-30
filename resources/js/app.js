// Mobilde yan menüyü aç/kapat
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-toggle]');
    if (!toggle) return;

    document.getElementById('sidebar')?.classList.toggle('-translate-x-full');
    document.getElementById('sidebar-backdrop')?.classList.toggle('hidden');
});

// <form data-confirm="Emin misiniz?"> gönderilmeden önce onay ister
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// ---- Atölye hesap önizlemeleri (kesin hesap sunucuda yapılır) ----

// "1.250,5" / "1250.5" / "585" → sayı
const parseNumber = (value) => {
    let text = String(value ?? '').replace(/\s/g, '');
    if (text === '') return NaN;
    if (text.includes(',')) text = text.replace(/\./g, '').replace(',', '.');
    return Number(text);
};

const parsePurity = (value) => {
    const number = parseNumber(value);
    return number > 1 && number <= 1000 ? number / 1000 : number;
};

// İşçilik milyemi: "0,040" → 0.04, "40" → 0.04 (binde), boş → 0
const parseLaborPurity = (value) => {
    if (String(value ?? '').trim() === '') return 0;
    const number = parseNumber(value);
    return number >= 1 ? number / 1000 : number;
};

const formatNumber = (number, decimals = 3) =>
    Number.isFinite(number)
        ? number.toLocaleString('tr-TR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
        : '—';

// Has hesabı: küsurat atılır (sunucudaki Workshop::hasMilli ile aynı), 15,61875 → 15,618
const hasOf = (gram, purity) => Math.trunc(Math.round(gram * purity * 1e7) / 1e4) / 1000;

// Atölye giriş formu: has = gram × (ayar + giriş işçiliği); firmanın son değerlerini öner
const workOrderForm = document.querySelector('[data-workorder-form]');

if (workOrderForm) {
    const gram = workOrderForm.querySelector('[data-gram]');
    const purity = workOrderForm.querySelector('[data-purity]');
    const labor = workOrderForm.querySelector('[data-labor-in]');
    const account = workOrderForm.querySelector('[data-account]');
    const hint = workOrderForm.querySelector('[data-purity-hint]');
    const lastPurities = JSON.parse(workOrderForm.dataset.lastPurities || '{}');

    const update = () => {
        const total = parsePurity(purity.value) + parseLaborPurity(labor.value);
        const has = hasOf(parseNumber(gram.value), total);
        workOrderForm.querySelector('[data-in-purity]').textContent = total > 0 ? formatNumber(total) : '—';
        workOrderForm.querySelector('[data-has-out]').textContent = has > 0 ? formatNumber(has) : '—';
    };

    const suggest = () => {
        const last = lastPurities[account.value];
        hint.textContent = last ? `Bu firmanın son girişi: ayar ${last.purity}, işçilik ${last.labor}` : '';
        if (last && purity.value === '') purity.value = last.purity;
        if (last && labor.value === '') labor.value = last.labor;
        update();
    };

    workOrderForm.addEventListener('input', update);
    account.addEventListener('change', suggest);
    suggest();
}

// Atölye çıkış formu: has = gram × (ayar + çıkış işçiliği), kalacak miktar
const deliverForm = document.querySelector('[data-deliver-form]');

if (deliverForm) {
    const remaining = Number(deliverForm.dataset.remaining);
    const purity = Number(deliverForm.dataset.purity);
    const out = deliverForm.querySelector('[data-gross-out]');
    const labor = deliverForm.querySelector('[data-labor-purity]');
    const set = (selector, text) => (deliverForm.querySelector(selector).textContent = text);

    const update = () => {
        const gramOut = parseNumber(out.value);
        const total = purity + parseLaborPurity(labor.value);

        set('[data-out-purity]', formatNumber(total));

        if (gramOut > 0) {
            set('[data-preview-has]', formatNumber(hasOf(gramOut, total)));
            set('[data-preview-remaining]', formatNumber(remaining - gramOut));
        } else {
            set('[data-preview-has]', '—');
            set('[data-preview-remaining]', formatNumber(remaining));
        }
    };

    deliverForm.addEventListener('input', update);
    update();
}

// Hareket formu: seçilen türe göre cari/kasa alanlarını göster, gizle
const typeSelect = document.querySelector('[data-transaction-type]');

if (typeSelect) {
    const update = () => {
        const option = typeSelect.selectedOptions[0];
        const needsAccount = option.dataset.account === '1';
        const needsRegister = option.dataset.register === '1';

        document.querySelector('[data-field="account"]')?.classList.toggle('hidden', !needsAccount);
        document.querySelector('[data-field="register"]')?.classList.toggle('hidden', !needsRegister);

        const hint = document.querySelector('[data-type-hint]');
        if (hint) hint.textContent = option.dataset.hint ?? '';
    };

    typeSelect.addEventListener('change', update);
    update();
}
