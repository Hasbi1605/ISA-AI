<div x-data="{ 
        darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches),
        isMobile: window.matchMedia('(max-width: 1023px)').matches,
        showLeftSidebar: !window.matchMedia('(max-width: 1023px)').matches,
        showRightSidebar: !window.matchMedia('(max-width: 1023px)').matches,
        isDraggingFile: false,
        dragDepth: 0,
        dropError: '',
        sendError: '',
        messageAcked: false,
        promptDraft: @js($prompt),
        isSendingMessage: false,
        optimisticUserMessage: '',
        scrollToBottom(smooth = false) {
            const chatBox = this.$refs.chatBox;
            if (!chatBox) return;

            chatBox.scrollTo({
                top: chatBox.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto',
            });
        },
        submitPrompt(event) {
            if (event) event.preventDefault();
            if (this.isSendingMessage) return;

            const text = this.promptDraft.trim();
            if (!text) return;

            this.sendError = '';
            this.messageAcked = false;
            this.isSendingMessage = true;
            this.optimisticUserMessage = text;
            this.promptDraft = '';
            this.$dispatch('message-send');
            this.$nextTick(() => this.scrollToBottom());

            this.$wire.sendMessage(text)
                .then(() => {
                    this.$nextTick(() => this.scrollToBottom());
                })
                .catch((error) => {
                    this.optimisticUserMessage = '';

                    if (!this.messageAcked) {
                        this.promptDraft = text;
                        this.sendError = 'Pesan gagal dikirim. Periksa koneksi lalu coba lagi.';
                    } else {
                        this.sendError = 'Pesan sudah terkirim, tetapi jawaban ISTA AI gagal diproses. Coba kirim ulang prompt Anda.';
                    }

                    setTimeout(() => {
                        if (this.sendError) this.sendError = '';
                    }, 6000);

                    console.error('Send message error:', error);
                })
                .finally(() => {
                    this.isSendingMessage = false;
                    this.$dispatch('message-complete');
                    this.$nextTick(() => this.scrollToBottom());
                });
        },
        initChatBehavior() {
            this.$nextTick(() => this.scrollToBottom());

            const chatBox = this.$refs.chatBox;
            if (chatBox) {
                const observer = new MutationObserver(() => this.scrollToBottom());
                observer.observe(chatBox, { childList: true, subtree: true, characterData: true });
                window.addEventListener('beforeunload', () => observer.disconnect(), { once: true });
            }

            this.$wire.on('assistant-output', () => {
                this.$nextTick(() => this.scrollToBottom());
            });

            this.$wire.on('user-message-acked', () => {
                this.messageAcked = true;
                this.optimisticUserMessage = '';
                this.$nextTick(() => this.scrollToBottom());
            });

            // Use matchMedia for reliable responsive detection
            const mql = window.matchMedia('(max-width: 1023px)');
            const handleMqlChange = (e) => {
                const wasMobile = this.isMobile;
                this.isMobile = e.matches;
                if (wasMobile && !this.isMobile) {
                    this.showLeftSidebar = true;
                    this.showRightSidebar = true;
                } else if (!wasMobile && this.isMobile) {
                    this.showLeftSidebar = false;
                    this.showRightSidebar = false;
                }
            };
            mql.addEventListener('change', handleMqlChange);
            window.addEventListener('beforeunload', () => mql.removeEventListener('change', handleMqlChange), { once: true });
        },

        setDropError(message) {
            this.dropError = message;
            setTimeout(() => {
                if (this.dropError === message) {
                    this.dropError = '';
                }
            }, 3500);
        },
        onDragEnter(event) {
            if (!event.dataTransfer || !Array.from(event.dataTransfer.types || []).includes('Files')) return;
            this.dragDepth += 1;
            this.isDraggingFile = true;
        },
        onDragOver(event) {
            if (!event.dataTransfer || !Array.from(event.dataTransfer.types || []).includes('Files')) return;
            this.isDraggingFile = true;
        },
        onDragLeave(event) {
            if (!event.dataTransfer || !Array.from(event.dataTransfer.types || []).includes('Files')) return;
            this.dragDepth = Math.max(this.dragDepth - 1, 0);
            if (this.dragDepth === 0) this.isDraggingFile = false;
        },
        onDropFile(event) {
            try {
                const files = event.dataTransfer ? event.dataTransfer.files : null;
                this.dragDepth = 0;
                this.isDraggingFile = false;

                if (!files || files.length === 0 || !$refs.chatAttachmentInput) return;
                if (files.length > 1) {
                    this.setDropError('Hanya bisa upload 1 file sekaligus.');
                    return;
                }

                const file = files[0];
                const maxSize = 50 * 1024 * 1024;
                if (file.size > maxSize) {
                    this.setDropError('File terlalu besar. Maksimal 50MB.');
                    return;
                }

                const allowedMimeTypes = [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
                const extension = (file.name.split('.').pop() || '').toLowerCase();
                const allowedExtensions = ['pdf', 'docx', 'xlsx'];

                if (!allowedMimeTypes.includes(file.type) || !allowedExtensions.includes(extension)) {
                    this.setDropError('Format file tidak didukung. Gunakan PDF, DOCX, atau XLSX.');
                    return;
                }

                this.dropError = '';
                $refs.chatAttachmentInput.files = files;
                $refs.chatAttachmentInput.dispatchEvent(new Event('change', { bubbles: true }));
                this.showRightSidebar = true;
            } catch (error) {
                console.error('Upload error:', error);
                this.setDropError('Gagal upload file. Silakan coba lagi.');
            }
        },
        openAttachmentPicker() {
            this.showRightSidebar = true;
            this.dropError = '';
            if (!$refs.chatAttachmentInput) return;
            $refs.chatAttachmentInput.value = '';
            $refs.chatAttachmentInput.click();
        },
        autoResizeTextarea(el) {
            el.style.height = 'auto';
            const minHeight = 44;
            const maxHeight = 200;
            el.style.height = Math.min(Math.max(el.scrollHeight, minHeight), maxHeight) + 'px';
            el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
        }
     }"
     x-on:dragenter.window.prevent="onDragEnter($event)"
     x-on:dragover.window.prevent="onDragOver($event)"
     x-on:dragleave.window.prevent="onDragLeave($event)"
     x-on:drop.window.prevent="onDropFile($event)"
     x-init="initChatBehavior(); $watch('darkMode', val => { localStorage.setItem('theme', val ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', val); }); document.documentElement.classList.toggle('dark', darkMode); if(promptDraft) { setTimeout(() => submitPrompt(), 100); }"
     class="flex h-screen w-full overflow-hidden text-stone-800 dark:text-gray-100 font-sans transition-colors duration-300 relative ista-display-sans bg-stone-50/50 dark:bg-gray-900" style="background-image: url('{{ asset('images/ista/dashboard-grid.png') }}'); background-size: 8px 8px;"
    @php
        $uiIcons = [
            'historyLight' => asset('images/icons/history-light.svg'),
            'historyDark' => asset('images/icons/history-dark.svg'),
            'collapseLeftLight' => asset('images/icons/collapse-left-light.svg'),
            'collapseLeftDark' => asset('images/icons/collapse-left-dark.svg'),
            'collapseRightLight' => asset('images/icons/collapse-right-light.svg'),
            'collapseRightDark' => asset('images/icons/collapse-right-dark.svg'),
            'searchLight' => asset('images/icons/search-light.svg'),
            'searchDark' => asset('images/icons/search-dark.svg'),
            'uploadLight' => asset('images/icons/upload-light.svg'),
            'uploadDark' => asset('images/icons/upload-dark.svg'),
            'sendLight' => asset('images/icons/send-light.svg'),
            'sendDark' => asset('images/icons/send-dark.svg'),
        ];
    @endphp

    <!-- LEFT SIDEBAR: Chat History -->
    @include('livewire.chat.partials.chat-left-sidebar')

    <!-- CENTER MAIN: Chat Area -->
    <main class="flex-1 flex flex-col relative w-full h-full bg-transparent z-0 overflow-hidden min-w-0">

        <!-- Header for Chat Space -->
        <div class="h-[61px] flex-shrink-0 flex items-center justify-between px-3 sm:px-6 z-20 border-b border-stone-200/60/70 dark:border-[#1E293B]/70 backdrop-blur-sm">
            <!-- Left toggler and title -->
            <div class="flex items-center gap-2 sm:gap-4">
                <button type="button" @click="showLeftSidebar = !showLeftSidebar" class="p-2 rounded-[10px] hover:bg-[#F1F5F9] dark:hover:bg-gray-800 transition-colors flex-shrink-0">
                    <img src="{{ $uiIcons['collapseLeftLight'] }}" alt="" class="h-5 w-5 dark:hidden transition-transform duration-300 ease-in-out" :class="showLeftSidebar ? 'rotate-0' : 'rotate-180'" />
                    <img src="{{ $uiIcons['collapseLeftDark'] }}" alt="" class="h-5 w-5 hidden dark:block transition-transform duration-300 ease-in-out" :class="showLeftSidebar ? 'rotate-0' : 'rotate-180'" />
                </button>
                <button type="button" wire:click="startNewChat" class="group flex items-center gap-2"><div class="ista-brand-title text-xl text-ista-primary not-italic transition-transform duration-300 group-hover:scale-105">ISTA <span class="font-light italic text-ista-gold">AI</span></div></button>
            </div>

            <!-- Right toggles -->
            <div class="flex items-center gap-1 sm:gap-3">
                <!-- Theme Toggle Button -->
                <button type="button" @click="darkMode = !darkMode" class="p-2 rounded-[10px] hover:bg-[#F1F5F9] dark:hover:bg-gray-800 transition-colors">
                    <svg x-show="darkMode === false" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.5M12 18.5V21M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M3 12h2.5M18.5 12H21M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8M12 16a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                    <svg x-show="darkMode === true" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" />
                    </svg>
                </button>

                <button type="button" @click="showRightSidebar = !showRightSidebar" class="p-2 rounded-[10px] hover:bg-[#F1F5F9] dark:hover:bg-gray-800 transition-colors flex-shrink-0">
                    <img src="{{ $uiIcons['collapseRightLight'] }}" alt="" class="h-5 w-5 dark:hidden transition-transform duration-300 ease-in-out" :class="showRightSidebar ? 'rotate-0' : 'rotate-180'" />
                    <img src="{{ $uiIcons['collapseRightDark'] }}" alt="" class="h-5 w-5 hidden dark:block transition-transform duration-300 ease-in-out" :class="showRightSidebar ? 'rotate-0' : 'rotate-180'" />
                </button>
            </div>
        </div>

        <div x-show="sendError" x-transition class="px-6 pt-3 pb-1">
            <div class="mx-auto max-w-3xl rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] text-rose-800 dark:border-rose-800/40 dark:bg-rose-950/30 dark:text-rose-200">
                <div class="flex items-start justify-between gap-3">
                    <p class="leading-relaxed" x-text="sendError"></p>
                    <button type="button" class="shrink-0 text-rose-500 hover:text-rose-700 dark:text-rose-300 dark:hover:text-rose-100" x-on:click="sendError = ''" aria-label="Tutup notifikasi">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages List -->
        @include('livewire.chat.partials.chat-messages')

        <!-- Input Area (Composer) -->
        @include('livewire.chat.partials.chat-composer')

    </main>

    <!-- RIGHT SIDEBAR: Documents -->
    @include('livewire.chat.partials.chat-right-sidebar')

    <!-- Unified Mobile Backdrop: closes whichever sidebar is open -->
    <div
        x-show="isMobile && (showLeftSidebar || showRightSidebar)"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="showLeftSidebar = false; showRightSidebar = false;"
        class="fixed inset-0 bg-black/50 z-40"
        style="display:none;"
    ></div>

</div>
