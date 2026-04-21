let hasRegisteredChatPageData = false;

const isDarkThemeEnabled = () => localStorage.getItem('theme') === 'dark';

const registerChatPageData = (Alpine) => {
    if (hasRegisteredChatPageData || !Alpine) {
        return;
    }

    hasRegisteredChatPageData = true;

    Alpine.data('chatLayout', () => ({
        darkMode: isDarkThemeEnabled(),
        isMobile: window.matchMedia('(max-width: 1023px)').matches,
        showLeftSidebar: !window.matchMedia('(max-width: 1023px)').matches,
        showRightSidebar: !window.matchMedia('(max-width: 1023px)').matches,
        isDraggingFile: false,
        dragDepth: 0,
        dropError: '',

        init() {
            this.$watch('darkMode', (value) => {
                localStorage.setItem('theme', value ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', value);
            });

            document.documentElement.classList.toggle('dark', this.darkMode);

            const mediaQuery = window.matchMedia('(max-width: 1023px)');
            const syncResponsiveState = (event) => {
                this.isMobile = event.matches;

                if (this.isMobile) {
                    this.showLeftSidebar = false;
                    this.showRightSidebar = false;

                    return;
                }

                this.showLeftSidebar = true;
                this.showRightSidebar = true;
            };

            mediaQuery.addEventListener('change', syncResponsiveState);
        },

        onDragEnter(event) {
            if (!this.hasFiles(event)) {
                return;
            }

            this.dragDepth += 1;
            this.isDraggingFile = true;
        },

        onDragOver(event) {
            if (!this.hasFiles(event)) {
                return;
            }

            this.isDraggingFile = true;
        },

        onDragLeave(event) {
            if (!this.hasFiles(event)) {
                return;
            }

            this.dragDepth = Math.max(this.dragDepth - 1, 0);

            if (this.dragDepth === 0) {
                this.isDraggingFile = false;
            }
        },

        onDropFile(event) {
            this.dragDepth = 0;
            this.isDraggingFile = false;

            const files = event.dataTransfer?.files;

            if (!files || files.length === 0) {
                return;
            }

            if (files.length > 1) {
                this.showDropError('Hanya bisa upload 1 file sekaligus.');

                return;
            }

            const input = document.querySelector('[x-ref="chatAttachmentInput"]');

            if (!input) {
                return;
            }

            input.files = files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            this.showRightSidebar = true;
        },

        hasFiles(event) {
            return Array.from(event.dataTransfer?.types || []).includes('Files');
        },

        showDropError(message) {
            this.dropError = message;
            this.$dispatch('show-drop-error', { message });

            setTimeout(() => {
                if (this.dropError === message) {
                    this.dropError = '';
                }
            }, 3500);
        },
    }));

    Alpine.data('chatMessages', () => ({
        optimisticUserMessage: '',

        init() {
            this.$el.dataset.chatMessagesReady = 'true';
            this.scrollToBottom();

            const chatBox = this.$refs.chatBox;

            if (chatBox) {
                const observer = new MutationObserver(() => this.scrollToBottom());
                observer.observe(chatBox, { childList: true, subtree: true, characterData: true });
            }

            this.$wire.on('assistant-output', () => this.scrollToBottom());
            this.$wire.on('user-message-acked', () => {
                this.optimisticUserMessage = '';
                this.scrollToBottom();
            });
        },

        scrollToBottom(smooth = false) {
            this.$nextTick(() => {
                const chatBox = this.$refs.chatBox;

                if (!chatBox) {
                    return;
                }

                chatBox.scrollTo({
                    top: chatBox.scrollHeight,
                    behavior: smooth ? 'smooth' : 'auto',
                });
            });
        },
    }));

    Alpine.data('chatComposer', (config = {}) => ({
        promptDraft: config.prompt || '',
        isSendingMessage: false,
        sendError: '',
        messageAcked: false,

        init() {
            if (this.promptDraft) {
                this.schedulePendingPromptSubmission();
            }

            this.$wire.on('user-message-acked', () => {
                this.messageAcked = true;
                this.stabilizeChatScroll();
            });
        },

        schedulePendingPromptSubmission(attempt = 0) {
            if (!this.promptDraft.trim()) {
                return;
            }

            if (document.querySelector('[data-chat-messages-ready="true"]')) {
                this.$nextTick(() => {
                    requestAnimationFrame(() => this.submitPrompt());
                });

                return;
            }

            if (attempt >= 20) {
                return;
            }

            window.setTimeout(() => {
                this.schedulePendingPromptSubmission(attempt + 1);
            }, 50);
        },

        submitPrompt(event) {
            if (event) {
                event.preventDefault();
            }

            if (this.isSendingMessage) {
                return;
            }

            const text = this.promptDraft.trim();

            if (!text) {
                return;
            }

            this.sendError = '';
            this.messageAcked = false;
            this.isSendingMessage = true;

            this.$dispatch('message-send', { text });
            this.scrollChatToBottom(true);
            this.stabilizeChatScroll();

            this.promptDraft = '';
            this.autoResizeTextarea(this.$refs.chatInput);

            this.$wire.sendMessage(text)
                .catch(() => {
                    this.$dispatch('user-message-acked');

                    if (!this.messageAcked) {
                        this.promptDraft = text;
                        this.sendError = 'Pesan gagal dikirim. Periksa koneksi.';
                    } else {
                        this.sendError = 'Jawaban gagal diproses. Coba kirim ulang.';
                    }

                    setTimeout(() => {
                        this.sendError = '';
                    }, 6000);
                })
                .finally(() => {
                    this.isSendingMessage = false;
                    this.$dispatch('message-complete');
                });
        },

        openAttachmentPicker() {
            this.$dispatch('open-sidebar-right');

            const input = this.$refs.chatAttachmentInput;

            if (!input) {
                return;
            }

            input.value = '';
            input.click();
        },

        scrollChatToBottom(smooth = false) {
            const chatBox = document.querySelector('[data-chat-box]');

            if (!chatBox) {
                return;
            }

            this.$nextTick(() => {
                chatBox.scrollTo({
                    top: chatBox.scrollHeight,
                    behavior: smooth ? 'smooth' : 'auto',
                });
            });
        },

        stabilizeChatScroll(attempts = 8) {
            const runScrollPass = (remaining) => {
                this.scrollChatToBottom(remaining === attempts);

                if (remaining <= 0) {
                    return;
                }

                window.setTimeout(() => {
                    this.$nextTick(() => runScrollPass(remaining - 1));
                }, 60);
            };

            this.$nextTick(() => runScrollPass(attempts));
        },

        autoResizeTextarea(element) {
            if (!element) {
                return;
            }

            element.style.height = 'auto';
            element.style.height = `${Math.min(Math.max(element.scrollHeight, 44), 200)}px`;
            element.style.overflowY = element.scrollHeight > 200 ? 'auto' : 'hidden';
        },
    }));
};

document.addEventListener('alpine:init', () => {
    registerChatPageData(window.Alpine);
});

if (window.Alpine) {
    registerChatPageData(window.Alpine);
}
