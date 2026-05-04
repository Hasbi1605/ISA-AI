<div class="min-h-screen w-full bg-stone-50 text-stone-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-5 py-6">
        <header class="flex flex-col gap-3 border-b border-stone-200 pb-5 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm font-semibold text-stone-500 hover:text-ista-primary dark:text-gray-400">ISTA AI</a>
                <h1 class="mt-2 text-2xl font-semibold tracking-normal">Google Drive Kantor</h1>
                <p class="mt-1 text-sm text-stone-600 dark:text-gray-400">Konfigurasi server-side untuk download, ingest, dan upload file kantor ke ISTA AI.</p>
            </div>
            <a href="{{ route('cloud-storage.google-drive') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-ista-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-800">
                Buka Browser Drive
            </a>
        </header>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500 dark:text-gray-400">Status</p>
                <div class="mt-3 flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $isConfigured ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                    <span class="text-base font-semibold">{{ $isConfigured ? 'Drive kantor aktif' : 'Drive kantor belum aktif' }}</span>
                </div>
                <p class="mt-2 text-sm text-stone-600 dark:text-gray-400">{{ $isConfigured ? 'Aplikasi dapat membaca dan mengunggah file ke folder kantor yang diizinkan.' : 'Lengkapi credential service account dan root folder id di environment server.' }}</p>
            </article>

            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500 dark:text-gray-400">Root Folder</p>
                <p class="mt-3 break-all text-sm font-medium text-stone-700 dark:text-stone-200">{{ $rootFolderId ?? 'Belum diatur' }}</p>
                <p class="mt-2 text-sm text-stone-600 dark:text-gray-400">Semua operasi dibatasi ke folder root kantor ini.</p>
            </article>

            <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500 dark:text-gray-400">Folder Upload Default</p>
                <p class="mt-3 text-sm font-medium text-stone-700 dark:text-stone-200">{{ $defaultUploadFolderName }}</p>
                <p class="mt-2 text-sm text-stone-600 dark:text-gray-400">File hasil export disimpan ke folder ini jika user tidak memilih folder tujuan lain.</p>
            </article>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500 dark:text-gray-400">Catatan Setup</p>
                    <h2 class="mt-2 text-lg font-semibold">Akses minimal, tanpa secret di database</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600 dark:text-gray-400">
                        Gunakan service account yang hanya diberi akses ke folder atau Shared Drive kantor yang memang dipakai ISTA AI.
                        Jangan menaruh credential di database, dan hindari memberi akses ke seluruh struktur Drive organisasi.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600 dark:bg-gray-800 dark:text-gray-300">Download</span>
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600 dark:bg-gray-800 dark:text-gray-300">Ingest</span>
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600 dark:bg-gray-800 dark:text-gray-300">Upload</span>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl bg-stone-50 p-4 dark:bg-gray-950">
                    <p class="text-sm font-semibold text-stone-800 dark:text-stone-100">Variabel environment</p>
                    <ul class="mt-3 space-y-2 text-sm text-stone-600 dark:text-gray-400">
                        <li><code>GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON</code> atau <code>GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH</code></li>
                        <li><code>GOOGLE_DRIVE_ROOT_FOLDER_ID</code></li>
                        <li><code>GOOGLE_DRIVE_UPLOAD_FOLDER_NAME</code></li>
                        <li><code>GOOGLE_DRIVE_SHARED_DRIVE_ID</code> jika memakai Shared Drive</li>
                    </ul>
                </div>

                <div class="rounded-2xl bg-stone-50 p-4 dark:bg-gray-950">
                    <p class="text-sm font-semibold text-stone-800 dark:text-stone-100">Alur yang sudah aktif</p>
                    <ul class="mt-3 space-y-2 text-sm text-stone-600 dark:text-gray-400">
                        <li>Browse file Drive kantor dari folder root yang diizinkan.</li>
                        <li>Download file sementara lalu masuk ke pipeline ingest ISTA AI.</li>
                        <li>Upload hasil export ke folder default ISTA AI di Drive kantor.</li>
                    </ul>
                </div>
            </div>
        </section>

        @if (! $isConfigured)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                <p class="font-semibold">Drive kantor belum siap</p>
                <p class="mt-2 text-sm leading-6">
                    Lengkapi credential service account dan root folder id di server, lalu reload halaman ini untuk memastikan status berubah menjadi aktif.
                </p>
            </section>
        @endif
    </div>
</div>
