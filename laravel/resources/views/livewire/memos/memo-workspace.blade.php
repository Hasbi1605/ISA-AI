<div x-data="memoWorkspace" class="flex w-full h-full overflow-hidden">

    {{-- LEFT SIDEBAR: Memo History --}}
    @include('livewire.memos.partials.memo-history-sidebar')

    {{-- CENTER: AI Chat Panel --}}
    @include('livewire.memos.partials.memo-chat')

    {{-- RIGHT: Preview / Editor Panel --}}
    @include('livewire.memos.partials.memo-preview-panel')

</div>
