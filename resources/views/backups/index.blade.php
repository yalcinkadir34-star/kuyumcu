@extends('layouts.app')

@section('title', 'Yedekleme')

@section('content')
    <x-page-header title="Yedekleme" subtitle="Veritabanı yedekleri: bu bilgisayarda ve Google Drive'da">
        <x-slot:actions>
            <form method="POST" action="{{ route('backups.store') }}" data-loading-text="Yedek alınıyor…">
                @csrf
                <button class="btn btn-gold">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    <span>Şimdi Yedek Al</span>
                </button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Son yedek --}}
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Son başarılı yedek</div>
            @if ($lastSuccess)
                <div class="mt-2 text-xl font-semibold text-stone-900">{{ $lastSuccess->created_at->format('d.m.Y H:i') }}</div>
                <div class="mt-1 text-xs text-stone-500">{{ $lastSuccess->created_at->locale('tr')->diffForHumans() }} · {{ $lastSuccess->sizeLabel() }}</div>
            @else
                <div class="mt-2 text-xl font-semibold text-stone-400">Henüz yok</div>
            @endif
        </div>

        {{-- Otomatik yedek --}}
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Otomatik yedek</div>
            <div class="mt-2 text-xl font-semibold text-stone-900">Günde {{ count($times) }} kez</div>
            <div class="mt-1 text-xs text-stone-500">Her gün saat {{ implode(', ', $times) }}</div>
        </div>

        {{-- Google Drive durumu --}}
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Google Drive</div>
            @if ($drive->isConnected())
                <div class="mt-2 flex items-center gap-2 text-xl font-semibold text-emerald-700">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span> Bağlı
                </div>
                <div class="mt-1 truncate text-xs text-stone-500">{{ $drive->accountEmail() }}</div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($drive->folderUrl())
                        <a href="{{ $drive->folderUrl() }}" target="_blank" rel="noopener" class="btn btn-secondary py-1.5 text-xs">Klasörü Aç ↗</a>
                    @endif
                    <form method="POST" action="{{ route('backups.google.disconnect') }}" data-confirm="Google Drive bağlantısı kaldırılsın mı? Drive'daki yedekler silinmez.">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger py-1.5 text-xs">Bağlantıyı Kaldır</button>
                    </form>
                </div>
            @elseif ($drive->isConfigured())
                <div class="mt-2 flex items-center gap-2 text-xl font-semibold text-amber-700">
                    <span class="size-2.5 rounded-full bg-amber-500"></span> Bağlı değil
                </div>
                <a href="{{ route('backups.google.connect') }}" class="btn btn-primary mt-3 py-1.5 text-xs">Google Drive'a Bağlan</a>
            @else
                <div class="mt-2 flex items-center gap-2 text-xl font-semibold text-stone-400">
                    <span class="size-2.5 rounded-full bg-stone-300"></span> Kurulmadı
                </div>
                <div class="mt-1 text-xs text-stone-500">Aşağıdaki kurulum adımlarını izleyin.</div>
            @endif
        </div>
    </div>

    {{-- Google kurulum adımları --}}
    @unless ($drive->isConnected())
        <details class="card group mt-6" @unless ($drive->isConfigured()) open @endunless>
            <summary class="card-header cursor-pointer list-none">
                <h3 class="font-semibold text-stone-900">Google Drive kurulumu (bir kerelik)</h3>
                <svg class="size-5 text-stone-400 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <ol class="list-decimal space-y-3 py-5 pr-5 pl-10 text-sm text-stone-700">
                <li>
                    <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener" class="font-medium text-gold-700 hover:underline">Google Cloud Console</a>'a
                    yedeğin gideceği Google hesabıyla girin ve <b>yeni proje</b> oluşturun (ad: <code class="rounded bg-stone-100 px-1">Kuyumcu Yedek</code>).
                </li>
                <li>
                    <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener" class="font-medium text-gold-700 hover:underline">Google Drive API</a>
                    sayfasında <b>Enable (Etkinleştir)</b>'e basın.
                </li>
                <li>
                    <a href="https://console.cloud.google.com/auth/branding" target="_blank" rel="noopener" class="font-medium text-gold-700 hover:underline">OAuth izin ekranı</a>'nı açın:
                    uygulama adı <code class="rounded bg-stone-100 px-1">Kuyumcu Yedek</code>, kendi e-postanız. Kitle (Audience) olarak <b>External (Harici)</b> seçin.
                    Ardından <b>Audience</b> sayfasında <b>Publish app (Uygulamayı yayınla)</b>'ya basın.
                    <span class="text-stone-500">(Yayınlanmazsa bağlantı 7 günde bir kopar. Uygulama sadece kendi oluşturduğu dosyalara eriştiği için Google incelemesi gerekmez.)</span>
                </li>
                <li>
                    <a href="https://console.cloud.google.com/auth/clients/create" target="_blank" rel="noopener" class="font-medium text-gold-700 hover:underline">OAuth istemcisi oluşturun</a>:
                    tür <b>Web application</b>, <b>Authorized redirect URIs</b> kısmına şu adresi ekleyin:
                    <div class="mt-2 flex items-center gap-2">
                        <code class="flex-1 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 font-mono text-xs break-all">{{ $redirectUri }}</code>
                    </div>
                    @unless (str_starts_with($redirectUri, 'http://localhost') || str_starts_with($redirectUri, 'https://'))
                        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Google, <b>.test</b> gibi yerel adresleri kabul etmez. Bağlanma işlemini
                            <a href="http://localhost:8000/yedekleme" class="font-medium underline">http://localhost:8000/yedekleme</a> adresinden yapın.
                        </p>
                    @endunless
                </li>
                <li>
                    Oluşan <b>Client ID</b> ve <b>Client secret</b> değerlerini proje klasöründeki <code class="rounded bg-stone-100 px-1">.env</code> dosyasına yazın:
                    <pre class="mt-2 overflow-x-auto rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 font-mono text-xs">GOOGLE_DRIVE_CLIENT_ID=...
