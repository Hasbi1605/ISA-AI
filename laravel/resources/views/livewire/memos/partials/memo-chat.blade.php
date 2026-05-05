{{-- Memo AI Chat Panel (Center Column) --}}
<div class="flex flex-col w-full lg:w-[420px] xl:w-[520px] flex-shrink-0 border-r border-stone-200/60 dark:border-[#1E293B] bg-transparent dark:bg-gray-900/30 overflow-hidden">

    {{-- Header with sidebar toggle, brand, tab toggle, and theme toggle --}}
    <div class="min-h-[61px] flex-shrink-0 flex items-center justify-between gap-2 px-3 sm:px-5 border-b border-stone-200/60 dark:border-[#1E293B]/70 bg-white/85 dark:bg-gray-800/85 backdrop-blur-sm">
        <div class="flex min-w-0 items-center gap-2">
            <button type="button" @click="showMemoSidebar = !showMemoSidebar" class="p-2 rounded-[10px] hover:bg-[#F1F5F9] dark:hover:bg-gray-800 transition-colors flex-shrink-0" aria-label="Toggle memo sidebar">
                <img src="{{ asset('images/icons/collapse-left-light.svg') }}" alt="" class="h-5 w-5 dark:hidden transition-transform duration-300 ease-in-out" :class="showMemoSidebar ? 'rotate-0' : 'rotate-180'" />
                <img src="{{ asset('images/icons/collapse-left-dark.svg') }}" alt="" class="h-5 w-5 hidden dark:block transition-transform duration-300 ease-in-out" :class="showMemoSidebar ? 'rotate-0' : 'rotate-180'" />
            </button>
            <div class="ista-brand-title text-xl text-ista-primary not-italic transition-transform duration-300">ISTA <span class="font-light italic text-ista-gold">AI</span></div>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-2">
            <button type="button" @click="darkMode = !darkMode" class="p-2 rounded-[10px] hover:bg-[#F1F5F9] dark:hover:bg-gray-800 transition-colors" aria-label="Toggle dark mode">
                <svg x-show="darkMode === false" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.5M12 18.5V21M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M3 12h2.5M18.5 12H21M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8M12 16a4 4 0 100-8 4 4 0 000 8z" />
                </svg>
                <svg x-show="darkMode === true" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" />
                </svg>
            </button>

            @include('livewire.chat.partials.chat-memo-tab-toggle')
        </div>
    </div>

    {{-- Context Form (Jenis + Judul) --}}
    <div class="px-4 py-3 border-b border-stone-100 dark:border-gray-800/60 bg-white/50 dark:bg-gray-900/40">
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
    <div class="flex-1 overflow-y-auto px-4 py-5 space-y-5" x-ref="memoChatBox" id="memo-chat-box">
        @foreach ($memoChatMessages as $index => $msg)
            @php
                $isUserMessage = $msg['role'] === 'user';
            @endphp

            <div wire:key="memo-msg-{{ $index }}" class="flex {{ $isUserMessage ? 'justify-end' : 'justify-start' }}">
                <div class="w-full flex items-start gap-2.5 {{ $isUserMessage ? 'flex-row-reverse' : '' }}">
                    <div class="shrink-0 h-8 w-8 rounded-full flex items-center justify-center {{ $isUserMessage ? 'bg-[#E2E8F0] dark:bg-white text-[#62748E] dark:text-black' : 'bg-white border border-stone-200 shadow-sm p-1' }}">
                        @if ($isUserMessage)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m12-10a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        @else
                            <img src="{{ asset('images/ista/logo.png') }}" alt="ISTA AI" class="h-full w-full object-contain" />
                        @endif
                    </div>

                    <div class="flex max-w-[82%] flex-col gap-1 {{ $isUserMessage ? 'items-end text-right' : 'items-start text-left' }}">
                        <div class="flex items-center gap-2 mb-1 {{ $isUserMessage ? 'justify-end' : 'justify-start' }}">
                            <span class="text-[13px] font-bold text-stone-800 dark:text-[#F8FAFC]">{{ $isUserMessage ? 'Anda' : 'ISTA AI' }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-[#64748B]">{{ $msg['timestamp'] }}</span>
                        </div>

                        <div class="{{ $isUserMessage
                            ? 'bg-ista-primary text-white rounded-xl rounded-br-md px-4 py-3'
                            : 'bg-white/80 backdrop-blur-sm dark:bg-gray-800 border border-stone-200/60 dark:border-gray-800 text-stone-700 dark:text-gray-100 rounded-xl rounded-bl-md px-4 py-3' }}">
                            <p class="text-[14px] leading-relaxed whitespace-pre-wrap">{{ $msg['content'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Generating indicator --}}
        @if ($isGenerating)
            <div class="flex justify-start">
                <div class="w-full flex items-start gap-2.5">
                    <div class="shrink-0 h-8 w-8 rounded-full bg-white border border-stone-200 shadow-sm p-1 flex items-center justify-center">
                        <img src="{{ asset('images/ista/logo.png') }}" alt="ISTA AI" class="h-full w-full object-contain" />
                    </div>
                    <div class="flex max-w-[82%] flex-col gap-1 items-start text-left">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[13px] font-bold text-stone-800 dark:text-[#F8FAFC]">ISTA AI</span>
                            <span class="h-3 w-3 rounded-full border border-current border-t-transparent animate-spin text-[#64748B] dark:text-[#94A3B8]"></span>
                        </div>
                        <div class="bg-white/80 backdrop-blur-sm dark:bg-gray-800 border border-stone-200/60 dark:border-gray-800 rounded-xl rounded-bl-md px-4 py-3">
                            <p class="text-[14px] text-stone-500 dark:text-gray-400">Sedang membuat draft memo...</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Chat Input --}}
    <div class="chat-composer-safe flex-shrink-0 px-4 pt-2 bg-transparent w-full">
        <form wire:submit="sendMemoChat" class="chat-form relative rounded-xl shadow-sm bg-white dark:bg-gray-800 border border-stone-200/60 dark:border-gray-700 transition-colors">
            <div class="px-3 pb-3 pt-3 w-full">
                <textarea
                    wire:model="memoPrompt"
                    x-ref="memoInput"
                    @keydown.enter="if(!$event.shiftKey) { $event.preventDefault(); $wire.sendMemoChat(); }"
                    placeholder="Ketik instruksi memo..."
                    rows="1"
                    class="chat-input w-full max-h-[120px] min-h-[44px] bg-transparent border-none focus:ring-0 focus:outline-none focus:border-transparent focus-visible:ring-0 focus-visible:outline-none resize-none text-[14px] text-stone-800 dark:text-[#F8FAFC] placeholder-[#94A3B8] dark:placeholder-[#64748B] px-2 py-[10px] hover:bg-transparent focus:bg-transparent"
                    style="outline: none !important; box-shadow: none !important;"
                    x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                ></textarea>

                <div class="mt-2 flex items-center justify-end">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendMemoChat,generateFromChat"
                            :disabled="$wire.isGenerating"
                            class="bg-ista-primary hover:bg-ista-dark dark:bg-ista-primary dark:hover:bg-ista-dark disabled:opacity-50 disabled:cursor-not-allowed rounded-full transition-all duration-300 h-[32px] w-[32px] flex items-center justify-center group">
                        <img src="{{ asset('images/icons/send-light.svg') }}" alt="" class="h-[17px] w-[17px] dark:hidden brightness-0 invert" />
                        <img src="{{ asset('images/icons/send-dark.svg') }}" alt="" class="h-[17px] w-[17px] hidden dark:block brightness-0 invert" />
                    </button>
                </div>
            </div>
        </form>
        <div class="text-center mt-3 text-[11px] text-[#94A3B8] dark:text-[#64748B]">
            ISTA AI dapat keliru. Mohon verifikasi kembali informasi yang penting.
        </div>
    </div>
</div>
