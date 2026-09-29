# Kuyumcu Atölye

Kuyumcu atölyesi için web tabanlı hesap, atölye ve cari takip uygulaması.

**Teknoloji:** Laravel 13 · PHP 8.3 · MySQL 8 · Blade + Tailwind CSS 4

Yapılanlar, kararlar ve yol haritası için: **[YOL_HARITASI.md](YOL_HARITASI.md)**

## Yerel kurulum (Windows + Laragon)

```bash
git clone <repo-adresi> C:\laragon\www\kuyumcu
cd C:\laragon\www\kuyumcu
composer install
copy .env.example .env
php artisan key:generate
```

1. `.env` içinde `ADMIN_PASSWORD` değerini belirle.
2. MySQL'de `kuyumcu` veritabanını oluştur (`utf8mb4_turkish_ci`).
3. Tabloları ve yönetici hesabını oluştur:

```bash
php artisan migrate --seed
```

4. Laragon → Start All → `http://kuyumcu.test`

## Sunucuya yayınlama

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Web sunucusunun kök dizini `public/` olmalıdır. Derlenmiş CSS/JS (`public/build`) repoda
bulunduğu için sunucuda Node.js gerekmez.
