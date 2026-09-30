# Kuyumcu Atölye: Claude için notlar

- İşe başlamadan önce **`YOL_HARITASI.md`** dosyasını oku: kararlar, yapılanlar ve sıradaki işler orada.
- Kullanıcıyla Türkçe konuş; arayüz metinleri, commit mesajları ve test isimleri Türkçe.
- Her geliştirmeden sonra: `php artisan test` → (arayüz değiştiyse) `npm run build` → `YOL_HARITASI.md` güncelle → commit → `git push` (origin/main, GitHub girişi kayıtlı).
- `public/build` bilerek git'te (sunucuda Node yok varsayımı).
- Araçlar PATH'te olmayabilir; Laragon yolları:
  - PHP: `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe`
  - Composer: `C:\laragon\bin\composer\composer.bat`
  - MySQL: `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` (root, şifresiz)
  - Node: `C:\laragon\bin\nodejs\node-v22`
- Para ve gram alanlarında `decimal` kullan, `float` kullanma.