GOOGLE_DRIVE_CLIENT_SECRET=...</pre>
                </li>
                <li>Bu sayfayı yenileyin ve <b>Google Drive'a Bağlan</b> butonuna basın.</li>
            </ol>
        </details>
    @endunless

    {{-- Yedek geçmişi --}}
    <div class="card mt-6 overflow-hidden">
        <div class="card-header">
            <h3 class="font-semibold text-stone-900">Yedek Geçmişi</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Tür</th>
                        <th>Durum</th>
                        <th>Dosya</th>
                        <th class="text-right">Boyut</th>
                        <th>Google Drive</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-stone-600">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                            <td class="text-stone-600">
                                {{ $log->trigger === 'manuel' ? 'Elle' : 'Otomatik' }}
                                @if ($log->user)
                                    <div class="text-xs text-stone-400">{{ $log->user->name }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($log->isSuccessful())
                                    <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Başarılı</span>
                                @else
                                    <span class="badge bg-red-50 text-red-700 ring-red-600/20" title="{{ $log->error }}">Hata</span>
                                @endif
                            </td>
                            <td class="max-w-64 truncate font-mono text-xs text-stone-600" title="{{ $log->error }}">{{ $log->file_name ?? $log->error }}</td>
                            <td class="text-right whitespace-nowrap text-stone-600">{{ $log->sizeLabel() }}</td>
                            <td>
                                @switch($log->drive_status)
                                    @case('yuklendi')
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Yüklendi</span>
                                        @break
                                    @case('bagli_degil')
                                        <span class="badge bg-stone-100 text-stone-500 ring-stone-300">Bağlı değil</span>
                                        @break
                                    @case('hata')
                                        <span class="badge bg-red-50 text-red-700 ring-red-600/20" title="{{ $log->error }}">Yüklenemedi</span>
                                        @break
                                    @default
                                        <span class="text-stone-300">—</span>
                                @endswitch
                            </td>
                            <td class="text-right">
                                @if ($log->isSuccessful())
                                    <a href="{{ route('backups.download', $log) }}" class="rounded p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" title="İndir" aria-label="İndir">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-stone-500">Henüz yedek alınmadı. Yukarıdaki <b>Şimdi Yedek Al</b> butonunu kullanın.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
