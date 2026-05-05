{{-- Memo Preview / Editor Panel (Right Column, flex-1) --}}
<div class="flex-1 flex flex-col min-w-0 bg-stone-50 dark:bg-gray-950 overflow-hidden">

    {{-- Preview/Editor Toggle Header --}}
    <div class="relative z-30 min-h-[61px] flex-shrink-0 flex items-center justify-between gap-3 px-5 border-b border-stone-200/60 bg-white/85 backdrop-blur-sm dark:border-[#1E293B]/70 dark:bg-gray-800/85">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            <div class="flex items-center gap-1">
            {{-- Preview Tab --}}
            <button type="button"
                    wire:click="switchPreviewMode('preview')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[12.5px] font-semibold transition-all
                        {{ $previewMode === 'preview'
                            ? 'bg-white dark:bg-gray-800 text-stone-800 dark:text-gray-200 shadow-sm border-stone-200 dark:border-gray-700'
                            : 'text-stone-500 dark:text-gray-400 hover:text-stone-700 dark:hover:text-gray-300 border-transparent hover:border-stone-200 dark:hover:border-gray-700' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Preview
            </button>
            {{-- Editor Tab --}}
            <button type="button"
                    wire:click="switchPreviewMode('editor')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[12.5px] font-semibold transition-all
                        {{ $previewMode === 'editor'
                            ? 'bg-white dark:bg-gray-800 text-stone-800 dark:text-gray-200 shadow-sm border-stone-200 dark:border-gray-700'
                            : 'text-stone-500 dark:text-gray-400 hover:text-stone-700 dark:hover:text-gray-300 border-transparent hover:border-stone-200 dark:hover:border-gray-700' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Editor
            </button>
            </div>
        </div>

        {{-- Actions --}}
        @if ($activeMemoId)
            <div class="flex flex-shrink-0 items-center gap-2">
                <button type="button" wire:click="regenerate" wire:loading.attr="disabled" wire:target="regenerate,generateFromChat"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-200 dark:border-gray-700 text-[12px] font-semibold text-stone-600 dark:text-gray-300 hover:bg-stone-100 dark:hover:bg-gray-800 transition-all disabled:opacity-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span wire:loading.remove wire:target="regenerate">Regenerate</span>
                    <span wire:loading wire:target="regenerate">...</span>
                </button>
                <a href="{{ route('memos.download', $activeMemoId) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-200 dark:border-gray-700 text-[12px] font-semibold text-stone-600 dark:text-gray-300 hover:bg-stone-100 dark:hover:bg-gray-800 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    DOCX
                </a>
                <a href="{{ route('memos.export.pdf', $activeMemoId) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-ista-primary text-[12px] font-semibold text-white hover:bg-ista-dark transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    PDF
                </a>
            </div>
        @endif
    </div>

    {{-- Content Area --}}
    <div class="flex-1 overflow-y-auto">
        @if ($previewMode === 'preview')
            {{-- Rich-text Preview --}}
            @if ($previewHtml)
                <div class="max-w-3xl mx-auto p-8">
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-stone-200/60 dark:border-gray-800 p-8 min-h-[500px]">
                        {{-- Memo Type Badge --}}
                        <div class="mb-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-ista-primary/10 text-ista-primary text-[11px] font-bold uppercase tracking-wider">
                                {{ $memoTypes[$memoType] ?? $memoType }}
                            </span>
                        </div>
                        {{-- Title --}}
                        <h1 class="text-xl font-bold text-stone-900 dark:text-gray-100 mb-6">{{ $title ?: 'Untitled' }}</h1>
                        {{-- Content --}}
                        <div class="prose prose-stone dark:prose-invert prose-sm max-w-none leading-relaxed">
                            {{-- $previewHtml must be escaped or trusted static markup before it reaches this view. --}}
                            {!! $previewHtml !!}
                        </div>
                    </div>
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex h-full items-center justify-center px-6">
                    <div class="text-center max-w-sm">
                        <div class="mx-auto h-16 w-16 rounded-2xl bg-stone-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-stone-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-[15px] font-semibold text-stone-700 dark:text-gray-300">Belum ada preview</h3>
                        <p class="mt-2 text-[13px] text-stone-500 dark:text-gray-400 leading-relaxed">
                            Isi form konteks di panel kiri, lalu ketik instruksi di chat untuk membuat draft memo. Preview akan muncul di sini setelah draft digenerate.
                        </p>
                    </div>
                </div>
            @endif
        @else
            {{-- OnlyOffice Editor --}}
            @if ($editorConfig)
                <div
                    wire:ignore
                    wire:key="memo-editor-{{ $activeMemoId }}-{{ $previewMode }}"
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h3 class="text-[15px] font-semibold text-stone-700 dark:text-gray-300">Editor belum tersedia</h3>
                        <p class="mt-2 text-[13px] text-stone-500 dark:text-gray-400 leading-relaxed">
                            Generate draft memo terlebih dahulu melalui chat, kemudian editor OnlyOffice akan tersedia untuk mengedit dokumen DOCX secara langsung.
                        </p>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
