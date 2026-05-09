<div class="min-h-screen w-full bg-stone-50 text-stone-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="flex min-h-screen w-full flex-col">
        <header class="flex h-16 shrink-0 items-center justify-between border-b border-stone-200 bg-white px-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('memos.index') }}" wire:navigate class="rounded-lg p-2 text-stone-500 hover:bg-stone-100 hover:text-ista-primary dark:text-gray-400 dark:hover:bg-gray-800">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-stone-500 dark:text-gray-400">Canvas Memo</p>
                    <h1 class="truncate text-base font-semibold">{{ $memo?->title ?: 'Draft baru' }}</h1>
                </div>
            </div>
            @if ($memo)
                <div class="flex gap-2">
                    <a href="{{ route('memos.download', $memo) }}" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">DOCX</a>
                    <a href="{{ route('memos.export.pdf', $memo) }}" class="rounded-lg bg-ista-primary px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800">PDF</a>
                </div>
            @endif
        </header>

        <main class="grid flex-1 overflow-hidden lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside class="border-b border-stone-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 lg:border-b-0 lg:border-r">
                <form wire:submit="generate" class="space-y-4">
                    <div>
                        <label for="memoType" class="text-sm font-semibold text-stone-700 dark:text-gray-200">Jenis memo</label>
                        <select id="memoType" wire:model="memoType" class="mt-2 w-full rounded-lg border-stone-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-950">
                            @foreach ($memoTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('memoType') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="title" class="text-sm font-semibold text-stone-700 dark:text-gray-200">Judul</label>
                        <input id="title" wire:model="title" type="text" class="mt-2 w-full rounded-lg border-stone-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Contoh: Rapat Koordinasi Mingguan">
                        @error('title') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="context" class="text-sm font-semibold text-stone-700 dark:text-gray-200">Instruksi</label>
                        <textarea id="context" wire:model="context" rows="9" class="mt-2 w-full resize-none rounded-lg border-stone-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Tuliskan tujuan, penerima, poin penting, dan nada bahasa yang diinginkan."></textarea>
                        @error('context') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="generate" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-ista-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 disabled:opacity-60">
                        <span wire:loading.remove wire:target="generate">Generate draft</span>
                        <span wire:loading.inline-flex wire:target="generate" class="items-center gap-2">
                            <span class="h-4 w-4 rounded-full border-2 border-white/70 border-t-transparent animate-spin"></span>
                            Membuat draft
                        </span>
                    </button>
                </form>
            </aside>

            <section class="min-h-[640px] bg-stone-100 dark:bg-gray-950">
                @if ($editorConfig)
                    <div
                        wire:ignore
                        wire:key="onlyoffice-editor-{{ md5($editorConfig['document']['key'] ?? '') }}"
                        class="h-full min-h-[640px]"
                        x-data="{
                            config: @js($editorConfig),
                            apiUrl: @js($onlyOfficeApiUrl),
                            containerId: 'onlyoffice-editor-{{ md5($editorConfig['document']['key'] ?? '') }}',
                            editor: null,
                            load() {
                                this.destroy();
                                const boot = () => {
                                    const container = document.getElementById(this.containerId);
                                    if (container) { container.innerHTML = ''; }
                                    this.editor = new DocsAPI.DocEditor(this.containerId, this.config);
                                };
                                if (window.DocsAPI) { boot(); return; }
                                const script = document.createElement('script');
                                script.src = this.apiUrl;
                                script.onload = boot;
                                document.head.appendChild(script);
                            },
                            destroy() {
                                if (this.editor && typeof this.editor.destroyEditor === 'function') {
                                    this.editor.destroyEditor();
                                }
                                this.editor = null;
                            }
                        }"
                        x-init="load()"
                    >
                        <div id="onlyoffice-editor-{{ md5($editorConfig['document']['key'] ?? '') }}" class="h-full min-h-[640px] w-full"></div>
                    </div>
                @else
                    <div class="flex h-full min-h-[640px] items-center justify-center px-6 text-center">
                        <div>
                            <h2 class="text-lg font-semibold">Belum ada draft aktif</h2>
                            <p class="mt-2 max-w-md text-sm text-stone-600 dark:text-gray-400">Isi form di sebelah kiri untuk membuat DOCX dan membuka editor OnlyOffice.</p>
                        </div>
                    </div>
                @endif
            </section>
        </main>
    </div>
</div>
