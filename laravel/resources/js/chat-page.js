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
        isSwitchingConversation: false,
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
        isSwitchingConversation: false,

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

    Alpine.data('chatHistory', (config = {}) => ({
        activeConversationId: config.activeConversationId ? Number(config.activeConversationId) : null,
        showOlderChats: config.showOlderChats || false,
        loadingConversationId: null,
        isNavigating: false,

        init() {
            this.$nextTick(() => this.syncActiveHistoryItem());
            this.$watch('activeConversationId', () => this.syncActiveHistoryItem());
        },

        isActive(id) {
            return this.activeConversationId === Number(id);
        },

        isLoading(id) {
            return this.loadingConversationId === Number(id);
        },

        setActiveConversation(id) {
            const conversationId = id ? Number(id) : null;
            this.activeConversationId = Number.isFinite(conversationId) ? conversationId : null;
            this.syncActiveHistoryItem();
            this.$nextTick(() => this.syncActiveHistoryItem());
        },

        syncActiveHistoryItem() {
            if (!this.$root) {
                return;
            }

            this.$root.querySelectorAll('[data-chat-history-id]').forEach((button) => {
                const isActive = Number(button.dataset.chatHistoryId) === this.activeConversationId;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-current', isActive ? 'page' : 'false');
            });
        },

        loadConversation(id) {
            const conversationId = Number(id);
            const previousConversationId = this.activeConversationId;

            this.setActiveConversation(conversationId);

            if (this.isNavigating || previousConversationId === conversationId) {
                return Promise.resolve();
            }

            this.loadingConversationId = conversationId;
            this.isNavigating = true;
            this.$dispatch('conversation-loading');

            return this.$wire.loadConversation(conversationId)
                .catch(() => {
                    this.setActiveConversation(previousConversationId);
                })
                .finally(() => {
                    if (this.loadingConversationId === conversationId) {
                        this.loadingConversationId = null;
                    }

                    this.isNavigating = false;
                    this.$dispatch('conversation-loaded');
                });
        },

        startNewChat() {
            if (this.isNavigating) {
                return Promise.resolve();
            }

            this.setActiveConversation(null);
            this.loadingConversationId = null;
            this.isNavigating = true;
            this.$dispatch('chat-new-optimistic');
            this.$dispatch('conversation-loading');

            return this.$wire.startNewChat()
                .finally(() => {
                    this.isNavigating = false;
                    this.$dispatch('conversation-loaded');
                });
        },
    }));

    Alpine.data('chatDocumentSelector', (config = {}) => ({
        selectedDocuments: config.selectedDocuments || [],
        readyDocumentIds: (config.readyDocumentIds || []).map((id) => Number(id)),
        availableDocuments: (config.availableDocuments || []).map((document) => ({
            id: Number(document.id),
            name: document.name,
            extension: document.extension,
            status: document.status,
        })),

        normalizedSelectedDocuments() {
            const ready = new Set(this.readyDocumentIds);

            return [...new Set((this.selectedDocuments || [])
                .map((id) => Number(id))
                .filter((id) => ready.has(id)))];
        },

        isSelected(id) {
            return this.normalizedSelectedDocuments().includes(Number(id));
        },

        selectedInAvailableCount() {
            return this.normalizedSelectedDocuments().length;
        },

        allDocumentsSelected() {
            return this.readyDocumentIds.length > 0
                && this.selectedInAvailableCount() === this.readyDocumentIds.length;
        },

        toggleSelectAllDocuments() {
            this.selectedDocuments = this.allDocumentsSelected()
                ? []
                : [...this.readyDocumentIds];
        },

        syncSelectedDocuments() {
            this.selectedDocuments = this.normalizedSelectedDocuments();
            this.$wire.selectedDocuments = this.selectedDocuments;
        },

        addSelectedDocumentsToChat() {
            this.syncSelectedDocuments();
            const selected = new Set(this.selectedDocuments.map((id) => Number(id)));
            const documents = this.availableDocuments.filter((document) => selected.has(document.id));

            this.$dispatch('conversation-documents-preview', {
                ids: this.selectedDocuments,
                documents,
            });

            return this.$wire.addSelectedDocumentsToChat();
        },

        deleteSelectedDocuments() {
            if (!window.confirm('Delete selected files from your documents?')) {
                return Promise.resolve();
            }

            this.syncSelectedDocuments();

            return this.$wire.deleteSelectedDocuments();
        },
    }));

    Alpine.data('chatComposer', (config = {}) => ({
        promptDraft: config.prompt || '',
        webSearchMode: config.webSearchMode || false,
        conversationDocuments: config.conversationDocuments || [],
        availableDocuments: (config.availableDocuments || []).map((document) => ({
            id: Number(document.id),
            name: document.name,
            extension: document.extension,
            status: document.status,
        })),
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

        previewConversationDocuments(event) {
            const ids = event.detail?.ids || [];
            const documents = event.detail?.documents || [];

            this.conversationDocuments = this.normalizedDocumentIds(ids);
            this.mergeAvailableDocuments(documents);
        },

        normalizedDocumentIds(ids = this.conversationDocuments) {
            return [...new Set((ids || []).map((id) => Number(id)).filter(Boolean))];
        },

        mergeAvailableDocuments(documents = []) {
            const existing = new Map(this.availableDocuments.map((document) => [Number(document.id), document]));

            documents.forEach((document) => {
                existing.set(Number(document.id), {
                    id: Number(document.id),
                    name: document.name,
                    extension: document.extension,
                    status: document.status,
                });
            });

            this.availableDocuments = Array.from(existing.values());
        },

        chatDocuments() {
            const byId = new Map(this.availableDocuments.map((document) => [Number(document.id), document]));

            return this.normalizedDocumentIds()
                .map((id) => byId.get(id))
                .filter(Boolean);
        },

        documentIconType(document) {
            const extension = (document.extension || '').toLowerCase();

            if (extension === 'pdf') {
                return 'pdf';
            }

            if (extension === 'xlsx') {
                return 'xlsx';
            }

            if (extension === 'docx') {
                return 'docx';
            }

            return 'file';
        },

        removeConversationDocument(id) {
            const documentId = Number(id);
            this.conversationDocuments = this.normalizedDocumentIds()
                .filter((currentId) => currentId !== documentId);
            this.$wire.conversationDocuments = this.conversationDocuments;

            return this.$wire.removeConversationDocument(documentId);
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
            this.$wire.webSearchMode = Boolean(this.webSearchMode);
            this.$wire.conversationDocuments = this.normalizedDocumentIds();

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

        handleEnterKey(event) {
            if (event.isComposing) {
                return;
            }

            if (event.shiftKey) {
                window.requestAnimationFrame(() => {
                    this.autoResizeTextarea(this.$refs.chatInput);
                });

                return;
            }

            event.preventDefault();
            this.submitPrompt(event);
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

    Alpine.data('documentViewer', (config = {}) => ({
        isVisible: Boolean(config.isOpen),
        isLoading: false,
        requestToken: 0,

        open(id) {
            const documentId = Number(id);

            if (!Number.isFinite(documentId)) {
                return Promise.resolve();
            }

            const token = this.requestToken + 1;
            this.requestToken = token;
            this.isVisible = true;
            this.isLoading = true;

            return this.$wire.open(documentId)
                .catch(() => {
                    if (this.requestToken === token) {
                        this.isVisible = false;
                    }
                })
                .finally(() => {
                    if (this.requestToken === token) {
                        this.isLoading = false;
                    }
                });
        },

        close() {
            this.requestToken += 1;
            this.isVisible = false;
            this.isLoading = false;

            return this.$wire.close();
        },
    }));
};

document.addEventListener('alpine:init', () => {
    registerChatPageData(window.Alpine);
});

if (window.Alpine) {
    registerChatPageData(window.Alpine);
}
