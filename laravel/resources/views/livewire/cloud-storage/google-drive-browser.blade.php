<div class="min-h-screen w-full bg-stone-50 text-stone-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6">
        <header class="flex flex-col gap-3 border-b border-stone-200 pb-5 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('cloud-storage.index') }}" wire:navigate class="text-sm font-semibold text-stone-500 hover:text-ista-primary dark:text-gray-400">Google Drive Kantor</a>
                <h1 class="mt-2 text-2xl font-semibold tracking-normal">Browser File</h1>
                <p class="mt-1 text-sm text-stone-600 dark:text-gray-400">Pilih file dari folder kantor yang diizinkan untuk diproses ke pipeline ISTA AI.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('cloud-storage.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                    Status Drive
                </a>
                <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-ista-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-800">
                    Kembali ke Dashboard
                </a>
            </div>
        </header>

        @if (! $isConfigured)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                <p class="font-semibold">Google Drive belum dikonfigurasi</p>
                <p class="mt-2 text-sm leading-6">Lengkapi service account dan root folder id terlebih dahulu agar browser file bisa menampilkan daftar Drive kantor.</p>
            </section>
        @else
            <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-500 dark:text-gray-400">Lokasi saat ini</p>
                        <ol class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                            @foreach ($breadcrumb as $index => $crumb)
                                <li class="flex items-center gap-2">
                                    @if ($index > 0)
                                        <span class="text-stone-400 dark:text-gray-500">/</span>
                                    @endif
                                    <button type="button"
                                            wire:click="goToBreadcrumb({{ $index }})"
                                            class="rounded-full px-3 py-1 font-medium text-stone-600 transition hover:bg-stone-100 hover:text-ista-primary dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">
                                        {{ $crumb['name'] }}
                                    </button>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                                wire:click="previousPage"
                                @disabled(empty($pageTokenStack))
                                class="inline-flex items-center justify-center rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            Sebelumnya
                        </button>
                        <button type="button"
                                wire:click="nextPage"
                                @disabled(blank($nextPageToken))
                                class="inline-flex items-center justify-center rounded-lg bg-ista-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-50">
                            Berikutnya
                        </button>
                    </div>
                </div>

                <div class="mt-5">
                    <label class="block text-sm font-semibold text-stone-700 dark:text-gray-200" for="google-drive-search">Cari file</label>
                    <input id="google-drive-search"
                           type="text"
                           wire:model.live.debounce.350ms="search"
                           placeholder="Cari nama file atau folder..."
                           class="mt-2 w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-ista-primary focus:ring-2 focus:ring-ista-primary/15 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                </div>

                @if ($statusMessage || $errorMessage)
                    <div class="mt-5 space-y-3">
                        @if ($statusMessage)
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                                {{ $statusMessage }}
                            </div>
                        @endif

                        @if ($errorMessage)
                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
                                {{ $errorMessage }}
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-6 space-y-3">
                    @forelse ($items as $item)
                        @php
                            $isFolder = (bool) ($item['is_folder'] ?? false);
                            $isProcessable = (bool) ($item['is_processable'] ?? false);
                            $isWorkspaceFile = (bool) ($item['is_google_workspace_file'] ?? false);
                            $sizeBytes = $item['size_bytes'] ?? null;
                            $sizeLabel = $sizeBytes !== null
                                ? number_format(max(((int) $sizeBytes) / 1024, 0.1), 1).' KB'
                                : 'Ukuran tidak tersedia';
                            $modifiedLabel = ! empty($item['modified_time'])
                                ? \Illuminate\Support\Carbon::parse($item['modified_time'])->format('d M Y H:i')
                                : null;
                        @endphp
                        <article class="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm transition hover:border-ista-primary/30 dark:border-gray-800 dark:bg-gray-900 md:flex-row md:items-center md:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $isFolder ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300' : ($isWorkspaceFile ? 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300' : 'bg-stone-100 text-stone-600 dark:bg-gray-800 dark:text-gray-300') }}">
                                    @if ($isFolder)
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />
                                        </svg>
                                    @elseif ($isWorkspaceFile)
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 3v5h5" />
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 3v5h5" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 13h6M9 17h6" />
                                        </svg>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-sm font-semibold text-stone-900 dark:text-gray-100">{{ $item['name'] }}</h2>
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $isFolder ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : ($isWorkspaceFile ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300' : 'bg-stone-100 text-stone-600 dark:bg-gray-800 dark:text-gray-300') }}">
                                            {{ $isFolder ? 'Folder' : ($isWorkspaceFile ? 'Google Workspace' : 'File') }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-stone-500 dark:text-gray-400">
                                        {{ $sizeLabel }}
                                        @if ($modifiedLabel)
                                            <span class="mx-1">·</span>
                                            {{ $modifiedLabel }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($isFolder)
                                    <button type="button"
                                            wire:click='goToFolder(@js($item["id"]), @js($item["name"]))'
                                            class="inline-flex items-center justify-center rounded-lg bg-ista-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-stone-800">
                                        Buka Folder
                                    </button>
                                @else
                                    @if (! empty($item['web_view_link']))
                                        <a href="{{ $item['web_view_link'] }}" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                            Buka di Drive
                                        </a>
                                    @endif

                                    <button type="button"
                                            wire:click='processFile(@js($item["id"]))'
                                            @disabled(! $isProcessable)
                                            class="inline-flex items-center justify-center rounded-lg {{ $isProcessable ? 'bg-ista-primary text-white hover:bg-stone-800' : 'bg-stone-100 text-stone-400 dark:bg-gray-800 dark:text-gray-500' }} px-3 py-2 text-sm font-semibold transition disabled:cursor-not-allowed">
                                        Proses dengan AI
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-8 text-center text-sm text-stone-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                            Tidak ada file di folder ini.
                        </div>
                    @endforelse
                </div>

                <div class="mt-6 flex items-center justify-between border-t border-stone-200 pt-4 text-sm text-stone-500 dark:border-gray-800 dark:text-gray-400">
                    <span>Folder tujuan upload default: {{ $defaultUploadFolderName ?? 'ISTA AI' }}</span>
                    @if ($sharedDriveId)
                        <span class="break-all">Shared Drive: {{ $sharedDriveId }}</span>
                    @endif
                </div>
            </section>
        @endif
    </div>
</div>
