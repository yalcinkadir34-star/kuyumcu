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

// <a data-print-receipt href="…/fis">: fişi yeni sekme açmadan, gizli bir çerçevede yükleyip yazdırır.
// Chrome "--kiosk-printing" ile açıldıysa önizleme çıkmaz, doğrudan varsayılan yazıcıya (fiş yazıcısı) basar.
// İki nüsha (müşteri, atölye) iki ayrı yazdırma işi olarak gider: fiş yazıcısı her işin sonunda kağıdı keser.
const RECEIPT_COPIES = ['musteri', 'atolye'];

const printInFrame = (url) => new Promise((resolve) => {
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    frame.addEventListener('load', () => {
        frame.contentWindow.focus();
        frame.contentWindow.print(); // yazdırma bitene kadar bekler
        setTimeout(() => {
            frame.remove();
            resolve();
        }, 500);
    }, { once: true });
    frame.src = url;
    document.body.append(frame);
});

let printing = false;

document.addEventListener('click', async (event) => {
    const link = event.target.closest('[data-print-receipt]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey) return;

    event.preventDefault();
    if (printing) return; // çift tıklamada fiş iki kez basılmasın
    printing = true;

    try {
        for (const copy of RECEIPT_COPIES) {
            const url = new URL(link.href, window.location.href);
            url.searchParams.set('nusha', copy);
            await printInFrame(url.toString());
        }
    } finally {
        printing = false;
    }
});

// Atölye girişi/çıkışı kaydedilince sesli uyarı (<div data-sound="giris|cikis">, components/flash).
// Ses dosyası yok, tarayıcıda üretilir. Giriş: yükselen iki nota · Çıkış: alçalan iki nota
const SOUNDS = {
    giris: [659, 988],
    cikis: [988, 659],
};

const playSound = (name) => {
    const notes = SOUNDS[name];
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!notes || !AudioContext) return;

    const context = new AudioContext();
    let played = false;
    const play = () => !played && context.state === 'running' && (played = true) && notes.forEach((frequency, index) => {
        const start = context.currentTime + index * 0.18;
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.5, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.35);

        oscillator.connect(gain).connect(context.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.4);
    });

    // Tarayıcı sesi engellediyse sayfadaki ilk tıklamada çalar
    play();
    context.resume().then(play).catch(() => {});
    document.addEventListener('pointerdown', () => context.resume().then(play), { once: true });
};

const soundMarker = document.querySelector('[data-sound]');
if (soundMarker) playSound(soundMarker.dataset.sound);

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

// Has hesabı: küsurat atılır (sunucudaki Workshop::hasMilli ile aynı), 15,61875 → 15,618
const hasOf = (gram, purity) => Math.trunc(Math.round(gram * purity * 1e7) / 1e4) / 1000;

// Atölye giriş formu: has = gram × milyem
//  - Ürüne "14 ayar" gibi yazılınca milyem otomatik dolar (config/kuyumcu.php → ayar_milyem)
//  - Ayar yazılmamışsa firmanın son milyemi önerilir
//  - Milyem elle değiştirilirse otomatik doldurma artık ona dokunmaz
const workOrderForm = document.querySelector('[data-workorder-form]');

