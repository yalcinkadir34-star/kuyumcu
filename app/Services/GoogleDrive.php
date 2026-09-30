<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Google Drive'a yedek yükleme (Drive REST API v3, ek kütüphane yok).
 *
 * Yetki: "drive.file", yani uygulama Drive'da sadece kendi oluşturduğu dosyaları görür.
 * Bağlantı bilgisi (refresh token) settings tablosunda şifreli saklanır.
 */
class GoogleDrive
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function isConfigured(): bool
    {
        return filled(config('kuyumcu.backup.google_client_id')) && filled(config('kuyumcu.backup.google_client_secret'));
    }

    public function isConnected(): bool
    {
        return $this->isConfigured() && Setting::get('google_drive_refresh_token') !== null;
    }

    public function accountEmail(): ?string
    {
        return Setting::get('google_drive_email');
    }

    public function folderUrl(): ?string
    {
        $id = Setting::get('google_drive_folder_id');

        return $id ? "https://drive.google.com/drive/folders/{$id}" : null;
    }

    public function authUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('kuyumcu.backup.google_client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE.' email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /** Google'dan dönen kodu kalıcı bağlantıya (refresh token) çevirir. */
    public function connect(string $code, string $redirectUri): void
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('kuyumcu.backup.google_client_id'),
            'client_secret' => config('kuyumcu.backup.google_client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        if (empty($response['refresh_token'])) {
            throw new RuntimeException('Google kalıcı erişim izni vermedi. Lütfen tekrar bağlanmayı deneyin.');
        }

        Setting::putSecret('google_drive_refresh_token', $response['refresh_token']);
        Setting::forget('google_drive_folder_id');
        Cache::put('google_drive_access_token', $response['access_token'], now()->addSeconds(($response['expires_in'] ?? 3600) - 120));

        $about = $this->client()->get(self::API.'/about', ['fields' => 'user(emailAddress)'])->json();
        Setting::put('google_drive_email', $about['user']['emailAddress'] ?? null);

        $this->folderId();
    }

    public function disconnect(): void
    {
        $token = Setting::getSecret('google_drive_refresh_token');

        if ($token) {
            Http::asForm()->post(self::REVOKE_URL, ['token' => $token]); // hata olsa da bağlantı silinir
        }

        Setting::forget('google_drive_refresh_token', 'google_drive_email', 'google_drive_folder_id');
        Cache::forget('google_drive_access_token');
    }

    /** Dosyayı yedek klasörüne yükler, Drive dosya id'sini döner. */
    public function upload(string $path, string $name): string
    {
        $boundary = 'kuyumcu'.Str::random(16);
        $metadata = json_encode(['name' => $name, 'parents' => [$this->folderId()]]);

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: application/zip\r\n\r\n"
            .file_get_contents($path)."\r\n"
            ."--{$boundary}--";

        return $this->client()
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD_API.'/files?uploadType=multipart&fields=id')
            ->throw()
            ->json('id');
    }

    /** @return array<int, array{id:string, name:string, size:?string, createdTime:string}> yeniden eskiye */
    public function listBackups(): array
    {
        return $this->client()->get(self::API.'/files', [
            'q' => "'{$this->folderId()}' in parents and trashed = false",
            'orderBy' => 'createdTime desc',
            'fields' => 'files(id,name,size,createdTime)',
            'pageSize' => 1000,
        ])->throw()->json('files', []);
    }

    /** En yeni $keep yedek dışındakileri siler; silinen sayısını döner. */
    public function prune(int $keep): int
    {
        $old = array_slice($this->listBackups(), $keep);

        foreach ($old as $file) {
            $this->client()->delete(self::API.'/files/'.$file['id'])->throw();
        }

        return count($old);
    }

    /** Yedek klasörünün id'si; yoksa oluşturulur. */
    private function folderId(): string
    {
        if ($id = Setting::get('google_drive_folder_id')) {
            return $id;
        }

        $id = $this->client()->post(self::API.'/files?fields=id', [
            'name' => config('kuyumcu.backup.drive_folder_name'),
            'mimeType' => 'application/vnd.google-apps.folder',
        ])->throw()->json('id');

        Setting::put('google_drive_folder_id', $id);

        return $id;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())->timeout(120);
    }

    private function accessToken(): string
    {
        return Cache::remember('google_drive_access_token', now()->addMinutes(50), function () {
            $refreshToken = Setting::getSecret('google_drive_refresh_token')
                ?? throw new RuntimeException('Google Drive bağlı değil.');

            return Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => config('kuyumcu.backup.google_client_id'),
                'client_secret' => config('kuyumcu.backup.google_client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ])->throw()->json('access_token');
        });
    }
}
