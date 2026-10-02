// Mobilde yan menüyü aç/kapat
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-toggle]');
    if (!toggle) return;

    document.getElementById('sidebar')?.classList.toggle('-translate-x-full');
    document.getElementById('sidebar-backdrop')?.classList.toggle('hidden');
});

// <form data-confirm="Emin misiniz?"> gönderilmeden önce onay ister
// <form data-loading-text="…"> gönderilince buton kilitlenir (çift tıklamayı önler)
document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = form.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
        return;
    }

    if (form.dataset?.loadingText) {
        const button = form.querySelector('button');
        if (button) {
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-wait');
            button.lastElementChild.textContent = form.dataset.loadingText;
        }
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

// Hızlı cari ekleme: formdan çıkmadan yeni cari oluşturur, listeye ekleyip seçer
document.querySelectorAll('[data-quick-account]').forEach((box) => {
    const panel = box.querySelector('[data-quick-account-panel]');
    const nameInput = box.querySelector('[data-quick-account-name]');
    const phoneInput = box.querySelector('[data-quick-account-phone]');
    const saveButton = box.querySelector('[data-quick-account-save]');
    const error = box.querySelector('[data-quick-account-error]');
    const success = box.querySelector('[data-quick-account-success]');
    const select = box.querySelector('select');
    const token = box.closest('form')?.querySelector('input[name="_token"]')?.value;

    const showError = (message) => {
        error.textContent = message;
        error.classList.remove('hidden');
    };

    box.querySelector('[data-quick-account-toggle]').addEventListener('click', () => {
        panel.classList.toggle('hidden');
        success.classList.add('hidden');
        if (!panel.classList.contains('hidden')) nameInput.focus();
    });

    const save = async () => {
        error.classList.add('hidden');

        if (nameInput.value.trim() === '') {
            showError('Cari adını yazın.');
            nameInput.focus();
            return;
        }

        saveButton.disabled = true;

        try {
            const response = await fetch(box.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ name: nameInput.value.trim(), phone: phoneInput.value.trim() }),
            });
            const data = await response.json();

            if (!response.ok) {
                showError(Object.values(data.errors ?? {})[0]?.[0] ?? 'Cari eklenemedi.');
                return;
            }

            select.add(new Option(`${data.name} (${data.code})`, data.id, true, true));
            select.dispatchEvent(new Event('change', { bubbles: true }));

            nameInput.value = '';
            phoneInput.value = '';
            panel.classList.add('hidden');
            success.textContent = `${data.name} carisi eklendi ve seçildi.`;
            success.classList.remove('hidden');
        } catch {
            showError('Bağlantı hatası, tekrar deneyin.');
        } finally {
            saveButton.disabled = false;
        }
    };

    saveButton.addEventListener('click', save);

    // Enter tuşu ana formu göndermesin, cariyi eklesin
    [nameInput, phoneInput].forEach((input) =>
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                save();
            }
        }),
    );
});

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
