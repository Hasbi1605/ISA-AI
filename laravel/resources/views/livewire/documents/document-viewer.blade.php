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
                    <button type="button"
                            @click="close()"
                            class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            aria-label="Tutup viewer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

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
                    @elseif (in_array($kind, ['docx', 'xlsx'], true))
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
