# Kuyumcu Atölye: Windows'a giriş yapılınca veritabanını ve siteyi otomatik başlatan görevi kurar.
#
# Kurmak için (PowerShell'de, proje klasöründe):
#   powershell -ExecutionPolicy Bypass -File scripts\windows-baslangic-gorevi.ps1
# Kaldırmak için:
#   Unregister-ScheduledTask -TaskName "Kuyumcu Sistem" -Confirm:$false

$ErrorActionPreference = 'Stop'

$script = Join-Path $PSScriptRoot 'sistemi-baslat.ps1'

# conhost --headless: pencere açılmadan çalıştırır (Windows 10/11)
$action = New-ScheduledTaskAction `
    -Execute 'conhost.exe' `
    -Argument "--headless powershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$script`""

$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

Register-ScheduledTask `
    -TaskName 'Kuyumcu Sistem' `
    -Description 'Kuyumcu Atölye: Windows açılışında MySQL ve siteyi (localhost:8000) başlatır' `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Force | Out-Null

Write-Host 'Görev kuruldu: "Kuyumcu Sistem" (Windows girişinde çalışır)'
