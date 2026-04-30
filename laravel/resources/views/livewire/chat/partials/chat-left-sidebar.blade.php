<aside
    x-data="chatHistory({ activeConversationId: @js($currentConversationId ? (int) $currentConversationId : null), showOlderChats: $wire.entangle('showOlderChats') })"
    :class="[
        showLeftSidebar ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-full pointer-events-none',
        isMobile ? 'fixed left-0 top-0 h-full w-[288px] shadow-2xl border-r border-stone-200/60 dark:border-[#1E293B]' : (showLeftSidebar ? 'relative w-[288px] border-r border-stone-200/60 dark:border-[#1E293B]' : 'relative w-0 border-r border-transparent')
    ]"
    @click.stop
    x-on:chat-new-optimistic.window="setActiveConversation(null); loadingConversationId = null"
    x-on:conversation-activated.window="setActiveConversation($event.detail.id)"
    class="z-50 flex-shrink-0 overflow-hidden bg-white dark:bg-gray-900 flex flex-col transform-gpu will-change-[width,transform,opacity] transition-[width,transform,opacity,border-color] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]">

    <div class="flex items-center justify-between px-4 pb-2 pt-3">
        <a href="{{ route('dashboard') }}" @click="showLeftSidebar = false" class="inline-flex items-center px-1 py-2.5 font-medium text-[13px] text-gray-700 dark:text-gray-200 hover:text-amber-800 dark:hover:text-amber-300 transition-colors duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Kembali ke Beranda
        </a>
        <button type="button" x-show="isMobile" @click="showLeftSidebar = false" class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 transition-colors" aria-label="Tutup sidebar">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="p-4 pt-2 pb-5">
        <button type="button" @click="startNewChat(); if(isMobile) showLeftSidebar = false;" :disabled="isNavigating" class="w-full flex items-center justify-start px-4 py-2.5 rounded-lg border border-stone-200/60 dark:border-[#334155] dark:bg-transparent bg-white hover:bg-gray-50 dark:hover:bg-white/5 font-medium text-[13px] text-gray-700 dark:text-gray-200 transition-all duration-200 shadow-sm disabled:cursor-wait disabled:opacity-70">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-[#64748B] dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m-7-7h14" />
            </svg>
            New Chat
        </button>
    </div>

    <div class="flex-1 overflow-y-auto overflow-x-hidden px-4">
        <div class="mb-6">
            <h3 class="text-[11.6px] font-bold text-[#64748B] dark:text-[#94A3B8] uppercase tracking-wider mb-2">Today</h3>
            <ul class="space-y-1">
                @php
                    $visibleChats = $conversations->take(10);
                    $olderChats = $conversations->skip(10);
                @endphp

                @foreach($visibleChats as $conversation)
                    <li class="group relative" wire:key="chat-history-visible-{{ $conversation->id }}">
                        <button type="button" @click="loadConversation({{ $conversation->id }}); if(isMobile) showLeftSidebar = false;"
                           data-chat-history-id="{{ $conversation->id }}"
                           :disabled="isNavigating"
                           :class="{ 'is-active': isActive({{ $conversation->id }}) }"
                           class="chat-history-item {{ (int) $currentConversationId === (int) $conversation->id ? 'is-active' : '' }}">
                            <img src="{{ $uiIcons['historyLight'] }}" alt="" class="h-4 w-4 mr-2.5 flex-shrink-0 dark:hidden" />
                            <img src="{{ $uiIcons['historyDark'] }}" alt="" class="h-4 w-4 mr-2.5 flex-shrink-0 hidden dark:block" />
                            <span class="truncate text-[13.2px]" title="{{ $conversation->title }}">{{ $conversation->title }}</span>
                            <span x-show="isLoading({{ $conversation->id }})" class="ml-auto h-3 w-3 rounded-full border border-current border-t-transparent animate-spin" style="display: none;"></span>
                        </button>
                        <button type="button" wire:click="deleteConversation({{ $conversation->id }})"
                                wire:confirm="Delete this chat?"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md opacity-0 group-hover:opacity-100 hover:bg-red-100 dark:hover:bg-red-500/20 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-all duration-200"
                                title="Delete chat">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        @if($olderChats->count() > 0)
        <div class="mb-6">
            <button type="button" @click="showOlderChats = !showOlderChats" class="flex items-center justify-between w-full text-left">
                <h3 class="text-[11.3px] font-bold text-[#64748B] dark:text-[#94A3B8] uppercase tracking-wider mb-2">Previous 7 Days</h3>
                <svg xmlns="http://www.w3.org/2000/svg" :class="showOlderChats ? 'rotate-180' : ''" class="h-3 w-3 text-[#64748B] dark:text-[#94A3B8] transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
                <ul x-show="showOlderChats" class="mt-1 space-y-1" style="{{ $showOlderChats ? '' : 'display: none;' }}">
                    @foreach($olderChats as $conversation)
                        <li class="group relative" wire:key="chat-history-older-{{ $conversation->id }}">
                            <button type="button" @click="loadConversation({{ $conversation->id }}); if(isMobile) showLeftSidebar = false;"
                               data-chat-history-id="{{ $conversation->id }}"
                               :disabled="isNavigating"
                               :class="{ 'is-active': isActive({{ $conversation->id }}) }"
                               class="chat-history-item {{ (int) $currentConversationId === (int) $conversation->id ? 'is-active' : '' }}">
                                <img src="{{ $uiIcons['historyLight'] }}" alt="" class="h-4 w-4 mr-2.5 flex-shrink-0 dark:hidden" />
                                <img src="{{ $uiIcons['historyDark'] }}" alt="" class="h-4 w-4 mr-2.5 flex-shrink-0 hidden dark:block" />
                                <span class="truncate text-[13.2px]" title="{{ $conversation->title }}">{{ $conversation->title }}</span>
                                <span x-show="isLoading({{ $conversation->id }})" class="ml-auto h-3 w-3 rounded-full border border-current border-t-transparent animate-spin" style="display: none;"></span>
                            </button>
                            <button type="button" wire:click="deleteConversation({{ $conversation->id }})"
                                    wire:confirm="Delete this chat?"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md opacity-0 group-hover:opacity-100 hover:bg-red-100 dark:hover:bg-red-500/20 text-gray-400 hover:text-red-600 transition-all duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </li>
                    @endforeach
                </ul>
        </div>
        @endif
    </div>

    <div class="px-4 py-6 text-sm flex flex-col gap-2">
         <a href="/profile" class="flex items-center gap-3 text-gray-700 dark:text-[#F8FAFC] hover:opacity-80 transition-opacity">
            <div class="h-8 w-8 rounded bg-ista-primary flex items-center justify-center text-white shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div>
                <h4 class="text-[13.1px] font-medium leading-tight">Pengaturan Akun</h4>
                <p class="text-[11.3px] text-gray-500 dark:text-gray-400">Kelola profil dan preferensi</p>
            </div>
         </a>
    </div>
</aside>
