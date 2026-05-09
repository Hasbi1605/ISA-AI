<div
    x-data="memoWorkspace"
    x-on:memo-document-ready.window="collapseMemoSidebarForDocument()"
    class="chat-viewport flex w-full h-full overflow-hidden text-stone-800 dark:text-gray-100 font-sans transition-colors duration-300 relative ista-display-sans bg-stone-50/50 dark:bg-gray-900"
    style="background-image: url('{{ asset('images/ista/dashboard-grid.png') }}'); background-size: 8px 8px;"
>

    {{-- LEFT SIDEBAR: Memo History --}}
    @include('livewire.memos.partials.memo-history-sidebar')

    {{-- CENTER: AI Chat Panel --}}
    @include('livewire.memos.partials.memo-chat')

    {{-- RIGHT: Document Panel --}}
    @include('livewire.memos.partials.memo-preview-panel')

    <div
        x-show="isMobile && showMemoSidebar"
        x-transition.opacity
        @click="showMemoSidebar = false"
        class="fixed inset-0 bg-black/50 z-40"
        style="display:none;"
    ></div>

</div>
