# Masaüstüne "Kuyumcu Atölye" kısayolu oluşturur.
# Kısayol Chrome'u ayrı bir profille ve --kiosk-printing ile açar: "Yazdır"a basınca önizleme ekranı
# çıkmaz, fiş doğrudan Windows'un varsayılan yazıcısına (fiş yazıcısı, TP80NB) gider.
# Normal Chrome açıkken de çalışır (ayrı profil). Çalıştırmak için:
#   powershell -ExecutionPolicy Bypass -File scripts\masaustu-kisayolu.ps1

$chrome = @(
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $chrome) {
    Write-Error 'Google Chrome bulunamadı.'
    exit 1
}

$profile = "$env:LOCALAPPDATA\KuyumcuAtolye\Chrome"
$desktop = [Environment]::GetFolderPath('Desktop')

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut("$desktop\Kuyumcu Atölye.lnk")
$shortcut.TargetPath = $chrome
$shortcut.Arguments = "--user-data-dir=`"$profile`" --kiosk-printing --app=http://localhost:8000"
$shortcut.IconLocation = "$chrome,0"
$shortcut.Description = 'Kuyumcu Atölye (fişler doğrudan fiş yazıcısına basılır)'
$shortcut.Save()

Write-Output "Kısayol oluşturuldu: $desktop\Kuyumcu Atölye.lnk"
