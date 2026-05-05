{{-- Tab Toggle: Chat / Memo --}}
<div class="inline-flex items-center rounded-full border border-stone-200/80 bg-white/80 p-1 shadow-sm backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/80">
    <button
        type="button"
        @click="$dispatch('chat-tab-switch', { tab: 'chat' })"
        :aria-pressed="activeTab === 'chat' ? 'true' : 'false'"
        :class="activeTab === 'chat'
            ? 'bg-ista-primary text-white shadow-sm'
            : 'text-stone-500 hover:text-stone-700 dark:text-gray-400 dark:hover:text-gray-200'"
        class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-[13px] font-semibold transition-all duration-200"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        Chat
    </button>
    <button
        type="button"
        @click="$dispatch('chat-tab-switch', { tab: 'memo' })"
        :aria-pressed="activeTab === 'memo' ? 'true' : 'false'"
        :class="activeTab === 'memo'
            ? 'bg-ista-primary text-white shadow-sm'
            : 'text-stone-500 hover:text-stone-700 dark:text-gray-400 dark:hover:text-gray-200'"
        class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-[13px] font-semibold transition-all duration-200"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Memo
    </button>
</div>
