# Kuyumcu Atölye: veritabanını (MySQL) ve siteyi (http://localhost:8000) başlatır.
# Zaten çalışıyorsa tekrar başlatmaz. Windows açılışında "Kuyumcu Sistem" göreviyle çalışır
# (bkz. scripts/windows-baslangic-gorevi.ps1). Elle de çalıştırılabilir:
#   powershell -ExecutionPolicy Bypass -File scripts\sistemi-baslat.ps1

$project = Split-Path -Parent $PSScriptRoot
$php = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
$mysqlDir = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64'

function Test-Port([int]$port) {
    [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
}

# 1) MySQL
if (-not (Test-Port 3306)) {
    Start-Process -WindowStyle Hidden -FilePath "$mysqlDir\bin\mysqld.exe" `
        -ArgumentList "--defaults-file=`"$mysqlDir\my.ini`""

    for ($i = 0; $i -lt 30 -and -not (Test-Port 3306); $i++) { Start-Sleep 1 }
}

# 2) Site: http://localhost:8000
if (-not (Test-Port 8000)) {
    Start-Process -WindowStyle Hidden -WorkingDirectory $project -FilePath $php `
        -ArgumentList 'artisan', 'serve', '--port=8000'
}
