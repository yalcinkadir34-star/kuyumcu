<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Services\BackupService;
use App\Services\GoogleDrive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private GoogleDrive $drive) {}

    public function index(): View
    {
        $lastSuccess = BackupLog::query()->where('status', 'basarili')->latest('id')->first();

        return view('backups.index', [
            'logs' => BackupLog::query()->with('user')->latest('id')->paginate(30),
            'lastSuccess' => $lastSuccess,
            'drive' => $this->drive,
            'redirectUri' => route('backups.google.callback'),
            'times' => config('kuyumcu.backup.times'),
        ]);
    }

    /** "Şimdi Yedek Al" butonu */
    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        try {
            $log = $backups->run(BackupLog::TRIGGER_MANUEL, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $log->isSuccessful()) {
            return back()->with('error', 'Yedek alınamadı: '.$log->error);
        }

        $message = "Yedek alındı ({$log->sizeLabel()}).";

        return match ($log->drive_status) {
            'yuklendi' => back()->with('success', $message.' Google Drive\'a yüklendi.'),
            'bagli_degil' => back()->with('success', $message.' Google Drive bağlı olmadığı için sadece bu bilgisayarda saklandı.'),
            default => back()->with('error', $message.' Ancak Google Drive\'a yüklenemedi: '.$log->error),
        };
    }

    public function download(BackupLog $backupLog): StreamedResponse|RedirectResponse
    {
        if (! $backupLog->file_path || ! Storage::disk('local')->exists($backupLog->file_path)) {
            return back()->with('error', 'Bu yedek dosyası artık bilgisayarda yok (eski yedekler otomatik temizlenir).');
        }

        return Storage::disk('local')->download($backupLog->file_path, $backupLog->file_name);
    }

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->drive->isConfigured()) {
            return back()->with('error', 'Önce Google bilgilerini .env dosyasına girin (sayfadaki adımlar).');
        }

        $state = Str::random(40);
        $request->session()->put('google_drive_state', $state);

        return redirect()->away($this->drive->authUrl(route('backups.google.callback'), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('google_drive_state');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('backups.index')->with('error', 'Google bağlantısı doğrulanamadı. Tekrar deneyin.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('backups.index')->with('error', 'Google bağlantısı iptal edildi.');
        }

        try {
            $this->drive->connect($request->query('code'), route('backups.google.callback'));
        } catch (Throwable $e) {
            Log::error('Google Drive bağlantı hatası', ['exception' => $e]);

            return redirect()->route('backups.index')->with('error', 'Google Drive\'a bağlanılamadı: '.$e->getMessage());
        }

        return redirect()->route('backups.index')
            ->with('success', 'Google Drive bağlandı ('.$this->drive->accountEmail().'). Yedekler "'.config('kuyumcu.backup.drive_folder_name').'" klasörüne yüklenecek.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->drive->disconnect();

        return redirect()->route('backups.index')->with('success', 'Google Drive bağlantısı kaldırıldı.');
    }
}
