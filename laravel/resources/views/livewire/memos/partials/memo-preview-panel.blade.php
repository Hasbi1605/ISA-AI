{{-- Memo Document Panel (Right Column, flex-1) --}}
<div class="flex-1 flex flex-col min-w-0 bg-stone-50 dark:bg-gray-950 overflow-hidden">

    {{-- Document Header --}}
    <div class="relative z-30 min-h-[61px] flex-shrink-0 flex items-center justify-between gap-3 px-5 border-b border-stone-200/60 bg-white/85 backdrop-blur-sm dark:border-[#1E293B]/70 dark:bg-gray-800/85">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            <div class="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-3 py-1.5 text-[12.5px] font-semibold text-stone-800 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Dokumen
            </div>
        </div>

        {{-- Actions --}}
        @if ($activeMemoId)
            <div
                class="flex flex-shrink-0 items-center gap-2"
                x-data="memoDocumentDownloads"
            >
                <button type="button" wire:click="regenerate" wire:loading.attr="disabled" wire:target="regenerate,generateFromChat"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-200 dark:border-gray-700 text-[12px] font-semibold text-stone-600 dark:text-gray-300 hover:bg-stone-100 dark:hover:bg-gray-800 transition-all disabled:opacity-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span wire:loading.remove wire:target="regenerate">Regenerate</span>
                    <span wire:loading wire:target="regenerate">...</span>
                </button>
                <button type="button"
                        data-download-url="{{ route('memos.download', $activeMemoId) }}"
                        data-download-filename="{{ e(($title ?: 'memo').'.docx') }}"
                        @click="downloadMemo($el.dataset.downloadUrl, 'docx', $el.dataset.downloadFilename)"
                        :disabled="downloadLoading !== null"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-200 dark:border-gray-700 text-[12px] font-semibold text-stone-600 dark:text-gray-300 hover:bg-stone-100 dark:hover:bg-gray-800 transition-all disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="downloadLoading === 'docx'" style="display:none;" class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin" aria-hidden="true"></span>
                    <svg x-show="downloadLoading !== 'docx'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    DOCX
                </button>
                <button type="button"
                        data-download-url="{{ route('memos.export.pdf', $activeMemoId) }}"
                        data-download-filename="{{ e(($title ?: 'memo').'.pdf') }}"
                        @click="downloadMemo($el.dataset.downloadUrl, 'pdf', $el.dataset.downloadFilename)"
                        :disabled="downloadLoading !== null"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-ista-primary text-[12px] font-semibold text-white hover:bg-ista-dark transition-all disabled:cursor-not-allowed disabled:opacity-70">
                    <span x-show="downloadLoading === 'pdf'" style="display:none;" class="h-3.5 w-3.5 rounded-full border-2 border-white/70 border-t-transparent animate-spin" aria-hidden="true"></span>
                    <svg x-show="downloadLoading !== 'pdf'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    PDF
                </button>
            </div>
        @endif
    </div>

    {{-- Content Area --}}
    <div class="flex-1 overflow-y-auto">
        @if ($editorConfig)
            <div
                wire:ignore
                wire:key="memo-editor-{{ $activeMemoId }}"
                class="h-full min-h-[640px]"
                x-data="{
                    config: @js($editorConfig),
                    apiUrl: @js($onlyOfficeApiUrl),
                    editor: null,
                    load() {
                        const boot = () => { this.editor = new DocsAPI.DocEditor('memo-workspace-editor', this.config); };
                        if (window.DocsAPI) { boot(); return; }
                        const script = document.createElement('script');
                        script.src = this.apiUrl;
                        script.onload = boot;
                        document.head.appendChild(script);
                    }
                }"
                x-init="load()"
            >
                <div id="memo-workspace-editor" class="h-full min-h-[640px] w-full"></div>
            </div>
        @else
            <div class="flex h-full min-h-[400px] items-center justify-center px-6 text-center">
                <div class="max-w-sm">
                    <div class="mx-auto h-16 w-16 rounded-2xl bg-stone-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-stone-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-[15px] font-semibold text-stone-700 dark:text-gray-300">Dokumen belum tersedia</h3>
                    <p class="mt-2 text-[13px] text-stone-500 dark:text-gray-400 leading-relaxed">
                        Lengkapi konfigurasi memo, lalu generate draft. Dokumen DOCX akan tampil di sini melalui OnlyOffice.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
