<div
    x-data="memoWorkspace"
    x-on:memo-document-ready.window="collapseMemoSidebarForDocument()"
    class="flex w-full h-full overflow-hidden"
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
