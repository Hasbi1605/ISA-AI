{{-- Memo AI Chat Panel (Center Column) --}}
<div class="flex flex-col w-full lg:w-[400px] flex-shrink-0 border-r border-stone-200/60 dark:border-[#1E293B] bg-white/50 dark:bg-gray-900/50 overflow-hidden">

    {{-- Header with sidebar toggle and brand title --}}
    <div class="h-[61px] flex-shrink-0 flex items-center px-3 sm:px-5 border-b border-stone-200/60 dark:border-[#1E293B]/70 backdrop-blur-sm">
        {{-- Left: sidebar toggle + brand --}}
        <div class="flex items-center gap-2">
            <button type="button" @click="showMemoSidebar = !showMemoSidebar" class="p-2 rounded-[10px] hover:bg-[#F1F5F9] dark:hover:bg-gray-800 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-stone-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h7" />
                </svg>
            </button>
            <div class="ista-brand-title text-lg text-ista-primary not-italic">ISTA <span class="font-light italic text-ista-gold">AI</span></div>
        </div>
    </div>

    {{-- Context Form (Jenis + Judul) --}}
    <div class="px-4 py-3 border-b border-stone-100 dark:border-gray-800/60 bg-stone-50/50 dark:bg-gray-900/30">
        <div class="flex gap-2">
            <div class="flex-shrink-0 w-[130px]">
                <label class="text-[10.5px] font-bold text-stone-500 dark:text-gray-400 uppercase tracking-wider">Jenis</label>
                <select wire:model="memoType" class="mt-1 w-full rounded-lg border-stone-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-[12px] py-1.5 focus:border-ista-primary focus:ring-ista-primary/20">
                    @foreach ($memoTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-0">
                <label class="text-[10.5px] font-bold text-stone-500 dark:text-gray-400 uppercase tracking-wider">Judul</label>
                <input type="text" wire:model="title" placeholder="Contoh: Rapat Koordinasi Mingguan"
                       class="mt-1 w-full rounded-lg border-stone-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-[12px] py-1.5 focus:border-ista-primary focus:ring-ista-primary/20">
            </div>
        </div>
    </div>

    {{-- Chat Messages Area --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4" x-ref="memoChatBox" id="memo-chat-box">
        @foreach ($memoChatMessages as $index => $msg)
            <div wire:key="memo-msg-{{ $index }}" class="flex gap-2.5 {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                @if ($msg['role'] === 'assistant')
                    {{-- AI Avatar: ISTA logo matching chat page --}}
                    <div class="flex-shrink-0 mt-1">
                        <div class="h-8 w-8 rounded-full bg-white border border-stone-200 shadow-sm p-1 flex items-center justify-center dark:bg-gray-900 dark:border-gray-700">
                            <img src="{{ asset('images/ista/logo.png') }}" alt="ISTA AI" class="h-full w-full object-contain" />
                        </div>
                    </div>
                @endif

                <div class="max-w-[85%] {{ $msg['role'] === 'user'
                    ? 'bg-ista-primary text-white rounded-2xl rounded-br-md px-4 py-2.5'
                    : 'bg-stone-100 dark:bg-gray-800 text-stone-800 dark:text-gray-200 rounded-2xl rounded-bl-md px-4 py-2.5' }}">
                    <p class="text-[13px] leading-relaxed whitespace-pre-wrap">{{ $msg['content'] }}</p>
                    <span class="text-[10px] mt-1 block {{ $msg['role'] === 'user' ? 'text-white/60' : 'text-stone-400 dark:text-gray-500' }}">{{ $msg['timestamp'] }}</span>
                </div>

                @if ($msg['role'] === 'user')
                    {{-- User Avatar matching chat page --}}
                    <div class="flex-shrink-0 mt-1">
                        <div class="h-8 w-8 rounded-full bg-[#E2E8F0] dark:bg-white text-[#62748E] dark:text-black flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m12-10a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Generating indicator --}}
        @if ($isGenerating)
            <div class="flex gap-2.5 justify-start">
                <div class="flex-shrink-0 mt-1">
                    <div class="h-8 w-8 rounded-full bg-white border border-stone-200 shadow-sm p-1 flex items-center justify-center dark:bg-gray-900 dark:border-gray-700">
                        <span class="h-3.5 w-3.5 rounded-full border-2 border-ista-primary/70 border-t-transparent animate-spin"></span>
                    </div>
                </div>
                <div class="bg-stone-100 dark:bg-gray-800 rounded-2xl rounded-bl-md px-4 py-2.5">
                    <p class="text-[13px] text-stone-500 dark:text-gray-400">Sedang membuat draft memo...</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Chat Input --}}
    <div class="flex-shrink-0 border-t border-stone-200/60 dark:border-[#1E293B] bg-white dark:bg-gray-900 px-4 py-3">
        <form wire:submit="sendMemoChat" class="rounded-xl border border-stone-200/70 bg-stone-50/80 p-2 shadow-sm transition focus-within:border-ista-primary/40 focus-within:ring-2 focus-within:ring-ista-primary/10 dark:border-gray-700 dark:bg-gray-800/80">
            <div class="relative">
                <textarea
                    wire:model="memoPrompt"
                    x-ref="memoInput"
                    @keydown.enter="if(!$event.shiftKey) { $event.preventDefault(); $wire.sendMemoChat(); }"
                    placeholder="Ketik instruksi memo..."
                    rows="1"
                    class="w-full resize-none border-0 bg-transparent text-[13px] px-2 py-2 focus:border-transparent focus:ring-0 transition-all placeholder:text-stone-400 dark:placeholder:text-gray-500 dark:text-gray-100"
                    style="min-height: 42px; max-height: 120px; overflow-y: auto; outline: none; box-shadow: none;"
                    x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                ></textarea>
            </div>
            <div class="mt-1 flex items-center justify-end">
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="sendMemoChat,generateFromChat"
                        :disabled="$wire.isGenerating"
                        class="h-[34px] w-[34px] rounded-full bg-ista-primary text-white flex items-center justify-center hover:bg-ista-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                    <img src="{{ asset('images/icons/send-light.svg') }}" alt="" class="h-[17px] w-[17px] brightness-0 invert" />
                </button>
            </div>
        </form>
    </div>
</div>
