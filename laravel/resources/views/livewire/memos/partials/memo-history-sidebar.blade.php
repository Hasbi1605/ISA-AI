{{-- Memo History Sidebar (Left Column ~220px) --}}
<aside
    x-show="showMemoSidebar"
    :class="[
        isMobile ? 'fixed left-0 top-0 h-full w-[240px] shadow-2xl z-50' : 'relative w-[240px]'
    ]"
    class="flex-shrink-0 flex flex-col border-r border-stone-200/60 dark:border-[#1E293B] bg-white dark:bg-gray-900 overflow-hidden transition-all duration-300"
>
    {{-- Header --}}
    <div class="flex items-center justify-between px-4 pt-4 pb-2">
        <h2 class="text-[13px] font-bold text-stone-700 dark:text-gray-200 uppercase tracking-wider">Memo</h2>
        <button type="button" x-show="isMobile" @click="showMemoSidebar = false" class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- New Memo Button --}}
    <div class="px-4 pb-3">
        <button type="button"
                wire:click="startNewMemo"
                class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-stone-200/60 dark:border-gray-700 bg-white dark:bg-transparent hover:bg-gray-50 dark:hover:bg-white/5 text-[13px] font-semibold text-stone-700 dark:text-gray-200 transition-all shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m-7-7h14" />
            </svg>
            Buat Memo Baru
        </button>
    </div>

    {{-- Memo List --}}
    <div class="flex-1 overflow-y-auto px-3">
        @forelse ($memos as $memo)
            <button
                type="button"
                wire:click="loadMemo({{ $memo->id }})"
                wire:key="memo-sidebar-{{ $memo->id }}"
                :class="{ 'bg-ista-primary/5 border-ista-primary/20 dark:bg-ista-primary/10': $wire.activeMemoId === {{ $memo->id }} }"
                class="w-full text-left px-3 py-2.5 rounded-lg mb-1 border border-transparent hover:bg-stone-50 dark:hover:bg-white/5 transition-all group"
            >
                <div class="flex items-start gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-0.5 flex-shrink-0 text-stone-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <div class="min-w-0 flex-1">
                        <p class="text-[12.5px] font-medium text-stone-800 dark:text-gray-200 truncate">{{ $memo->title }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[10.5px] font-medium px-1.5 py-0.5 rounded bg-stone-100 dark:bg-gray-800 text-stone-500 dark:text-gray-400">{{ $memo->type_label }}</span>
                            <span class="text-[10.5px] text-stone-400 dark:text-gray-500">{{ $memo->updated_at->diffForHumans(short: true) }}</span>
                        </div>
                    </div>
                </div>
            </button>
        @empty
            <div class="px-3 py-6 text-center">
                <p class="text-[12px] text-stone-400 dark:text-gray-500">Belum ada memo</p>
            </div>
        @endforelse
    </div>
</aside>