if (workOrderForm) {
    const gram = workOrderForm.querySelector('[data-gram]');
    const purity = workOrderForm.querySelector('[data-purity]');
    const product = workOrderForm.querySelector('[data-product]');
    const account = workOrderForm.querySelector('[data-account]');
    const hint = workOrderForm.querySelector('[data-purity-hint]');
    const lastPurities = JSON.parse(workOrderForm.dataset.lastPurities || '{}');
    const ayarMilyem = JSON.parse(workOrderForm.dataset.ayarMilyem || '{}');

    // Düzenleme ya da hatalı form dönüşünde mevcut değer korunur
    let purityTouched = purity.value.trim() !== '';

    // "14 ayar", "14ayar", "14 AYAR", "14k" → 14
    const ayarPattern = new RegExp(`(?:^|[^0-9])(${Object.keys(ayarMilyem).join('|')})\\s*(?:ayar|ayr|k)(?![a-zçğıöşü])`, 'i');
    const ayarOf = () => product.value.match(ayarPattern)?.[1] ?? null;

    const update = () => {
        const has = hasOf(parseNumber(gram.value), parsePurity(purity.value));
        workOrderForm.querySelector('[data-has-out]').textContent = has > 0 ? formatNumber(has) : '—';
    };

    const fill = () => {
        const ayar = ayarOf();
        const last = lastPurities[account.value];

        // Seçili ayar butonunu vurgula
        workOrderForm.querySelectorAll('[data-ayar-pick]').forEach((button) => {
            button.toggleAttribute('data-active', button.dataset.ayarPick === ayar);
        });

        if (ayar) {
            hint.textContent = `${ayar} ayar → ${ayarMilyem[ayar]} (değiştirebilirsiniz)`;
            if (!purityTouched) purity.value = ayarMilyem[ayar];
        } else {
            hint.textContent = last ? `Bu firmanın son girişi: ${last}` : '';
            if (!purityTouched && last) purity.value = last;
        }

        update();
    };

    // Ayar butonu: ürüne "14 ayar" yazar (varsa eski ayarın yerine) ve milyemi o ayarınkiyle doldurur
    workOrderForm.querySelectorAll('[data-ayar-pick]').forEach((button) => {
        button.addEventListener('click', () => {
            const label = `${button.dataset.ayarPick} ayar`;
            const rest = product.value.replace(ayarPattern, ' ').replace(/\s+/g, ' ').trim();

            product.value = rest ? `${label} ${rest}` : label;
            purityTouched = false; // butonla seçim, elle girilen milyemin önüne geçer
            fill();
            gram.focus();
        });
    });

    purity.addEventListener('input', () => (purityTouched = purity.value.trim() !== ''));
    product.addEventListener('input', fill);
    account.addEventListener('change', fill);
    workOrderForm.addEventListener('input', update);
    fill();
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

// Atölyeden çıkış formu (çok satırlı): satır ekle/sil, satır ve toplam has önizlemesi,
// müşteri seçilince kalan gram, son çıkış milyemi ve çıkış türü önerisi
const deliveryForm = document.querySelector('[data-delivery-form]');

if (deliveryForm) {
    const info = JSON.parse(deliveryForm.dataset.info || '{}');
    const account = deliveryForm.querySelector('[data-delivery-account]');
    const linesBody = deliveryForm.querySelector('[data-lines]');
    const remainingText = deliveryForm.querySelector('[data-delivery-remaining]');
    const kinds = deliveryForm.querySelectorAll('[data-delivery-kind]');
    const set = (selector, text) => (deliveryForm.querySelector(selector).textContent = text);
    const kind = () => deliveryForm.querySelector('[data-delivery-kind]:checked')?.value ?? 'atolye';
    const rows = () => [...linesBody.querySelectorAll('[data-line]')];
    const field = (row, name) => row.querySelector(`[data-field="${name}"]`);

    // Satır numaraları ve form alan adları: lines[0][gross_out], lines[1][gross_out] ...
    const renumber = () => {
        rows().forEach((row, i) => {
            row.querySelector('[data-line-no]').textContent = i + 1;
            row.querySelectorAll('[data-field]').forEach((input) => (input.name = `lines[${i}][${input.dataset.field}]`));
        });
    };

    const update = () => {
        let totalGram = 0;
        let totalHas = 0;

        rows().forEach((row) => {
            const gram = parseNumber(field(row, 'gross_out').value);
            const purity = parsePurity(field(row, 'purity_out').value);
            const has = gram > 0 && purity > 0 ? hasOf(gram, purity) : NaN;

            row.querySelector('[data-line-has]').textContent = Number.isFinite(has) ? formatNumber(has) : '—';
            if (gram > 0) totalGram += gram;
            if (Number.isFinite(has)) totalHas += has;
        });

        const selected = info[account.value];
        const remaining = selected ? Number(selected.kalanSayi) : NaN;

        set('[data-total-gram]', totalGram > 0 ? formatNumber(totalGram) : '—');
        set('[data-preview-has]', totalHas > 0 ? formatNumber(totalHas) : '—');
        set('[data-preview-remaining]', Number.isFinite(remaining) ? formatNumber(remaining - totalGram) : '—');

        // Satışta ramat değişmez: "kalacak" kutusu gizlenir
        deliveryForm.querySelector('[data-preview-remaining-box]').classList.toggle('invisible', kind() !== 'atolye');
    };

    const addRow = () => {
        const last = rows().at(-1);
        const row = last.cloneNode(true);

        row.querySelectorAll('[data-field]').forEach((input) => {
            input.value = '';
            input.classList.remove('input-error');
        });
        row.querySelectorAll('.field-error').forEach((error) => error.remove());

        // Milyem: önceki satırınki (aynı işçilik sık kullanılır), yoksa müşterinin son milyemi
        field(row, 'purity_out').value = field(last, 'purity_out').value || info[account.value]?.sonMilyem || '';

        linesBody.appendChild(row);
        renumber();
        update();
        field(row, 'product').focus();
    };

    linesBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-line-remove]');
        if (!button) return;

        const row = button.closest('[data-line]');

        if (rows().length === 1) {
            row.querySelectorAll('[data-field]').forEach((input) => (input.value = ''));
        } else {
            row.remove();
        }

        renumber();
        update();
    });

    deliveryForm.querySelector('[data-line-add]').addEventListener('click', addRow);

    const onAccount = (event) => {
        const selected = info[account.value];
        remainingText.textContent = selected ? `Atölyede kalan ürünü: ${selected.kalan}` : '';

        // Boş milyem kutularına müşterinin son çıkış milyemi
        if (selected?.sonMilyem) {
            rows().forEach((row) => {
                if (field(row, 'purity_out').value === '') field(row, 'purity_out').value = selected.sonMilyem;
            });
        }

        // Müşteri değiştirilince türü öner: atölyede ürünü yoksa satış
        if (event && selected) {
            const target = Number(selected.kalanSayi) > 0 ? 'atolye' : 'satis';
            kinds.forEach((radio) => (radio.checked = radio.value === target));
        }

        update();
    };

    deliveryForm.addEventListener('input', update);
    deliveryForm.addEventListener('change', update);
    account.addEventListener('change', onAccount);
    onAccount();
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
