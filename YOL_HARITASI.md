# Kuyumcu Atölye Uygulaması: Yol Haritası

> Bu dosya projenin **hafızasıdır**. Yapılan her geliştirme, alınan her karar ve
> sıradaki işler burada tutulur. Bir şey hatırlanmak istendiğinde önce bu dosya okunur.
> Her geliştirmeden sonra güncellenir ve commit edilir.

**Son güncelleme:** 30.09.2026
**Mevcut sürüm:** 0.3.3: Atölye işçiliği milyem olarak (ayar + işçilik)

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

### Günlük çalıştırma
1. **Laragon**'u aç → **Start All** (Apache + MySQL başlar)
2. Tarayıcıda `http://kuyumcu.test` adresine git
   - Alternatif: proje klasöründe `php artisan serve` → `http://localhost:8000`
3. Veritabanını görmek için Laragon → **Database** (HeidiSQL açılır, kullanıcı `root`, şifre boş)

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
  - ⚠️ Kullanıcı 57,49 bekliyordu, 0,203 gr fark var. Kullanıcıya soruldu (bkz. Bölüm 5)
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

---

## 5. Yapılacaklar

### ❓ Kullanıcıya sorulan açık konular
- Mustafa has bakiyesi: sistem **57,693**, kullanıcı **57,49** bekliyor (0,203 gr fark). Hesabın kontrolü bekleniyor

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
- [ ] **Google Drive'a otomatik yedek**: veritabanı ve dosyaların düzenli yedeği (kullanıcı istedi, 30.09.2026)
  - Plan: `spatie/laravel-backup` + Google Drive bağlantısı. Kullanıcının Google Cloud'da bir kerelik izin oluşturması gerekecek
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
