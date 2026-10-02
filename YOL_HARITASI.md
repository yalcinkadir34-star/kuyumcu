# Kuyumcu Atölye Uygulaması: Yol Haritası

> Bu dosya projenin **hafızasıdır**. Yapılan her geliştirme, alınan her karar ve
> sıradaki işler burada tutulur. Bir şey hatırlanmak istendiğinde önce bu dosya okunur.
> Her geliştirmeden sonra güncellenir ve commit edilir.

**Son güncelleme:** 30.09.2026
**Mevcut sürüm:** 0.4: Yedekleme (buton + günde 3 otomatik + Google Drive)

---

## 1. Proje Özeti

Web tabanlı kuyumcu atölyesi yönetim uygulaması. Amaç:

- Atölyenin kendi hesaplarını takip etmek (kasa, has altın, işçilik vb.)
- Kuyumcu atölyesindeki iş/hesap hareketlerini takip etmek
- Müşterilerin cari hesaplarını tutmak

> Detaylı özellikler kullanıcı tarafından ileride aktarılacak (bkz. Bölüm 5).

---

## 2. Teknik Altyapı (Kararlar)

| Konu | Karar | Not |
|---|---|---|
| Dil / Framework | **PHP 8.3 + Laravel 13** | Laravel 13.34 kuruldu |
| Veritabanı | **MySQL 8.4** | Veritabanı adı: `kuyumcu`, karakter seti `utf8mb4_turkish_ci` (Türkçe sıralama doğru çalışsın diye) |
| Arayüz | **Blade + Tailwind CSS 4** (Vite ile derleniyor) | Ek JS framework yok, sade tutuldu |
| Yerel ortam | **Laragon 8.7** (Windows) | PHP, MySQL, Composer, Node 22 içinde geliyor |
| Proje klasörü | `C:\laragon\www\kuyumcu` | Laragon otomatik olarak `http://kuyumcu.test` adresini açar |
| Versiyon kontrol | **Git + GitHub** | Her geliştirme ayrı commit ve GitHub'a push |
| GitHub deposu | [yalcinkadir34-star/kuyumcu](https://github.com/yalcinkadir34-star/kuyumcu) (Private) | Dal: `main` |
| Commit yazarı | Kadir &lt;yalcinkadir34@gmail.com&gt; | 30.09.2026'dan itibaren (ilk 2 commit eski e-postayla) |
| Saat dilimi | `Europe/Istanbul` | `.env` → `APP_TIMEZONE` |
| Dil | Türkçe (`APP_LOCALE=tr`) | |
| Oturum / Cache / Kuyruk | MySQL (database driver) | Ek servis (Redis vb.) gerekmez |

### Derlenmiş CSS/JS (`public/build`) neden git'te?
Normalde Laravel bu klasörü git'e koymaz, sunucuda `npm run build` yapılır. Paylaşımlı
hosting'lerde Node.js olmayabileceği için **derlenmiş dosyalar commit ediliyor**. Böylece
sunucuda sadece `git pull` + `composer install` yeterli olur.
➜ **Kural:** CSS/Blade/JS değiştiğinde commit öncesi `npm run build` çalıştırılmalı.

---

## 3. Yerel Geliştirme Ortamı

### Günlük çalıştırma (02.10.2026'dan itibaren otomatik)
- **Windows'a giriş yapınca sistem kendiliğinden açılır.** "Kuyumcu Sistem" görevi MySQL'i ve siteyi başlatır
  → **http://localhost:8000** (`scripts/sistemi-baslat.ps1`, görev kurulumu: `scripts/windows-baslangic-gorevi.ps1`)
- Elle başlatmak gerekirse: `powershell -ExecutionPolicy Bypass -File scripts\sistemi-baslat.ps1`
- Alternatif: Laragon → **Start All** → `http://kuyumcu.test` (MySQL zaten açıksa Laragon uyarı verebilir, zararsız)
- Veritabanını görmek için: Laragon → **Database** (HeidiSQL açılır, kullanıcı `root`, şifre boş)

### Windows görevleri (Görev Zamanlayıcı)
| Görev | Ne zaman | Ne yapar |
|---|---|---|
| **Kuyumcu Sistem** | Windows girişinde | MySQL + site (localhost:8000) başlatır, açıksa dokunmaz |
| **Kuyumcu Yedek** | Her gün 10:00, 15:00, 20:00 (kaçarsa açılınca) | `php artisan yedek:al` |
- İkisi de pencere açmadan (`conhost --headless`) çalışır
- Kaldırmak: `Unregister-ScheduledTask -TaskName "Kuyumcu Sistem" -Confirm:$false` (yedek için "Kuyumcu Yedek")

### Yönetici hesabı
- Kullanıcı adı ve şifre `.env` dosyasında: `ADMIN_USERNAME` / `ADMIN_PASSWORD`
- Hesap `php artisan db:seed` ile oluşturulur (varsa dokunmaz)
- `.env` git'e **gönderilmez**, şifre sadece yerelde durur

### Sık kullanılan komutlar
| Komut | Ne işe yarar |
|---|---|
| `php artisan migrate` | Yeni veritabanı tablolarını oluşturur |
| `php artisan migrate:fresh --seed` | ⚠️ Veritabanını **sıfırlar**, yöneticiyi yeniden oluşturur (sadece geliştirmede) |
| `php artisan test` | Otomatik testleri çalıştırır |
| `npm run build` | CSS/JS'i derler (commit öncesi) |
| `npm run dev` | Geliştirirken CSS/JS'i canlı derler |

---

## 4. Yapılanlar (Değişiklik Günlüğü)

### ✅ v0.1: Altyapı, Giriş, Ana Sayfa (29.09.2026)

**Ortam kurulumu**
- Laragon 8.7 (PHP 8.3.33, MySQL 8.4.3, Composer 2.10, Node 22) ve Git 2.55 winget ile kuruldu
- PHP `zip` eklentisi açıldı (Composer hızı için)
- Laravel 13 projesi `C:\laragon\www\kuyumcu` içinde oluşturuldu
- MySQL'de `kuyumcu` veritabanı açıldı (`utf8mb4_turkish_ci`)

**Veritabanı**
- `users` tablosu atölyeye uyarlandı:
  - `username` (benzersiz): giriş e-posta yerine **kullanıcı adı** ile yapılıyor
  - `email`: isteğe bağlı
  - `role`: `admin` (Yönetici) / `personel` (Personel)
  - `is_active`: pasif kullanıcı giriş yapamaz, oturumu açıksa atılır
  - `last_login_at`: son giriş zamanı
- Laravel'in standart tabloları: `sessions`, `cache`, `jobs`, `password_reset_tokens`
- `DatabaseSeeder`: `.env`'deki bilgilerle ilk yönetici hesabını oluşturur

**Giriş sistemi** (`/giris`)
- Kullanıcı adı + şifre, "Beni hatırla"
- Kaba kuvvet koruması: 1 dakikada 5 hatalı denemeden sonra geçici engel
- Pasif kullanıcı kontrolü (`EnsureUserIsActive` middleware, `active` takma adı)
- Çıkış (`POST /cikis`)
- Giriş yapmamış kullanıcı her sayfada `/giris`'e yönlendirilir

**Ana sayfa / Dashboard** (`/`)
- Yan menü (mobilde açılır/kapanır): Ana Sayfa, Cariler, Kasa, Atölye, Stok, Raporlar, Ayarlar
  - Henüz yapılmayan modüller "Yakında" etiketiyle pasif görünüyor
- Özet kartları (Toplam Cari, Kasa TL, Has Altın Bakiyesi, Bugünkü İşlemler): şimdilik boş, modüller gelince dolacak
- "Son Hareketler" alanı (boş durum) ve oturum bilgisi kartı
- Türkçe tarih, altın/koyu renk teması

**Testler** (`tests/Feature/AuthTest.php`: 9 test, hepsi geçiyor)
- Yönlendirme, başarılı/başarısız giriş, pasif kullanıcı, deneme sınırı, çıkış, dashboard görüntüleme

**Önemli dosyalar**
| Dosya | Görevi |
|---|---|
| `app/Http/Controllers/Auth/LoginController.php` | Giriş / çıkış |
| `app/Http/Controllers/DashboardController.php` | Ana sayfa verileri |
| `app/Http/Middleware/EnsureUserIsActive.php` | Pasif kullanıcıyı çıkarır |
| `app/Models/User.php` | Kullanıcı modeli, roller |
| `config/kuyumcu.php` | Uygulamaya özel ayarlar (ilk yönetici) |
| `routes/web.php` | Adresler |
| `resources/views/layouts/app.blade.php` | Ana şablon + yan menü |
| `resources/views/auth/login.blade.php` | Giriş sayfası |
| `resources/views/dashboard.blade.php` | Ana sayfa |
| `resources/css/app.css` | Tema renkleri (`gold-*` tonları) |

### ✅ GitHub bağlantısı (30.09.2026)
- Private depo oluşturuldu: `https://github.com/yalcinkadir34-star/kuyumcu`
- GitHub'ın otomatik README'si yerel README ile birleştirildi (yerel sürüm korundu)
- İlk push kullanıcı tarafından Git Bash'ten yapıldı. GitHub girişi Windows'a (Git Credential Manager) kaydedildi, sonraki push'lar otomatik
- Commit yazarı "Kadir &lt;yalcinkadir34@gmail.com&gt;" olarak ayarlandı (sadece bu projede)

### ✅ v0.2: Cari, Kasa ve Genel Bilanço (30.09.2026)

**Temel tasarım kararı: çok birimli hesap**
Kuyumculukta hesap sadece TL değil. Her cari ve kasa **her birim için ayrı bakiye** tutar.
Birimler `currencies` tablosunda: **TRY** (2 ondalık), **USD**, **EUR**, **HAS** (has altın, gram, 3 ondalık).
Yeni birim (ör. 22 ayar, gümüş) eklemek için tabloya satır eklemek yeterli.

**Hareket (işlem) mantığı**: tek tablo `transactions`
- Tutar her zaman pozitif. Cariye ve kasaya etkisi `account_direction` / `cash_direction` alanlarında (+1 / −1 / 0)
- Bakiye = `SUM(tutar × yön)`. Yönler hareket türünden **otomatik** hesaplanır (`app/Enums/TransactionType.php`)

| İşlem türü | Cari | Kasa | Örnek |
|---|---|---|---|
| Tahsilat | alacaklanır (borcu azalır) | + giriş | Müşteri borcunu ödedi |
| Ödeme | borçlanır | − çıkış | Tedarikçiye ödeme yaptık |
| Cari Borçlandırma | borçlanır | — | Veresiye satış, işçilik bedeli |
| Cari Alacaklandırma | alacaklanır | — | Cariden emanet/alış |
| Kasa Giriş | — | + giriş | Cari dışı gelir |
| Kasa Çıkış | — | − çıkış | Kira, fatura, gider |

**Bakiye yorumu (cari):** pozitif = **B (Borçlu)**, cari bize borçlu. Negatif = **A (Alacaklı)**, biz cariye borçluyuz.

**Hassasiyet:** Veritabanında `decimal(18,3)`. PHP'de hesaplar "binde bir" birimli tam sayılarla yapılıyor
(`app/Support/Amount.php`), yani kuruş/miligram kayması yok.
**Tutar girişi:** Türk biçimi, `1.250,50` / `2,5` / `12,345`. Belirsiz olan `1.500` reddedilir
(bin beş yüz mü, bir buçuk mu anlaşılmaz). Birimin ondalık sınırı kontrol edilir (TL'de en fazla 2 hane).

**Ekranlar**
- **Cariler** (`/cariler`): arama, tür/durum filtresi, her birim için bakiye sütunu (B/A işaretli)
- **Cari detay**: birim bazında bakiye kartları, hızlı işlem butonları, **cari ekstre**
  (tarih aralığı, devreden bakiye, yürüyen bakiye, dönem sonu bakiyesi)
- **Kasalar** (`/kasalar`): nakit/banka/POS, birim bazında mevcut, toplam satırı, kasa hareketleri ekstresi
  - Varsayılan olarak **Merkez Kasa** oluşturuldu
- **Hareketler** (`/hareketler`): tüm işlemler, filtreleme, düzenleme. İşlem formu türe göre cari/kasa alanlarını gösterip gizliyor
  - "Kaydet ve Yeni" ile art arda giriş yapılabiliyor
- **Ana sayfa:** aktif cari sayısı, kasa TL ve has mevcudu, bugünkü işlem sayısı, **Genel Bilanço**, son hareketler
  - **Genel Bilanço:** her birim için Kasa + Alacaklarımız − Borçlarımız = **Net Durum**

**Yetkiler**
- Cari/kasa/hareket ekleme ve düzenleme: tüm kullanıcılar
- Silme: sadece **Yönetici**. Hareketi olan cari/kasa silinemez, pasif yapılır

**Teknik**
- Yeni tablolar: `currencies`, `accounts`, `cash_registers`, `transactions`
- Adresler Türkçe: `/cariler/yeni`, `/cariler/5/duzenle` (`AppServiceProvider` → `resourceVerbs`)
- Türkçe doğrulama mesajları: `lang/tr/validation.php`, sayfalama: `lang/tr.json`
- Ortak CSS sınıfları (`.card`, `.btn`, `.input`, `.table`, `.badge`): `resources/css/app.css`
- Testler: `tests/Unit/AmountTest.php`, `tests/Feature/CariKasaTest.php` (toplam 38 test, hepsi geçiyor)

| Dosya | Görevi |
|---|---|
| `app/Enums/TransactionType.php` | İşlem türleri ve cari/kasa etkileri |
| `app/Support/Amount.php` | Tutar okuma/biçimlendirme, hassas hesap |
| `app/Support/Balances.php` | Cari/kasa bakiyeleri ve genel bilanço sorguları |
| `app/Support/Ledger.php` | Ekstre (devir + yürüyen bakiye) |
| `resources/views/partials/ledger.blade.php` | Cari ve kasa ekstresi tablosu |

### ✅ v0.3: Atölye / Fason İşçilik (30.09.2026)

**Kullanıcının anlattığı iş akışı**
- Firmalar döküm ürünlerini atölyeye getirir, atölye fason işçilik (cila vb.) yapar
- Ürün tartılır, **milyem ürüne ve müşteriye göre değişir** ve elle girilir (0,585 / 0,595 …)
  - Örnek: 200 gr × 0,585 = **117 gr has**
- İşlem (cila) sırasında ürün incelir ve **fire** verir. Ortalama %20 civarında ama kesin değer
  teslimde **tartılarak** girilir. Örnek: 200 → 160 gr, **40 gr fire**
- Ürün, fire vermiş haliyle ve belli bir **işçilik** ücretiyle firmaya geri gönderilir
- Fire tozları **aylık rafine/remat** edilerek bir kısmı geri kazanılır (henüz yapılmadı, bkz. Bölüm 5)

**Yapılanlar**
- **Atölyeye Giriş** (`/atolye/yeni`): firma, ürün, brüt gram, milyem, giriş tarihi
  - Has karşılığı formda canlı hesaplanır
  - Milyem `0,585`, `0.585` veya `585` olarak yazılabilir. Firmanın son milyemi otomatik önerilir
  - Fiş no otomatik: A00001, A00002…
- **Teslim (Çıkış)**: tartıdaki net gram, çıkış tarihi, işçilik
  - Fire gram, fire oranı (%), fire has ve çıkış has otomatik hesaplanır (canlı önizleme)
  - İşçilik iki şekilde girilebilir: **gram başı** (× çıkış gramı) veya **toplam tutar**
  - İşçilik birimi seçilebilir: TL / USD / EUR / **Has altın**
  - Firmanın son işçilik ayarı (tip, ücret, birim) teslim formuna otomatik gelir
  - Teslimde işçilik firmanın carisine **otomatik "Cari Borçlandırma"** olarak işlenir (belge no = fiş no)
  - Teslim bilgileri sonradan düzeltilebilir, cari kaydı da güncellenir
  - Yönetici teslimi geri alabilir, bu durumda işçilik cari kaydı silinir
- **Atölye listesi** (`/atolye`): Atölyede / Teslim edilen / Tümü sekmeleri, firma, tarih ve arama filtresi
  - Özet kartları: atölyedeki fiş sayısı ve gramı, atölyedeki has (emanet), bu ayın fire gramı, has karşılığı ve ortalama oranı
- Giriş bilgileri teslimden sonra düzeltilirse fire yeniden hesaplanır

**~~Varsayım: altın emanet, cariye has yazılmaz~~** → v0.3.1'de kullanıcı düzeltti, aşağıya bakın.

### ✅ v0.3.1: Atölye has hareketleri cariye işleniyor (01.10.2026)

**Kullanıcı geri bildirimi:** "Mustafa'nın carisine 26,25 gr × 0,595 ile giriş yaptım ama ona olan borcum artmadı, artması lazım."

**Cariye etkiler (hepsi otomatik, fişe bağlı):**
| Olay | Cari kaydı | Birim | Örnek (200 gr × 0,585, çıkış 160 gr) |
|---|---|---|---|
| Atölyeye giriş | Cari **Alacak** (firmaya borçlanırız) | HAS | 117,000 gr |
| Teslim | Cari **Borç** (geri verdiğimiz) | HAS | 93,600 gr |
| Fire, **firma üstlenirse** | Cari **Borç** | HAS | 23,400 gr, fiş için has bakiyesi 0 olur |
| Fire, **atölye üstlenirse** | kayıt yok | | 23,400 gr firmaya borcumuz kalır |
| İşçilik | Cari **Borç** | TL/döviz/HAS | 2.400 ₺ |

- Teslim formuna **"Fireyi kim üstleniyor? Firma / Atölye"** seçimi eklendi. Firmanın son seçimi hatırlanır, varsayılan: Firma
- Giriş düzenlenirse (gram, milyem, firma) cari kayıtları da güncellenir. Fiş silinirse kayıtları da silinir
- Teslim geri alınırsa teslim, fire ve işçilik kayıtları silinir, giriş kaydı kalır
- Fişe bağlı cari kayıtları **Hareketler ekranından düzenlenemez veya silinemez**, kullanıcı fişe yönlendirilir (tutarsızlık olmasın)
- **Genel bilançoya "Atölyede" sütunu** eklendi: atölyedeki ürünlerin has karşılığı varlık olarak sayılır
  (firmaya olan has borcuyla dengelenir). Net = Kasa + Atölyede + Alacaklar − Borçlar
- Migration, daha önce girilmiş fişleri otomatik olarak cariye işledi (Mustafa / A00001 → 15,619 gr has alacak)
- Yeni alanlar: `work_orders.in_transaction_id`, `out_transaction_id`, `fire_transaction_id`, `fire_bearer`
- Testler: 68 test, hepsi geçiyor

**Teknik**
- Yeni tablo: `work_orders`. Model: `app/Models/WorkOrder.php` (`deliver()`, `undeliver()`)
- Hesaplar: `app/Support/Workshop.php`. Milyem "on binde bir" tam sayıyla, gram "binde bir" tam sayıyla hesaplanıyor, yuvarlama hatası yok
- Veritabanında milyem `decimal(5,4)`, gramlar `decimal(12,3)`
- Testler: `tests/Unit/WorkshopTest.php`, `tests/Feature/AtolyeTest.php` (toplam 61 test, hepsi geçiyor)

> ⚠️ v0.3 ve v0.3.1'deki tek seferlik "teslim", "fire kaydı" ve "fireyi kim üstleniyor" yapısı
> v0.3.2'de **kaldırıldı**. Güncel mantık aşağıda.

### ✅ v0.3.2: Parçalı çıkış, fire cariye işlenmiyor (01.10.2026)

**Kullanıcı geri bildirimi:** "Sadece yapılan işlemin giriş-çıkışını yapacağız, fire işlemini yapmayacağız.
26,25 giriş oldu, 6,97 çıkış oldu, aradaki kalan atölyede kalacak."

**Güncel atölye mantığı**
- Bir giriş fişinden **birden fazla çıkış** yapılabilir (`work_order_deliveries` tablosu)
- **Giriş:** has karşılığı → cari **alacak** (firmaya has borçlanırız)
- **Her çıkış:** çıkan gramın has karşılığı → cari **borç**, işçilik → cari **borç**
- **Atölyede kalan** = giriş − çıkışlar. Firmaya o kadar has borcumuz devam eder
- **Fire cariye hiç işlenmez.** İş bitince "Fişi Kapat" denirse kalan miktar **sadece raporda** fire olarak görünür
  - Kapalı fiş yönetici tarafından tekrar açılabilir
- Çıkış, atölyede kalandan fazla olamaz. Giriş gramı, yapılmış çıkışların altına düşürülemez
- Yönetici bir çıkışı silebilir, bağlı cari kayıtları da silinir
- Milyem veya firma değiştirilirse giriş ve tüm çıkışların cari kayıtları yeniden hesaplanır
- Çıkış formunda işçilik varsayılanları firmanın son çıkışından gelir
- Fişe bağlı cari kayıtları Hareketler ekranından değiştirilemez, fişe yönlendirilir

**Ekranlar**
- Fiş detayı: Giriş | Has karşılığı | Çıkan | **Atölyede kalan** (kapalıysa **Fire**), çıkışlar tablosu, yeni çıkış formu
  (canlı önizleme: çıkan has, kalacak gram, işçilik), fişi kapat
- Atölye listesi: Atölyede / Tamamlanan / Tümü. Sütunlar: giriş, milyem, has, çıkan, kalan (%)
- Özet kartları: atölyede kalan gram ve has, bu ayın çıkışları, bu ay kapanan fişlerin firesi
- Bilanço "Atölyede" sütunu: açık fişlerde kalan has

**Veri dönüşümü (migration 2026_10_01_000002)**
- Eski tek seferlik teslimler çıkış kaydına dönüştürüldü, **fire cari kayıtları silindi**
- Kalanı olan fişler tekrar "atölyede" durumuna alındı
- Mustafa / A00001: 26,250 gr giriş, 6,970 gr çıkış, 19,280 gr atölyede. Has bakiyesi 53,546 gr (biz borçluyuz)
- Migration öncesi yerel veritabanı yedeği: `storage/app/yedek/kuyumcu-2026-10-01-oncesi.sql` (git'e gitmez)

**Teknik**
- Yeni: `app/Models/WorkOrderDelivery.php`, `app/Support/LinkedTransaction.php` (bağlı cari kaydı oluştur/güncelle/sil)
- `work_orders`'tan teslim, fire ve işçilik sütunları kaldırıldı, `closed_at` eklendi. Durumlar: `atolyede` | `tamamlandi`
- Adresler: `POST /atolye/{id}/cikis`, `DELETE /atolye/{id}/cikis/{cikis}`, `POST|DELETE /atolye/{id}/kapat`
- Testler: 65 test, hepsi geçiyor

### ✅ v0.3.3: İşçilik milyem olarak, ayar milyemine eklenir (01.10.2026)

**Kullanıcı geri bildirimi:** "Ürün 26,25 ile girdi, 0,010 işçilikle aldık, yani 0,595 ile aldık.
6,97 teslim ettik, 0,040 işçilikle, yani 0,625 ile teslim ettik. Has borcumuz 57,49 olmalı, neden fark var?"

**Hata:** Çıkışta hem `6,97 × 0,595 = 4,147` has çıkışı hem de ayrıca `6,97 × 0,625 = 4,356` has işçilik
düşülmüştü, yani işçilik **iki kez** sayılmıştı. Sistem 53,546 gösteriyordu, doğrusu 57,693.

**Güncel formül** (işçilik ayrı bir kayıt değil, milyemin içinde):
- **Giriş has** = giriş gramı × (ayar milyemi + giriş işçiliği) → 26,25 × (0,585 + 0,010) = **15,619** → cari **alacak**
- **Çıkış has** = çıkış gramı × (ayar milyemi + çıkış işçiliği) → 6,97 × (0,585 + 0,040) = **4,356** → cari **borç**
- Mustafa: 46,430 + 15,619 − 4,356 = **57,693 gr has** borcumuz
  - v0.3.4'te küsurat atma kuralı gelince 57,692 oldu (ilk bakiye 46,43)
- **Atölyede kalan** gram, bilançoda **ayar milyemiyle** (işçiliksiz) saf has olarak sayılır: 19,28 × 0,585 = 11,279

**Değişenler**
- Giriş formu: **Ayar milyemi** + **Giriş işçiliği (milyem)** alanları, canlı "hesap milyemi" ve has
  - Firmanın son girişindeki ayar ve işçilik otomatik önerilir
- Çıkış formu: gram + **Çıkış işçiliği (milyem)**. TL/döviz işçilik ve gram başı/toplam seçimi **kaldırıldı**
  - Varsayılan işçilik: bu fişin ya da firmanın son çıkışındaki işçilik milyemi
- İşçilik milyemi `0,040`, `0,04` veya `40` (binde) olarak yazılabilir. **0,200 üstü reddedilir**
  (ör. "0,40" yazılırsa uyarı verir, büyük ihtimalle 0,040 kastedilmiştir)
- Yeni alanlar: `work_orders.labor_purity_in`, `work_order_deliveries.labor_purity`.
  Kaldırılanlar: çıkıştaki `labor_basis`, `labor_rate`, `labor_total`, `labor_currency_id`, `labor_transaction_id`
- Yeni komut: `php artisan atolye:yeniden-hesapla` (tüm fişlerin has ve cari kayıtlarını güncel formülle yeniden hesaplar)
- Yerel veri düzeltmesi: A00001 → ayar 0,585, giriş işçiliği 0,010, çıkış işçiliği 0,040 (kullanıcının verdiği bilgi), ardından yeniden hesap
- Yedek: `storage/app/yedek/kuyumcu-2026-10-01-iscilik-oncesi.sql`
- Testler: 68 test, hepsi geçiyor. Kullanıcının örneği `test_kullanicinin_ornegi_iscilik_milyemle_hesaplanir`

### ✅ v0.3.4: Has hesabında küsurat atılır (01.10.2026)

**Kullanıcının verdiği doğru hesap:**
```
26,25 × 0,595 = 15,618   (giriş, 10 milyem işçilikle)
 6,97 × 0,625 =  4,356   (çıkış, 40 milyem işçilikle)
İlk bakiye 46,43 → 46,43 + 15,618 − 4,356 = 57,692
```
(Kullanıcı önce ilk bakiyeyi 46,23 dedi, sonra "yanlış oldu, 46,43 olmalı" diye düzeltti.)

**Kural: has hesabında binde birden sonrası atılır (aşağı yuvarlama, kuyumcu usulü)**
- 26,25 × 0,595 = 15,61875 → **15,618** (önceden 15,619'a yuvarlanıyordu)
- `Workshop::hasMilli()` ve formlardaki canlı önizleme (JS `hasOf`) aynı kuralı kullanıyor
- Sadece gram × milyem (has) hesabı için geçerli. TL tutarlarında normal yuvarlama devam ediyor

**Veri düzeltmesi (yerel)**
- Mustafa'nın elle girilmiş ilk kaydı **46,430** olarak kaldı (kısa süre 46,230 yapıldı, kullanıcı düzeltince geri alındı)
- `atolye:yeniden-hesapla` ile A00001 girişi 15,619 → 15,618
- Sonuç: Mustafa has bakiyesi **57,692 gr** (biz borçluyuz) = 46,43 + 15,618 − 4,356 ✓
- Yedek: `storage/app/yedek/kuyumcu-2026-10-01-yuvarlama-oncesi.sql`
- Testler: 68 test, hepsi geçiyor

### ✅ v0.4: Yedekleme ve Google Drive (01.10.2026)

**Kullanıcı istekleri:** "Google Drive bağlayacağız, yedek alacağız", "yedekleme butonu da koy, tıklayarak
yedek alayım", "günde 3 kere de kendin yedek al".

**Nasıl çalışıyor**
- Yedek = **sadece veritabanı** (mysqldump → zip). Kod GitHub'da, `.env` gizli olduğu için yedeklenmez
- Paket: `spatie/laravel-backup` (v10). Yerel yedekler: `storage/app/private/kuyumcu-yedek/kuyumcu-YYYY-MM-DD-HH-MM-SS.zip`
- Yerel temizlik (`backup:clean`): 7 gün hepsi, 30 gün günlük, 8 hafta haftalık, 12 ay aylık
- **Google Drive:** her yedek Drive'daki **"Kuyumcu Yedekleri"** klasörüne yüklenir, en yeni **90** yedek tutulur (~30 gün)
  - Drive API doğrudan HTTP ile kullanılıyor (Google kütüphanesi yok): `app/Services/GoogleDrive.php`
  - Yetki `drive.file`: uygulama sadece kendi oluşturduğu dosyaları görür
  - Bağlantı (refresh token) `settings` tablosunda **şifreli** saklanır
  - Drive'a yüklenemezse yedek yine yerelde kalır, hata geçmişte görünür
- **Buton:** üst çubukta **"Yedek Al"** (her sayfada) ve Yedekleme sayfasında **"Şimdi Yedek Al"**. Çift tıklamaya karşı kilitli
- **Otomatik:** her gün **10:00, 15:00, 20:00** (`config/kuyumcu.php` → `backup.times`), komut: `php artisan yedek:al`
  - Laravel zamanlaması `routes/console.php` içinde. **Tetikleyici gerekiyor:**
    - Windows (yerel): `scripts/windows-yedek-gorevi.ps1` → Görev Zamanlayıcı'ya "Kuyumcu Yedek" görevi (pencere açmadan)
    - Sunucu: cron'a `* * * * * cd /proje && php artisan schedule:run >> /dev/null 2>&1`
- **Yedekleme sayfası** (`/yedekleme`, sadece yönetici): son yedek, otomatik yedek saatleri, Drive durumu
  (bağlı hesap, klasörü aç, bağlantıyı kaldır), kurulum adımları, yedek geçmişi (indirme)
- Yeni tablolar: `settings` (anahtar-değer ayarlar), `backup_logs` (yedek geçmişi)
- Yeni `admin` middleware'i: sadece yöneticinin erişebildiği sayfalar

**Google Drive kurulumu (bir kerelik, kullanıcı yapacak):** Yedekleme sayfasında adım adım yazıyor
1. Google Cloud Console → yeni proje → Google Drive API'yi etkinleştir
2. OAuth izin ekranı: External, **Publish app** (yayınlanmazsa bağlantı 7 günde kopar)
3. OAuth istemcisi (Web application), yönlendirme adresi: `http://localhost:8000/yedekleme/google/callback`
   - Google `.test` adreslerini kabul etmez. Yerelde bağlanma işlemi **localhost:8000** üzerinden yapılmalı
   - Sunucuya taşınınca sunucu adresiyle yeni bir yönlendirme adresi eklenmeli
4. Client ID ve secret `.env` dosyasına: `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`
5. Yedekleme sayfasında "Google Drive'a Bağlan"

**Windows'a özgü düzeltme:** Web isteğinden çalışan mysqldump "Can't create TCP/IP socket (10106)" hatası veriyordu.
Symfony Process ortam değişkenlerini `$_SERVER` ile süzdüğü için `SystemRoot` alt işleme geçmiyordu.
`BackupService::ensureWindowsEnvironment()` bunu düzeltiyor.

**.env yeni anahtarlar:** `DB_DUMP_PATH` (Laragon: `C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin`, Linux'ta boş),
`GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`

- Testler: `tests/Feature/YedeklemeTest.php` (Drive çağrıları sahte). Toplam 79 test, hepsi geçiyor

### ✅ Gerçek kullanıma geçiş (02.10.2026)
- Deneme verileri kullanıcının isteğiyle silindi (MUSTAFA carisi, 4 hareket, A00001/A00002 fişleri).
  Silmeden önceki yedek: `storage/app/yedek/kuyumcu-2026-10-02-mustafa-silinmeden-once.sql`
- **Bundan sonraki veriler gerçek.** Migration'larda veri dönüştürürken mutlaka önce yedek alınmalı
- "Kuyumcu Yedek" görevi kuruldu ve elle tetiklenerek denendi (otomatik yedek başarılı)
- "Kuyumcu Sistem" başlangıç görevi kuruldu: MySQL ve site kapalıyken görev ikisini de açtı (denendi)
- Henüz yapılmadı: Google Drive bağlantısı, şifre değiştirme ekranı

### ✅ v0.5: Müşteriye atölye çıkış fişi (02.10.2026)

**Kullanıcı isteği:** "Müşteriye bilgi amaçlı fiş vermek istiyoruz, atölyeden çıkan ürünlerde. Basit, anlaşılır, kolay olsun."

- Her çıkışın yanında **"Fiş"** butonu var. Yeni çıkış kaydedilince üstte **"Fişi Yazdır"** kutusu çıkar
- Fiş sayfası (`/atolye/{fiş}/cikis/{çıkış}/fis`) yeni sekmede açılır: **Yazdır** butonu ve boy seçimi
  - **Normal (A5)** ve **Fiş yazıcı (80 mm)** (`?boyut=80`). Yazdırırken tüm yazılar siyah basılır (termal yazıcı için)
- **Fiş içeriği:** firma adı, telefon, adres · fiş no (`A00001-1` = fiş no + çıkış sırası) · tarih · müşteri · ürün
  · **Giriş** (gram, milyem = ayar + işçilik, has) · **Çıkış** (gram, milyem, **has**) · bu çıkıştan sonra atölyede kalan
  · **Hesap durumu**: müşterinin güncel bakiyesi, müşteri gözünden ("Alacağınız" / "Borcunuz")
  · Teslim eden / Teslim alan imza alanı · "Bu fiş bilgi amaçlıdır."
- **Firma bilgileri** `.env` dosyasında: `FIRMA_ADI`, `FIRMA_TELEFON`, `FIRMA_ADRES` (`config/kuyumcu.php` → `firma`)
  - ⚠️ Kullanıcı kendi firma adını, telefonunu ve adresini vermeli (şimdilik "Kuyumcu Atölye")
- Testler: 82 test, hepsi geçiyor
- Önizleme notu: gerçek veriye dokunmamak için ayrı `kuyumcu_onizleme` veritabanı ve 8001 portu kullanıldı, sonra silindi.
  `artisan serve` alt sunucuya `DB_DATABASE` aktarmıyor, bu yüzden `php -S ... server.php` ve farklı `SESSION_COOKIE` gerekiyor

### ✅ v0.5.1: İşlemlerde saat:dakika:saniye (02.10.2026)

**Kullanıcı isteği:** "Cari ve atölye işlemlerinde tarihin yanında saat, dakika, saniye olsun. İşlem sırasını gün gün
değil saat-dakika-saniye sırasına göre görmek istiyorum."

- `transactions.date`, `work_orders.received_at`, `work_order_deliveries.delivered_at` artık **datetime**
- Formlarda **tarih + saat** seçici (`datetime-local`, saniyeli). Varsayılan: formun açıldığı an
- Listeler, ekstre, ana sayfa, atölye fişi detayı: `gg.aa.yyyy ss:dd:sn`. Müşteri fişinde `gg.aa.yyyy ss:dd`
- Ekstre ve listeler saate göre sıralanır (aynı saniyede eşitlikte kayıt sırası)
- Tarih filtrelerinde **bitiş günü tamamen dahil** (ör. bitiş 02.10 → 02.10 23:59:59'a kadar)
- Mevcut kayıtlar: saat, kaydın sisteme girildiği andan alındı (gün değişmedi)
- Yedek: `storage/app/yedek/kuyumcu-2026-10-02-saat-oncesi.sql`
- Testler: 84 test, hepsi geçiyor

### ✅ v0.5.2: Cari listesi sadeleşti (02.10.2026)
- **Kullanıcı isteği:** "Cari hesaplarda Tür, TL, Dolar, Euro kaldır, sadece müşteri ve has kısmı kalsın"
- Cari listesi (`/cariler`) sütunları: **Müşteri** (altında küçük yazıyla kod ve telefon) ve **Has (gr)**
- Tür filtresi kaldırıldı (durum filtresi ve arama duruyor)
- Diğer birimler veritabanında ve cari detayında (bakiye kartları, ekstre) duruyor, sadece listeden kaldırıldı

### ✅ v0.5.3: Atölye girişinde hızlı cari ekleme (02.10.2026)
- **Kullanıcı isteği:** "Atölye giriş kısmında firma yoksa oraya bir buton koy, cari ekle diye"
- Firma seçiminin yanında **"+ Yeni Cari"** butonu: formun içinde küçük bir alan açılır (ad + isteğe bağlı telefon)
  - **Ekle** (ya da Enter) → cari oluşturulur, listeye eklenir ve **otomatik seçilir**. Sayfadan çıkılmaz, girilen bilgiler kaybolmaz
  - Yeni cari türü "Müşteri", aktif. Diğer bilgiler sonra Cariler sayfasından düzenlenebilir
- Arka uç: `POST /cariler/hizli-ekle` (`accounts.quick-store`), JSON döner, ad zorunlu
- Testler: 86 test, hepsi geçiyor

---

## 5. Yapılacaklar

### ✅ v0.5.4: Müşteri fişi sadeleşti (02.10.2026)
- **Kullanıcı isteği:** "Fişte giriş gramına ihtiyaç yok. Fişte çıkış gramını ve müşterinin son bakiyesini göstereceğiz.
  Müşteri atölyeden çıkan ürünün gramını ve son durumunu görecek."
- Fişten **giriş bölümü** (gram, milyem, has) ve **atölyede kalan** satırı kaldırıldı
- Fiş içeriği: başlık, fiş no, tarih, müşteri, ürün · **ÇIKAN ÜRÜN** (gram, milyem, has) · **SON DURUM** · imza
- **Son durum = bu çıkıştan hemen sonraki bakiye** (`Balances::forAccountUntil`). Fiş sonradan tekrar yazdırılsa da
  aynı rakamı gösterir, araya giren işlemler fişi değiştirmez. Bakiye sıfırsa "Hesabınız kapalı (bakiye yok)"
- Testler: 86 test, hepsi geçiyor

### ✅ v0.5.5: Atölyede işçilik alanları kaldırıldı, milyem doğrudan girilir (02.10.2026)
- **Kullanıcı isteği:** "Atölye girişinde işçilik kutusunu kaldır, ben bunu milyem kısmından ayarlayabiliyorum."
  Çıkış için kullanıcıya soruldu: "Çıkışta da milyemi direkt yazayım" seçildi
- **Giriş:** tek alan **Milyem (işçilik dahil)**, ör. 0,595 → has = gram × milyem
- **Çıkış:** tek alan **Çıkış milyemi (işçilik dahil)**, ör. 0,625 → has = gram × çıkış milyemi
  - Varsayılan: bu fişin ya da firmanın son çıkış milyemi
- Giriş milyemi değişse de çıkışların has'ı değişmez (her çıkışın kendi milyemi var)
- Atölyede kalan has = kalan gram × giriş milyemi
- Veritabanı (migration `2026_10_02_000002`): `work_orders.purity` ← ayar + giriş işçiliği,
  yeni `work_order_deliveries.purity_out` ← ayar + çıkış işçiliği. `labor_purity_in` ve `labor_purity` kaldırıldı.
  **Has değerleri ve cari bakiyeleri birebir aynı kaldı** (önce/sonra karşılaştırıldı)
- Kaldırılan kod: `Workshop::parseLaborPurity`, `addPurity`, `laborMilli`, `WorkOrder::inPurity`, `WorkOrderDelivery::outPurity`
- Yedek: `storage/app/yedek/kuyumcu-2026-10-02-iscilik-kaldirma-oncesi.sql`
- Testler: 82 test, hepsi geçiyor

### ✅ v0.6: Ramat hesabı (02.10.2026)
- **Kullanıcının anlattığı işleyiş:** Müşteri ör. 50 gr 0,585 ile getirir, parça parça çıkılır (ör. 25 gr 0,625 ile).
  Çıkmayan kısım atölyede, **ramatta** kalır (50 gramda genelde 7-8 gr). Bu yüzden müşteriye has borcu sürekli artar.
  **Hesap müşteriyle hiç kapatılmaz**, ramat hesabı ayrı bir yerde görülür
  - Önce "has borcunu kapat" butonu düşünüldü, kullanıcı "hesap hiç kapatılmıyor" deyince **yapılmadı**
- **Ramat sayfası** (`/ramat`, menüde "Ramat"): sadece görüntüleme, kayıt değiştirmez
  - Özet: ramatta kalan gram (ve girişe oranı), ramattaki has, müşterilere toplam has borcu, fiş/müşteri sayısı
  - **Müşteri bazında tablo:** fiş sayısı, giren gr, çıkan gr, **ramat gr (%)**, ramat has, has borcu. Altta toplam
  - Müşteriye tıklayınca **fiş bazında detay** (her fişin giren, milyem, çıkan, ramat gr ve has değeri)
  - **Tarih aralığı** filtresi (fişin giriş tarihine göre)
  - Formüller: ramat gr = giriş − çıkışlar · ramat has = ramat gr × giriş milyemi · has borcu = giriş has − çıkış has
- Açık ve kapalı (tamamlanan) fişlerin hepsi dahil
- Yapılmadı (ileride istenirse): **ramat sonucu kaydı** (eritilen ramattan çıkan has ile beklenen karşılaştırması)
- Testler: `tests/Feature/RamatTest.php`. Toplam 85 test, hepsi geçiyor

### ✅ v0.6.1: "Fişi kapat" kaldırıldı (02.10.2026)
- **Kullanıcı:** "Fişi kapatmaya ihtiyacım yok. Müşteri sürekli ürün gönderdiği için borçlu kalıyorum,
  hesap hiç sıfır olmuyor. Fişi nasıl kapatacağım?"
- Fiş kapatma / tekrar açma özelliği, "Atölyede / Tamamlandı" durumu ve kapanış tarihi **tamamen kaldırıldı**
  - Veritabanı: `work_orders.status` ve `closed_at` silindi (migration `2026_10_02_000003`). Daha önce kapatılan 2 fiş normal fiş oldu
  - Atölye listesinde sekmeler kalktı, tüm fişler tek listede. "Kalan gr" sütunu → **"Ramat gr"**
  - Atölye özet kartları: ramatta kalan (oran + Ramat hesabına bağlantı), ramattaki has, bu ay giriş, bu ay çıkış
  - Fiş detayında "Atölyede kalan" → **"Ramatta kalan"**. Kalan > 0 olduğu sürece çıkış formu açık
  - Bilançodaki "Atölyede" sütunu artık tüm fişlerin ramatını sayıyor
- Cari bakiyeler değişmedi (önce/sonra karşılaştırıldı). Yedek: `storage/app/yedek/kuyumcu-2026-10-02-fis-kapatma-kaldirma-oncesi.sql`
- Testler: 85 test, hepsi geçiyor

### ✅ v0.7: Müşteri raporu, yıllık ya da tarih aralığı (02.10.2026)
- **Kullanıcı isteği:** "Müşteri bazlı rapor almak istiyorum. Yıllık bazda rapor almak istiyorum.
  Bunları müşteri talep ederse verebileyim."
- Menüde **Raporlar** açıldı → **Müşteri Raporu** (`/raporlar/musteri`). Cari detayında da **"Rapor"** butonu var
- Seçim: **müşteri + yıl** (varsayılan bu yıl; ilk hareketin yılından bu yıla kadar seçilebilir) ya da **özel tarih aralığı**
- **Yazdır** butonu: A4, yan menü ve üst çubuk yazdırılmaz (`print:hidden` ana şablona eklendi)
- **Rapor içeriği** (sadece has hesabı, müşteri gözünden):
  - Başlık: firma bilgileri, "Müşteri Hesap Raporu", dönem, düzenlenme tarihi, müşteri
  - Özet: **dönem başı** (devir), **atölyeye verilen** (has + ürün gramı), **size teslim edilen** (has + gram), **dönem sonu**
  - **Aylık özet** (yıllık raporda; Ocak–Aralık, verilen/teslim gram ve has, toplam)
  - **Hareket dökümü:** devreden bakiye satırı, her hareket (tarih saat, fiş no, açıklama, gram, milyem, verilen/teslim has,
    bakiye), dönem toplamı. Atölye hareketlerinde gram ve milyem fişten gelir; elle girilen cari hareketlerde boş
  - Bakiye dili: **"Alacağınız"** (atölye müşteriye borçlu) / **"Borcunuz"**
  - İmza alanları (firma ve müşteri), "bu rapor bilgi amaçlıdır" notu
- **Ramat bilgisi raporda yok** (atölyenin iç bilgisi)
- Teknik: `app/Http/Controllers/ReportController.php`, `resources/views/reports/customer.blade.php`
- Testler: `tests/Feature/RaporTest.php`. Toplam 89 test, hepsi geçiyor

### ✅ v0.8: Atölye girişi ve çıkışı ayrıldı, çıkış müşteriye yapılır (02.10.2026)
- **Kullanıcı:** "Atölye girişi için bir buton var, burada sadece giriş olacak. Atölye çıkışı için de ayrı buton olacak."
  + "Ürünü girdiğimde direk çıkış yapmıyorum. **Hangi fişten çıktığımın önemi yok.**"
- **Yeni yapı:** giriş ve çıkış birbirinden bağımsız
  - **Giriş** (`work_orders`, A00001…): müşteri, ürün, gram, milyem → cari alacak (değişmedi)
  - **Çıkış** (`work_order_deliveries`, **T00001…**): **müşteri**, ürün (isteğe bağlı), gram, çıkış milyemi, tarih, not → cari borç.
    Giriş fişine bağlı **değil**
  - **Ramat müşteri bazında:** müşterinin tüm girişleri − tüm çıkışları (`app/Support/WorkshopTotals.php`)
    - Ramat has = ramat gram × müşterinin ortalama giriş milyemi (giriş has / giriş gram)
  - Çıkış, müşterinin atölyede kalan toplam gramından fazla olamaz
  - Giriş düzenlenir/silinirse müşterinin girişleri çıkışlarının altına düşemez
- **Ekranlar**
  - Atölye üst kısmı (`work-orders/_header`): **Atölyeye Giriş** ve **Atölyeden Çıkış** butonları, özet kartları, **Girişler / Çıkışlar** sekmeleri
  - Girişler (`/atolye`): fiş, tarih, müşteri/ürün, gram, milyem, has
  - Çıkışlar (`/atolye/cikislar`): no, tarih, müşteri/ürün, gram, milyem, has, **Fiş** (müşteri fişi) ve sil (yönetici)
  - **Atölyeden Çıkış** (`/atolye/cikislar/yeni`): müşteri seçilince "atölyede kalan ürünü" ve son çıkış milyemi gelir; canlı has ve kalacak önizlemesi
  - Giriş fişi detayı: giriş bilgileri + müşterinin atölye durumu (toplam giriş, çıkış, ramat, has borcu) + "Bu Müşteriye Çıkış Yap"
  - Ramat sayfası: müşteri tablosu aynı; detayda müşterinin **giriş ve çıkışları** tarih sırasıyla ve **yürüyen ramat** ile
  - Müşteri fişi artık çıkış numarasıyla: `/atolye/cikislar/{çıkış}/fis` (fiş no = T00001)
  - Müşteri raporunda çıkış satırları "Teslim · ürün" ve T numarası ile
- **Veri dönüşümü** (migration `2026_10_02_000004`): mevcut çıkışlara müşteri ve ürün, bağlı oldukları fişten verildi;
  T00001–T00003 numaraları verildi; `work_order_id` kaldırıldı. Bakiyeler ve çıkış toplamları birebir aynı (önce/sonra kontrol)
  - Yedek: `storage/app/yedek/kuyumcu-2026-10-02-cikis-musteriye-oncesi.sql`
- Teknik: yeni `WorkshopDeliveryController`, `WorkshopDeliveryRequest`; `DeliverWorkOrderRequest` silindi;
  `WorkOrder`'dan fişe bağlı çıkış/kalan metotları kaldırıldı
- Testler: 87 test, hepsi geçiyor

### ✅ v0.8.1: Atölyede girişi olmayan müşteriye satış çıkışı (02.10.2026)
- **Kullanıcı:** "Atölyede girişi olmayan ama carisi kayıtlı olan kişiye çıkış yapabileyim. Bazen müşteri bana has altın
  veriyor, ben ürünü kendim işleyip satıyorum."
- Çıkışa **tür** eklendi (`work_order_deliveries.kind`):
  - **atolye** = "Atölyedeki ürününden": müşterinin getirdiği ürün. Ramattan düşer, müşterinin kalanını aşamaz
  - **satis** = "Kendi ürünüm (satış)": atölyenin kendi ürünü. **Ramatı etkilemez**, sınır yok
  - İki türde de has müşterinin carisine **borç** yazılır (cari açıklaması "Satış: …" / "Atölye çıkışı: …")
- Çıkış formunda **tüm aktif cariler** listelenir. Müşteri seçilince atölyede ürünü yoksa tür otomatik "satış" olur,
  varsa "atölye". Satışta "atölyede kalacak" kutusu gizlenir
- Çıkışlar listesinde satışlar **"Satış"** etiketiyle. Müşteri raporunda "Satış · ürün"
- Ramat toplamları, Ramat sayfası ve bilançodaki "Atölyede" sütunu **sadece atölye çıkışlarını** sayar
- Müşterinin verdiği has altın: Hareketler'den **Cari Alacaklandırma** (ya da kasaya girdiyse **Tahsilat**), birim Has
- Mevcut 3 çıkış "atolye" türünde. Yedek: `storage/app/yedek/kuyumcu-2026-10-02-cikis-turu-oncesi.sql`
- Testler: 90 test, hepsi geçiyor

### 🧪 Deneme carisi
- **`halit` (C00005, id 6)** kullanıcının **deneme/demo** carisidir. Bu caride yapılan işlemler denemedir
- Kullanıcı "sil" dediğinde: önce veritabanı yedeği alınır, sonra bu carinin **hareketleri, atölye girişleri, atölye çıkışları
  ve carinin kendisi** silinir (MUSTAFA silme işlemiyle aynı yöntem, bkz. "Gerçek kullanıma geçiş")
- Diğer cariler **gerçek veridir**, dokunulmaz

### ⏳ Sıradaki: kullanıcıdan özellik detayları bekleniyor
Aşağıdaki modüller menüde yer tutucu olarak var. Kapsamları kullanıcıyla netleştirilecek.
Kuyumculuk sektörü için **öneri** niteliğindeki başlıklar:

- [x] **Cariler**: müşteri/tedarikçi kartları; TL, döviz ve **has altın** bazında bakiye; cari ekstre (v0.2)
- [x] **Kasa**: TL / döviz / altın kasaları, tahsilat-ödeme hareketleri (v0.2)
- [ ] Kasalar arası virman (transfer)
- [ ] Döviz/altın bozdurma (bir birimden diğerine çevirme)
- [ ] Cari ekstre yazdırma / PDF
- [x] **Atölye**: fason iş emirleri: giriş (gram, milyem, has), çıkış (tartı, fire), işçilik → cari (v0.3)
- [ ] **Fire geri kazanımı (aylık rafine/remat)**: dönemsel fire toplamı, rafineden geri alınan has girişi, kazanım oranı
- [x] Firma bazında has hesabı: giriş, teslim ve fire cariye işleniyor (v0.3.1)
- [x] Parçalı teslim (bir girişin birkaç seferde çıkışı) (v0.3.2)
- [ ] Atölye fişi yazdırma (giriş/teslim fişi)
- [x] **Google Drive'a otomatik yedek** + yedek butonu + günde 3 otomatik yedek (v0.4)
  - [ ] Kullanıcı Google Cloud kurulumunu yapıp Drive'ı bağlayacak
  - [x] Windows Görev Zamanlayıcı görevi kuruldu ve denendi (02.10.2026)
- [ ] Yedekten geri yükleme ekranı (şimdilik: zip'teki .sql dosyası HeidiSQL ile içe aktarılır)
- [ ] **Stok**: ürün/hammadde, gram ve ayar bazında takip
- [ ] **Raporlar**: günlük özet, cari bakiye listesi, has altın durumu
- [ ] **Ayarlar**: kullanıcı yönetimi (personel ekleme, pasif etme, şifre değiştirme), firma bilgileri, altın kurları
- [x] Dashboard kartlarını gerçek verilerle doldurmak (v0.2)

### 🚀 Sunucuya taşıma (ileride)
- [ ] Sunucu tipine karar ver (paylaşımlı hosting / VPS)
- [ ] Sunucuda: `git clone` → `composer install --no-dev --optimize-autoloader`
- [ ] `.env` oluştur (`APP_ENV=production`, `APP_DEBUG=false`, gerçek DB bilgileri, güçlü `ADMIN_PASSWORD`)
- [ ] `php artisan key:generate` → `php artisan migrate --force` → `php artisan db:seed --force`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Cron: `* * * * * cd /proje && php artisan schedule:run >> /dev/null 2>&1` (otomatik yedek için şart)
- [ ] Sunucuda `mysqldump` kurulu olmalı. `.env` → `DB_DUMP_PATH` boş bırakılabilir
- [ ] Google Cloud OAuth istemcisine sunucu adresiyle yönlendirme adresi ekle: `https://ALANADI/yedekleme/google/callback`
- [ ] Web sunucusunun kök dizini `public/` klasörü olmalı
- [ ] HTTPS (SSL) aktif edilmeli
- [ ] Güncelleme akışı: `git pull` → `composer install --no-dev` → `php artisan migrate --force` → cache komutları

---

## 6. Çalışma Kuralları

1. Her geliştirme sonrası bu dosya güncellenir (Bölüm 4'e yeni sürüm, Bölüm 5'ten tamamlananlar işaretlenir).
2. Her geliştirme ayrı bir git commit'i olarak kaydedilir ve GitHub'a push edilir, commit mesajları Türkçe.
3. CSS/Blade/JS değişikliğinden sonra commit öncesi `npm run build`.
4. Yeni özellik = yeni migration (eski migration dosyaları sunucuya çıktıktan sonra **değiştirilmez**).
5. Parasal ve gram değerler için `decimal` kullanılır (`float` değil); yuvarlama hatası olmasın.
6. Commit öncesi `php artisan test` geçmeli.
