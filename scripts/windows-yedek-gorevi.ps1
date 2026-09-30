# Kuyumcu Atölye: Windows Görev Zamanlayıcı'ya otomatik yedek görevi ekler.
#
# Her gün 10:00, 15:00 ve 20:00'de "php artisan yedek:al" çalışır (pencere açılmadan).
# Bilgisayar o saatte kapalıysa, açıldığında kaçan yedek alınır.
#
# Kurmak için (PowerShell'de, proje klasöründe):
#   powershell -ExecutionPolicy Bypass -File scripts\windows-yedek-gorevi.ps1
# Kaldırmak için:
#   Unregister-ScheduledTask -TaskName "Kuyumcu Yedek" -Confirm:$false

$ErrorActionPreference = 'Stop'

$project = Split-Path -Parent $PSScriptRoot
$php = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'

if (-not (Test-Path $php)) {
    throw "PHP bulunamadı: $php"
}

# conhost --headless: komut penceresi açılmadan çalıştırır (Windows 10/11)
$action = New-ScheduledTaskAction `
    -Execute 'conhost.exe' `
    -Argument "--headless `"$php`" artisan yedek:al" `
    -WorkingDirectory $project

$triggers = @('10:00', '15:00', '20:00') | ForEach-Object { New-ScheduledTaskTrigger -Daily -At $_ }

$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 15)

Register-ScheduledTask `
    -TaskName 'Kuyumcu Yedek' `
    -Description 'Kuyumcu Atölye veritabanı yedeği (günde 3 kez) ve Google Drive yüklemesi' `
    -Action $action `
    -Trigger $triggers `
    -Settings $settings `
    -Force | Out-Null

Write-Host 'Görev kuruldu: "Kuyumcu Yedek" (her gün 10:00, 15:00, 20:00)'
