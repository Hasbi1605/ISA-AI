<div x-data="documentViewer({ isOpen: @js($isOpen) })"
     x-on:open-document-preview.window="open($event.detail.documentId)">
        <div x-show="isVisible"
             x-transition.opacity
             class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-6 sm:px-6 lg:px-8"
             style="{{ $isOpen ? '' : 'display: none;' }}"
             role="dialog" aria-modal="true" aria-labelledby="document-viewer-title">
            <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm"
                 @click="close()"
                 aria-hidden="true"></div>

            <div class="relative w-full max-w-5xl max-h-[90vh] flex flex-col bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-stone-200 dark:border-[#1E293B] overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-stone-200 dark:border-[#1E293B]">
                    <div class="min-w-0">
                        <h2 id="document-viewer-title"
                            x-show="isLoading"
                            class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                            Membuka dokumen...
                        </h2>
                        <h2 x-show="!isLoading"
                            class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate"
                            title="{{ $document?->original_name }}">
                            {{ $document?->original_name ?? 'Dokumen tidak ditemukan' }}
                        </h2>
                        @if ($document)
                            <p class="text-[11.5px] text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ strtoupper($kind ?? '') }} · {{ $document->formatted_size ?? '' }}
                            </p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($document && in_array($kind, ['pdf', 'docx', 'xlsx', 'csv'], true))
                            @php
                                $exportBaseName = pathinfo($document->original_name ?: $document->filename, PATHINFO_FILENAME);
                                $exportBaseName = $exportBaseName !== '' ? $exportBaseName : 'dokumen';
                                $usesTableExtraction = in_array($kind, ['pdf', 'docx'], true);
                            @endphp
                            <div
                                wire:key="document-export-actions-{{ $document->id }}"
                                x-data="documentViewerExport({
                                    contentUrl: @js(route('documents.content-html', $document)),
                                    extractUrl: @js($usesTableExtraction ? route('documents.extract-tables', $document) : null),
                                    exportUrl: @js(route('documents.export')),
                                    fileName: @js($exportBaseName),
                                    preferTableExtraction: @js($usesTableExtraction),
                                })"
                                data-document-export-actions
                                class="relative flex items-center gap-2"
                                x-on:click.outside="exportMenuOpen = false; driveMenuOpen = false"
                            >
                                <button
                                    type="button"
                                    @click="toggleMenu()"
                                    :disabled="loading"
                                    class="inline-flex h-9 items-center gap-2 rounded-md border border-ista-primary/20 bg-ista-primary px-3 text-[12px] font-semibold text-white shadow-sm transition hover:bg-ista-dark disabled:cursor-wait disabled:opacity-75"
                                    :aria-label="loading ? 'Menyiapkan ekspor' : 'Ekspor'"
                                    :title="loading ? 'Menyiapkan ekspor' : 'Ekspor'"
                                >
                                    <span x-show="loading" class="h-3.5 w-3.5 rounded-full border-2 border-white/70 border-t-transparent animate-spin" aria-hidden="true"></span>
                                    <svg x-show="!loading" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M12 15V3" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="m7 10 5 5 5-5" />
                                    </svg>
                                    <span class="hidden sm:inline" x-text="loading ? 'Menyiapkan...' : 'Ekspor'">Ekspor</span>
                                </button>

                                <div
                                    x-show="exportMenuOpen"
                                    x-transition.opacity
                                    class="absolute left-0 z-30 mt-2 w-44 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                                    style="display: none; top: 2.5rem;"
                                >
                                    <button type="button" data-document-export-format="xlsx" @click="exportTablesAs('xlsx')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>XLSX</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Excel</span>
                                    </button>
                                    <button type="button" data-document-export-format="csv" @click="exportTablesAs('csv')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>CSV</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Tabel</span>
                                    </button>
                                    <button type="button" data-document-export-format="docx" @click="exportTablesAs('docx')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>DOCX</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Word</span>
                                    </button>
                                    <button type="button" data-document-export-format="pdf" @click="exportTablesAs('pdf')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>PDF</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Laporan</span>
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    @click="toggleDriveMenu()"
                                    :disabled="loading"
                                    class="inline-flex h-9 items-center gap-2 rounded-md border border-stone-300 bg-white px-3 text-[12px] font-semibold text-stone-700 shadow-sm transition hover:border-ista-primary/30 hover:bg-stone-50 disabled:cursor-wait disabled:opacity-75 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800"
                                    :aria-label="loading ? 'Menyiapkan upload ke Google Drive' : 'Upload ke GDrive Kantor'"
                                    :title="loading ? 'Menyiapkan upload ke Google Drive' : 'Upload ke GDrive Kantor'"
                                >
                                    <span x-show="loading" class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin" aria-hidden="true"></span>
                                    <svg x-show="!loading" class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="#1A73E8" d="M8.8 3.2h6.5l6.4 11.2h-6.5L8.8 3.2Z" />
                                        <path fill="#34A853" d="M2.3 14.4 8.8 3.2l3.2 5.6-3.3 5.6H2.3Z" />
                                        <path fill="#FBBC04" d="M8.7 14.4h13L18.5 20H5.5l3.2-5.6Z" />
                                    </svg>
                                    <span class="hidden sm:inline">Upload ke GDrive Kantor</span>
                                </button>

                                <div
                                    x-show="driveMenuOpen"
                                    x-transition.opacity
                                    class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                                    style="display: none; top: 2.5rem;"
                                >
                                    <div class="border-b border-stone-200 bg-stone-50 px-4 py-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-500 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-400">
                                        Upload ke Drive
                                    </div>
                                    <button type="button" data-document-drive-format="xlsx" @click="saveToGoogleDrive('xlsx')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>XLSX</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Drive</span>
                                    </button>
                                    <button type="button" data-document-drive-format="csv" @click="saveToGoogleDrive('csv')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>CSV</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Drive</span>
                                    </button>
                                    <button type="button" data-document-drive-format="docx" @click="saveToGoogleDrive('docx')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>DOCX</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Drive</span>
                                    </button>
                                    <button type="button" data-document-drive-format="pdf" @click="saveToGoogleDrive('pdf')" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-[12px] text-stone-700 transition hover:bg-stone-50 dark:text-gray-100 dark:hover:bg-gray-700/80">
                                        <span>PDF</span>
                                        <span class="text-[10px] text-[#64748B] dark:text-[#94A3B8]">Drive</span>
                                    </button>
                                </div>

                                <p x-show="error" x-transition.opacity class="absolute right-0 top-11 z-30 w-56 rounded-lg border border-rose-200 bg-white px-3 py-2 text-[11px] text-rose-600 shadow-lg dark:border-rose-500/30 dark:bg-gray-800 dark:text-rose-200" x-text="error" style="display: none;"></p>
                            </div>
                        @endif

                        <button type="button"
                                @click="close()"
                                class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                aria-label="Tutup viewer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                @if ($driveUploadResult)
                    <div class="border-b border-emerald-200 bg-emerald-50 px-5 py-3 text-[12px] text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold">Tersimpan ke Google Drive</p>
                                <p class="mt-0.5 text-[11px] opacity-90">{{ $driveUploadResult['file_name'] ?? 'File' }}</p>
                            </div>
                            @if (! empty($driveUploadResult['web_view_link']))
                                <a href="{{ $driveUploadResult['web_view_link'] }}" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-3 py-2 text-[11px] font-semibold text-white transition hover:bg-emerald-800">
                                    Buka di Drive
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($driveUploadError)
                    <div class="border-b border-rose-200 bg-rose-50 px-5 py-3 text-[12px] text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
                        {{ $driveUploadError }}
                    </div>
                @endif

                <div class="flex-1 overflow-auto bg-stone-50 dark:bg-gray-950">
                    <div x-show="isLoading"
                         class="flex flex-col items-center justify-center h-[70vh] p-8 text-center gap-3">
                        <span class="h-6 w-6 rounded-full border-2 border-stone-400 border-t-transparent animate-spin"></span>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Membuka dokumen...
                        </p>
                    </div>

                    <div x-show="!isLoading">
                    @if (! $document)
                        <div class="flex items-center justify-center h-full text-sm text-gray-500 dark:text-gray-400 p-8 text-center">
                            Dokumen tidak ditemukan atau Anda tidak memiliki izin untuk melihatnya.
                        </div>
                    @elseif ($kind === 'pdf')
                        <iframe src="{{ $streamUrl }}"
                                class="w-full h-[70vh] bg-white"
                                title="Preview {{ $document->original_name }}"></iframe>
                    @elseif (in_array($kind, ['docx', 'xlsx', 'csv'], true))
                        @if ($previewStatus === \App\Models\Document::PREVIEW_STATUS_READY)
                            <div wire:poll.30s.keep-alive
                                 class="bg-white dark:bg-gray-900 px-6 py-5"
                                 wire:ignore>
                                <iframe src="{{ $htmlUrl }}"
                                        class="w-full h-[70vh] border-0 bg-white"
                                        title="Preview {{ $document->original_name }}"
                                        sandbox="allow-same-origin"></iframe>
                            </div>
                        @elseif ($previewStatus === \App\Models\Document::PREVIEW_STATUS_FAILED)
                            <div class="flex flex-col items-center justify-center h-full p-8 text-center gap-3">
                                <p class="text-sm text-rose-600 dark:text-rose-400 font-medium">
                                    Gagal menyiapkan preview untuk dokumen ini.
                                </p>
                                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">
                                    Anda masih bisa menggunakan dokumen ini sebagai konteks AI.
                                </p>
                            </div>
                        @else
                            <div wire:poll.3s
                                 class="flex flex-col items-center justify-center h-full p-8 text-center gap-3">
                                <span class="h-6 w-6 rounded-full border-2 border-stone-400 border-t-transparent animate-spin"></span>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    Sedang menyiapkan preview dokumen…
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="flex items-center justify-center h-full text-sm text-gray-500 dark:text-gray-400 p-8 text-center">
                            Format dokumen ini belum didukung untuk preview.
                        </div>
                    @endif
                    </div>
                </div>
            </div>
        </div>
</div>
