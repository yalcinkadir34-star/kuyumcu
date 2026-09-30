# Kuyumcu Atölye Uygulaması: Yol Haritası

> Bu dosya projenin **hafızasıdır**. Yapılan her geliştirme, alınan her karar ve
> sıradaki işler burada tutulur. Bir şey hatırlanmak istendiğinde önce bu dosya okunur.
> Her geliştirmeden sonra güncellenir ve commit edilir.

**Son güncelleme:** 30.09.2026
**Mevcut sürüm:** 0.2: Cari hesaplar, kasalar ve genel bilanço

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

---

## 5. Yapılacaklar

### ⏳ Sıradaki: kullanıcıdan özellik detayları bekleniyor
Aşağıdaki modüller menüde yer tutucu olarak var. Kapsamları kullanıcıyla netleştirilecek.
Kuyumculuk sektörü için **öneri** niteliğindeki başlıklar:

- [x] **Cariler**: müşteri/tedarikçi kartları; TL, döviz ve **has altın** bazında bakiye; cari ekstre (v0.2)
- [x] **Kasa**: TL / döviz / altın kasaları, tahsilat-ödeme hareketleri (v0.2)
- [ ] Kasalar arası virman (transfer)
- [ ] Döviz/altın bozdurma (bir birimden diğerine çevirme)
- [ ] Cari ekstre yazdırma / PDF
- [ ] **Atölye**: iş emirleri (müşteriden gelen maden, ayar, milyem, fire, işçilik), teslim alma/verme
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
