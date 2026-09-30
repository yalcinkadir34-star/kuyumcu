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

const formatNumber = (number, decimals = 3) =>
    Number.isFinite(number)
        ? number.toLocaleString('tr-TR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
        : '—';

// Atölye giriş formu: has karşılığı + firmanın son milyemini öner
const workOrderForm = document.querySelector('[data-workorder-form]');

if (workOrderForm) {
    const gram = workOrderForm.querySelector('[data-gram]');
    const purity = workOrderForm.querySelector('[data-purity]');
    const account = workOrderForm.querySelector('[data-account]');
    const hint = workOrderForm.querySelector('[data-purity-hint]');
    const lastPurities = JSON.parse(workOrderForm.dataset.lastPurities || '{}');

    const update = () => {
        const has = parseNumber(gram.value) * parsePurity(purity.value);
        workOrderForm.querySelector('[data-has-out]').textContent = has > 0 ? formatNumber(has) : '—';
    };

    const suggest = () => {
        const last = lastPurities[account.value];
        hint.textContent = last ? `Bu firmanın son milyemi: ${last}` : '';
        if (last && purity.value === '') {
            purity.value = last;
            update();
        }
    };

    gram.addEventListener('input', update);
    purity.addEventListener('input', update);
    account.addEventListener('change', suggest);
    suggest();
    update();
}

// Atölye çıkış formu: çıkan has, kalacak miktar ve işçilik önizlemesi
const deliverForm = document.querySelector('[data-deliver-form]');

if (deliverForm) {
    const remaining = Number(deliverForm.dataset.remaining);
    const purity = Number(deliverForm.dataset.purity);
    const out = deliverForm.querySelector('[data-gross-out]');
    const rate = deliverForm.querySelector('[data-labor-rate]');
    const currency = deliverForm.querySelector('[data-labor-currency]');
    const set = (selector, text) => (deliverForm.querySelector(selector).textContent = text);

    const update = () => {
        const basis = deliverForm.querySelector('[data-labor-basis]:checked')?.value ?? 'gram';
        const option = currency.selectedOptions[0];
        const decimals = Number(option.dataset.decimals);
        const gramOut = parseNumber(out.value);

        set('[data-labor-label]', basis === 'gram' ? 'Gram başı işçilik' : 'Toplam işçilik');

        if (gramOut > 0) {
            set('[data-preview-has]', formatNumber(gramOut * purity));
            set('[data-preview-remaining]', formatNumber(remaining - gramOut));
        } else {
            set('[data-preview-has]', '—');
            set('[data-preview-remaining]', formatNumber(remaining));
        }

        const rateValue = parseNumber(rate.value);
        const labor = basis === 'gram' ? rateValue * gramOut : rateValue;
        set('[data-preview-labor]', labor >= 0 ? `${formatNumber(labor, decimals)} ${option.dataset.symbol}` : '—');
    };

    deliverForm.addEventListener('input', update);
    deliverForm.addEventListener('change', update);
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
