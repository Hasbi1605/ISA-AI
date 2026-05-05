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

    {{-- Dynamic Configuration / Chat Area --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4" x-ref="memoChatBox" id="memo-chat-box">
        @if ($activeMemoId)
            <div class="rounded-xl border border-stone-200/70 bg-white/80 p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10.5px] font-bold uppercase tracking-wider text-stone-400 dark:text-gray-500">Memo aktif</p>
                        <p class="mt-1 truncate text-[13.5px] font-semibold text-stone-800 dark:text-gray-100">{{ $title ?: 'Tanpa hal' }}</p>
                        <p class="mt-1 text-[11.5px] text-stone-500 dark:text-gray-400">{{ $memoNumber ?: 'Nomor belum diisi' }} · {{ $memoDate ?: 'Tanggal belum diisi' }}</p>
                    </div>
                    <button type="button"
                            wire:click="$toggle('showMemoConfiguration')"
                            class="shrink-0 rounded-lg border border-stone-200 px-2.5 py-1.5 text-[11px] font-semibold text-stone-600 transition hover:bg-stone-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        {{ $showMemoConfiguration ? 'Tutup' : 'Edit' }}
                    </button>
                </div>
            </div>
        @endif

        @if (! $activeMemoId || $showMemoConfiguration)
            <form wire:submit="generateConfiguredMemo" class="chat-form rounded-xl border border-stone-200/70 bg-white/90 p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/80">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[14px] font-bold text-stone-800 dark:text-gray-100">Konfigurasi Memo</h2>
                        <p class="mt-1 text-[12px] leading-relaxed text-stone-500 dark:text-gray-400">Isi data resmi terlebih dahulu, lalu gunakan chat untuk revisi setelah draft tersedia.</p>
                    </div>
                    <span class="rounded-md bg-stone-100 px-2 py-1 text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:bg-gray-800 dark:text-gray-400">Manual</span>
                </div>

                <div class="space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Jenis</label>
                            <select wire:model="memoType" class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                @foreach ($memoTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('memoType') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Format</label>
                            <select wire:model="memoPageSize" class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                @foreach ($memoPageSizes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('memoPageSize') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Nomor Memo</label>
                        <input type="text" wire:model="memoNumber" placeholder="M-02/I-Yog/IT.02/04/2026"
                               class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        @error('memoNumber') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Yth.</label>
                        <input type="text" wire:model="memoRecipient" placeholder="Kepala Pusat Pengembangan dan Layanan Sistem Informasi"
                               class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        @error('memoRecipient') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Dari</label>
                        <input type="text" wire:model="memoSender"
                               class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        @error('memoSender') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_150px]">
                        <div>
                            <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Hal</label>
                            <input type="text" wire:model="title" placeholder="Penyampaian Nama PIC Aplikasi Virtual Meeting"
                                   class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            @error('title') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Tanggal</label>
                            <input type="text" wire:model="memoDate"
                                   class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            @error('memoDate') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Dasar / Konteks</label>
                        <textarea wire:model="memoBasis" rows="2" placeholder="Contoh: Menindaklanjuti memorandum Bapak nomor ..."
                                  class="mt-1 w-full resize-none rounded-lg border-stone-200 bg-white text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
                        @error('memoBasis') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Isi / Poin Wajib</label>
                        <textarea wire:model="memoContent" rows="4" placeholder="Tuliskan data nama, NIP, jabatan, permohonan, pertimbangan, atau poin bernomor yang wajib masuk."
                                  class="mt-1 w-full resize-none rounded-lg border-stone-200 bg-white text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
                        @error('memoContent') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Penutup</label>
                        <input type="text" wire:model="memoClosing"
                               class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        @error('memoClosing') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Penandatangan</label>
                            <input type="text" wire:model="memoSignatory"
                                   class="mt-1 w-full rounded-lg border-stone-200 bg-white py-2 text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            @error('memoSignatory') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Tembusan</label>
                            <textarea wire:model="memoCarbonCopy" rows="2" placeholder="Satu tembusan per baris"
                                      class="mt-1 w-full resize-none rounded-lg border-stone-200 bg-white text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
                            @error('memoCarbonCopy') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-[10.5px] font-bold uppercase tracking-wider text-stone-500 dark:text-gray-400">Catatan Tambahan</label>
                        <textarea wire:model="memoAdditionalInstruction" rows="2" placeholder="Opsional: arahkan nada, panjang, atau detail khusus."
                                  class="mt-1 w-full resize-none rounded-lg border-stone-200 bg-white text-[12.5px] focus:border-ista-primary focus:ring-ista-primary/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
                        @error('memoAdditionalInstruction') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="generateConfiguredMemo,generateFromChat"
                        :disabled="$wire.isGenerating"
                        class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-ista-primary px-4 py-2.5 text-[13px] font-semibold text-white transition hover:bg-ista-dark disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="generateConfiguredMemo,generateFromChat">{{ $activeMemoId ? 'Regenerate dari Konfigurasi' : 'Generate Memo' }}</span>
                    <span wire:loading wire:target="generateConfiguredMemo,generateFromChat">Memproses...</span>
                </button>
            </form>
        @endif

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

    {{-- Revision Chat Input --}}
    <div class="chat-composer-safe flex-shrink-0 px-4 pt-2 bg-transparent w-full">
        @if ($activeMemoId)
            <form wire:submit="sendMemoChat" class="chat-form relative rounded-xl shadow-sm bg-white dark:bg-gray-800 border border-stone-200/60 dark:border-gray-700 transition-colors">
                <div class="px-3 pb-3 pt-3 w-full">
                    <textarea
                        wire:model="memoPrompt"
                        x-ref="memoInput"
                        @keydown.enter="if(!$event.shiftKey) { $event.preventDefault(); $wire.sendMemoChat(); }"
                        placeholder="Tulis revisi untuk memo ini..."
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
        @endif
        <div class="text-center mt-3 text-[11px] text-[#94A3B8] dark:text-[#64748B]">
            ISTA AI dapat keliru. Mohon verifikasi kembali informasi yang penting.
        </div>
    </div>
</div>
