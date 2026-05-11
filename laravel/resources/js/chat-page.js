let hasRegisteredChatPageData = false;

const isDarkThemeEnabled = () => localStorage.getItem('theme') === 'dark';

const registerChatPageData = (Alpine) => {
    if (hasRegisteredChatPageData || !Alpine) {
        return;
    }

    hasRegisteredChatPageData = true;

    Alpine.data('chatLayout', (config = {}) => ({
        activeTab: config.activeTab || 'chat',
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

        setTab(tab) {
            if (!['chat', 'memo'].includes(tab)) {
                return;
            }

            this.activeTab = tab;
            this.$wire.set('tab', tab);
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
        streaming: false,
        streamingText: '',
        modelName: '',
        sources: [],
        loadingContext: 'general',
        loadingPhase: 'AI sedang berpikir',
        loadingPhaseKey: 0,
        loadingPhaseTimeout: null,
        phase2Timeout: null,
        hasFirstAssistantChunk: false,
        phase1Done: false,
        phase2Done: false,
        shimmerActive: false,
        _messageCompleteHandler: null,
        chatMutationObserver: null,
        wireListeners: [],

        init() {
            this.$el.dataset.chatMessagesReady = 'true';
            this.scrollToBottom();

            const chatBox = this.$refs.chatBox;

            if (chatBox) {
                this.chatMutationObserver = new MutationObserver(() => this.scrollToBottom());
                this.chatMutationObserver.observe(chatBox, { childList: true, subtree: true, characterData: true });
            }

            this._messageCompleteHandler = () => this.resetStreamingState();
            window.addEventListener('message-complete', this._messageCompleteHandler);
            this.registerWireListener('assistant-output', (data) => {
                this.streamingText += data[0] || '';
                this.streaming = true;
                this.hasFirstAssistantChunk = true;
                this.scrollToBottom();

                // Jangan langsung pindah ke "Menampilkan jawaban" — biarkan
                // chain timeout fase 1→2→3 yang mengatur. Kalau fase 2 sudah
                // selesai (phase2Done), baru boleh pindah sekarang.
                if (this.phase2Done) {
                    this.loadingPhase = 'Menampilkan jawaban';
                    this.loadingPhaseKey++;
                    this.shimmerActive = false;
                }
                // Kalau belum, phase2Timeout akan memanggil tryShowAnswer()
                // setelah fase 2 selesai.
            });
            this.registerWireListener('model-name', (data) => {
                this.modelName = data[0] || '';
            });
            this.registerWireListener('assistant-sources', (data) => {
                this.sources = data[0] || [];
            });
            this.registerWireListener('assistant-message-persisted', () => this.resetStreamingState());
            this.registerWireListener('user-message-acked', () => {
                this.optimisticUserMessage = '';
                this.scrollToBottom();
            });
        },

        registerWireListener(event, callback) {
            const cleanup = this.$wire.on(event, callback);
            if (typeof cleanup === 'function') {
                this.wireListeners.push(cleanup);
            }
        },

        destroy() {
            this.clearLoadingPhaseTimeout();
            this.clearPhase2Timeout();
            if (this._messageCompleteHandler) {
                window.removeEventListener('message-complete', this._messageCompleteHandler);
                this._messageCompleteHandler = null;
            }
            if (this.chatMutationObserver) {
                this.chatMutationObserver.disconnect();
                this.chatMutationObserver = null;
            }
            if (this.wireListeners && this.wireListeners.length > 0) {
                this.wireListeners.forEach((cleanup) => {
                    if (typeof cleanup === 'function') {
                        try {
                            cleanup();
                        } catch (e) {
                            // Defensive: ignore cleanup errors
                        }
                    }
                });
                this.wireListeners = [];
            }
        },

        clearLoadingPhaseTimeout() {
            if (this.loadingPhaseTimeout) {
                window.clearTimeout(this.loadingPhaseTimeout);
                this.loadingPhaseTimeout = null;
            }
        },

        clearPhase2Timeout() {
            if (this.phase2Timeout) {
                window.clearTimeout(this.phase2Timeout);
                this.phase2Timeout = null;
            }
        },

        // Dipanggil setelah fase 2 selesai. Kalau chunk sudah ada, langsung
        // tampilkan "Menampilkan jawaban". Kalau belum, tunggu chunk datang
        // (assistant-output handler akan cek phase2Done).
        tryShowAnswer() {
            this.phase2Done = true;
            if (this.hasFirstAssistantChunk) {
                this.loadingPhase = 'Menampilkan jawaban';
                this.loadingPhaseKey++;
                this.shimmerActive = false;
            }
        },

        startStreamingPlaceholder(context = 'general') {
            this.resetStreamingState(false);
            this.streaming = true;
            this.loadingContext = context;
            this.loadingPhaseKey++;
            this.loadingPhase = this.loadingPhaseLabels()[0];
            this.shimmerActive = true;
            this.phase1Done = false;
            this.phase2Done = false;

            const labels = this.loadingPhaseLabels();

            if (labels.length > 2) {
                // Kontekstual: fase 1 (Mencari jawaban / Sedang membaca dokumen)
                // bertahan 8000ms, lalu fase 2 (AI sedang berpikir) juga 8000ms,
                // baru tryShowAnswer(). Chain ini tidak bisa di-cancel oleh chunk
                // yang datang lebih awal — chunk hanya di-buffer sampai fase selesai.
                this.loadingPhaseTimeout = window.setTimeout(() => {
                    this.phase1Done = true;
                    this.loadingPhase = labels[1]; // "AI sedang berpikir"
                    this.loadingPhaseKey++;

                    this.phase2Timeout = window.setTimeout(() => {
                        this.tryShowAnswer();
                    }, 8000);
                }, 8000);
            } else {
                // General: langsung "AI sedang berpikir", fase 2 juga 8000ms.
                this.phase1Done = true;
                this.phase2Timeout = window.setTimeout(() => {
                    this.tryShowAnswer();
                }, 8000);
            }
        },

        resetStreamingState(stopStreaming = true) {
            this.clearLoadingPhaseTimeout();
            this.clearPhase2Timeout();

            this.loadingPhaseKey = 0;
            this.hasFirstAssistantChunk = false;
            this.phase1Done = false;
            this.phase2Done = false;
            this.loadingContext = 'general';
            this.loadingPhase = 'AI sedang berpikir';
            this.shimmerActive = false;
            this.streamingText = '';
            this.modelName = '';
            this.sources = [];

            if (stopStreaming) {
                this.streaming = false;
            }
        },

        loadingPhaseLabels() {
            if (this.loadingContext === 'web-search') {
                return ['Mencari jawaban', 'AI sedang berpikir', 'Menampilkan jawaban'];
            }

            if (this.loadingContext === 'documents') {
                return ['Sedang membaca dokumen', 'AI sedang berpikir', 'Menampilkan jawaban'];
            }

            if (this.loadingContext === 'hybrid') {
                return ['Sedang membaca dokumen + Mencari di web', 'AI sedang berpikir', 'Menampilkan jawaban'];
            }

            return ['AI sedang berpikir', 'Menampilkan jawaban'];
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

            if (previousConversationId === conversationId) {
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

    Alpine.data('chatAnswerActions', (config = {}) => ({
        messageId: Number(config.messageId || 0),
        html: config.html || '',
        exportUrl: config.exportUrl || '',
        exportFileName: config.exportFileName || 'ista-ai-export',
        driveUploadAvailable: Boolean(config.driveUploadAvailable),
        exportMenuOpen: false,
        driveMenuOpen: false,
        copied: false,
        exportLoading: false,
        exportError: '',
        driveLoading: false,
        driveError: '',
        driveResult: null,

        plainText() {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = this.html || '';

            return this.normalizePlainText(this.nodeToPlainText(wrapper));
        },

        nodeToPlainText(node) {
            const blockTags = new Set([
                'address', 'article', 'aside', 'blockquote', 'div', 'dl', 'fieldset',
                'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5',
                'h6', 'header', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'ul',
            ]);

            if (node.nodeType === Node.TEXT_NODE) {
                return node.nodeValue || '';
            }

            if (node.nodeType !== Node.ELEMENT_NODE && node.nodeType !== Node.DOCUMENT_FRAGMENT_NODE) {
                return '';
            }

            const tagName = node.tagName?.toLowerCase();

            if (tagName === 'br') {
                return '\n';
            }

            if (tagName === 'hr') {
                return '\n\n';
            }

            if (tagName === 'li') {
                const itemText = Array.from(node.childNodes)
                    .map((child) => this.nodeToPlainText(child))
                    .join('');

                return `\n- ${this.normalizePlainText(itemText)}\n`;
            }

            if (tagName === 'tr') {
                const cells = Array.from(node.children)
                    .filter((child) => ['th', 'td'].includes(child.tagName?.toLowerCase()))
                    .map((cell) => this.normalizePlainText(this.nodeToPlainText(cell)).replace(/\n+/g, ' '));

                return cells.length > 0 ? `${cells.join('\t')}\n` : '';
            }

            const childrenText = Array.from(node.childNodes)
                .map((child) => this.nodeToPlainText(child))
                .join('');

            if (tagName === 'table') {
                return `\n${childrenText}\n`;
            }

            return blockTags.has(tagName) ? `\n${childrenText}\n` : childrenText;
        },

        normalizePlainText(text) {
            return (text || '')
                .replace(/\u00a0/g, ' ')
                .replace(/[ \t]+\n/g, '\n')
                .replace(/\n[ \t]+/g, '\n')
                .replace(/[ \t]{2,}/g, ' ')
                .replace(/\n{3,}/g, '\n\n')
                .split('\n')
                .map((line) => line.trim())
                .join('\n')
                .trim();
        },

        copyStatusLabel() {
            return this.copied ? 'Tersalin' : 'Salin';
        },

        driveButtonLabel() {
            if (!this.driveUploadAvailable) {
                return 'Upload Drive perlu koneksi akun pusat';
            }

            return this.driveLoading ? 'Mengupload ke Google Drive' : 'Upload ke Google Drive';
        },

        toggleExportMenu() {
            if (this.exportLoading) {
                return;
            }

            this.driveMenuOpen = false;
            this.exportMenuOpen = !this.exportMenuOpen;
        },

        toggleDriveMenu() {
            if (this.driveLoading || !this.driveUploadAvailable) {
                return;
            }

            this.exportMenuOpen = false;
            this.driveMenuOpen = !this.driveMenuOpen;
            this.driveError = '';
        },

        async copyToClipboard() {
            const text = this.plainText();

            if (!text) {
                return;
            }

            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const helper = document.createElement('textarea');
                    helper.value = text;
                    helper.setAttribute('readonly', 'true');
                    helper.style.position = 'fixed';
                    helper.style.left = '-9999px';
                    document.body.appendChild(helper);
                    helper.select();
                    document.execCommand('copy');
                    document.body.removeChild(helper);
                }

                this.copied = true;
                window.setTimeout(() => {
                    this.copied = false;
                }, 1800);
            } catch (error) {
                console.error('Gagal menyalin jawaban AI', error);
            }
        },

        shareToWhatsApp() {
            const text = this.plainText();

            if (!text) {
                return;
            }

            const shareUrl = `https://wa.me/?text=${encodeURIComponent(text)}`;
            window.open(shareUrl, '_blank', 'noopener,noreferrer');
        },

        getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        downloadBlob(blob, fileName) {
            const downloadUrl = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.download = fileName;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.URL.revokeObjectURL(downloadUrl);
        },

        filenameFromResponse(response, format) {
            const disposition = response.headers.get('Content-Disposition') || '';
            const fileNameMatch = disposition.match(/filename="?([^"]+)"?/i);

            if (fileNameMatch?.[1]) {
                return fileNameMatch[1];
            }

            return `${this.exportFileName}.${format}`;
        },

        async exportAs(format) {
            if (!this.exportUrl || this.exportLoading) {
                return;
            }

            this.exportMenuOpen = false;
            this.exportLoading = true;
            this.exportError = '';

            try {
                const response = await fetch(this.exportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': '*/*',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                    body: JSON.stringify({
                        content_html: this.html,
                        target_format: format,
                        file_name: this.exportFileName,
                    }),
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(errorText || 'Export gagal');
                }

                const blob = await response.blob();
                const fileName = this.filenameFromResponse(response, format);
                this.downloadBlob(blob, fileName);
            } catch (error) {
                console.error('Gagal mengekspor jawaban AI', error);
                this.exportError = 'Ekspor gagal. Coba lagi.';
            } finally {
                this.exportLoading = false;
            }
        },

        async uploadToGoogleDrive(format) {
            if (!this.messageId || this.driveLoading || !this.driveUploadAvailable) {
                return;
            }

            this.driveMenuOpen = false;
            this.driveLoading = true;
            this.driveError = '';
            this.driveResult = null;

            try {
                const result = await this.$wire.saveAnswerToGoogleDrive(this.messageId, format);

                if (!result?.ok) {
                    throw new Error(result?.message || 'Upload ke Google Drive gagal.');
                }

                this.driveResult = result;
            } catch (error) {
                console.error('Gagal mengupload jawaban AI ke Google Drive', error);
                this.driveError = error?.message || 'Upload ke Google Drive gagal. Coba lagi.';
            } finally {
                this.driveLoading = false;
            }
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

            if (['xlsx', 'csv'].includes(extension)) {
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

            const normalizedDocs = this.normalizedDocumentIds();
            const hasDocs = normalizedDocs.length > 0;
            const hasWeb = Boolean(this.webSearchMode);
            const loadingContext = hasWeb && hasDocs
                ? 'hybrid'
                : (hasWeb ? 'web-search' : (hasDocs ? 'documents' : 'general'));

            this.$dispatch('message-send', { text, loadingContext });
            this.scrollChatToBottom(true);
            this.stabilizeChatScroll();

            this.promptDraft = '';
            this.autoResizeTextarea(this.$refs.chatInput);
            this.$wire.webSearchMode = Boolean(this.webSearchMode);
            this.$wire.conversationDocuments = normalizedDocs;

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

        openGoogleDrivePicker() {
            this.$dispatch('open-google-drive-picker');
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

    Alpine.data('memoDocumentDownloads', () => ({
        downloadLoading: null,

        async downloadMemo(url, type, fallbackName, versionId = null) {
            if (this.downloadLoading) {
                return;
            }

            this.downloadLoading = type;

            try {
                const response = await fetch(this.versionedUrl(url, versionId), {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Download gagal');
                }

                const blob = await response.blob();
                this.downloadBlob(blob, this.fileNameFromDisposition(
                    response.headers.get('Content-Disposition') || '',
                    fallbackName,
                ));
            } finally {
                this.downloadLoading = null;
            }
        },

        versionedUrl(url, fallbackVersionId = null) {
            const requestUrl = new URL(url, window.location.origin);
            const selectedVersionId = document.getElementById('memo-version-select')?.value || fallbackVersionId;

            if (selectedVersionId) {
                requestUrl.searchParams.set('version_id', selectedVersionId);
            }

            return requestUrl.toString();
        },

        fileNameFromDisposition(disposition, fallbackName) {
            const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);

            if (utfMatch) {
                try {
                    return decodeURIComponent(utfMatch[1]);
                } catch (error) {
                    return fallbackName;
                }
            }

            const asciiMatch = disposition.match(/filename="?([^";]+)"?/i);

            return asciiMatch ? asciiMatch[1] : fallbackName;
        },

        downloadBlob(blob, fileName) {
            const downloadUrl = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.download = fileName;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.URL.revokeObjectURL(downloadUrl);
        },
    }));

    Alpine.data('memoWorkspace', () => ({
        showMemoSidebar: !window.matchMedia('(max-width: 1023px)').matches,
        isMobile: window.matchMedia('(max-width: 1023px)').matches,
        memoMobilePanel: 'chat',
        memoRevisionText: '',
        memoRevisionLoading: false,
        memoLoadingPhase: 'Membuat ulang memo',
        memoLoadingPhaseKey: 0,
        memoLoadingPhaseTimeout: null,
        memoPhase2Timeout: null,
        memoPhase2Done: false,
        memoShimmerActive: false,

        init() {
            const mediaQuery = window.matchMedia('(max-width: 1023px)');
            const syncState = (event) => {
                this.isMobile = event.matches;
                this.showMemoSidebar = !event.matches;

                if (!event.matches) {
                    this.memoMobilePanel = 'chat';
                }
            };

            mediaQuery.addEventListener('change', syncState);

            // Auto-scroll memo chat on new messages
            this.$watch('$wire.memoChatMessages', () => {
                this.$nextTick(() => this.scrollMemoChatToBottom());
            });
        },

        collapseMemoSidebarForDocument() {
            this.showMemoSidebar = false;

            if (this.isMobile) {
                this.memoMobilePanel = 'document';
            }
        },

        showMemoChatPanel() {
            this.memoMobilePanel = 'chat';
            this.showMemoSidebar = false;
        },

        showMemoDocumentPanel() {
            this.memoMobilePanel = 'document';
            this.showMemoSidebar = false;
        },

        submitMemoRevision($wire, textarea) {
            const message = (textarea?.value || '').trim();

            if (!message || this.memoRevisionLoading || $wire.isGenerating) {
                return;
            }

            this.memoRevisionText = message;
            this.memoRevisionLoading = true;
            this.memoShimmerActive = true;
            this.startMemoLoadingPhase();

            textarea.value = '';
            textarea.style.height = 'auto';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            this.scrollMemoChatToBottom();

            $wire.sendMemoChat(message)
                .catch(() => {})
                .finally(() => {
                    this.memoRevisionText = '';
                    this.memoRevisionLoading = false;
                    this.resetMemoLoadingPhase();
                    this.scrollMemoChatToBottom();
                });
        },

        memoLoadingLabels() {
            return ['Membuat ulang memo', 'AI sedang berpikir', 'Menampilkan jawaban'];
        },

        clearMemoLoadingPhaseTimeout() {
            if (this.memoLoadingPhaseTimeout) {
                window.clearTimeout(this.memoLoadingPhaseTimeout);
                this.memoLoadingPhaseTimeout = null;
            }
        },

        clearMemoPhase2Timeout() {
            if (this.memoPhase2Timeout) {
                window.clearTimeout(this.memoPhase2Timeout);
                this.memoPhase2Timeout = null;
            }
        },

        startMemoLoadingPhase() {
            this.resetMemoLoadingPhase();
            this.memoLoadingPhaseKey++;
            this.memoLoadingPhase = this.memoLoadingLabels()[0];
            this.memoShimmerActive = true;
            this.memoPhase2Done = false;

            // Fase 1 ("Membuat ulang memo") 8000ms, lalu fase 2
            // ("AI sedang berpikir") juga 8000ms — chain tidak bisa
            // di-cancel, sehingga perbandingan durasi 1:1.
            this.memoLoadingPhaseTimeout = window.setTimeout(() => {
                this.memoLoadingPhase = this.memoLoadingLabels()[1];
                this.memoLoadingPhaseKey++;

                this.memoPhase2Timeout = window.setTimeout(() => {
                    this.memoPhase2Done = true;
                    this.memoLoadingPhase = this.memoLoadingLabels()[2];
                    this.memoLoadingPhaseKey++;
                    this.memoShimmerActive = false;
                }, 8000);
            }, 8000);
        },

        resetMemoLoadingPhase() {
            this.clearMemoLoadingPhaseTimeout();
            this.clearMemoPhase2Timeout();

            this.memoLoadingPhaseKey = 0;
            this.memoPhase2Done = false;
            this.memoLoadingPhase = 'Membuat ulang memo';
            this.memoShimmerActive = false;
        },

        scrollMemoChatToBottom() {
            const chatBox = document.getElementById('memo-chat-box');

            if (!chatBox) {
                return;
            }

            this.$nextTick(() => {
                chatBox.scrollTo({
                    top: chatBox.scrollHeight,
                    behavior: 'smooth',
                });
            });
        },
    }));

    Alpine.data('documentViewerExport', (config = {}) => ({
        contentUrl: config.contentUrl || '',
        extractUrl: config.extractUrl || '',
        exportUrl: config.exportUrl || '',
        fileName: config.fileName || 'ista-ai-tabel-dokumen',
        preferTableExtraction: Boolean(config.preferTableExtraction),
        driveUploadAvailable: Boolean(config.driveUploadAvailable),
        exportMenuOpen: false,
        driveMenuOpen: false,
        loadingAction: null,
        error: '',
        contentHtml: '',
        tables: null,

        isBusy() {
            return this.loadingAction !== null;
        },

        isExportLoading() {
            return this.loadingAction === 'export';
        },

        isDriveLoading() {
            return this.loadingAction === 'drive';
        },

        toggleMenu() {
            if (this.isBusy()) {
                return;
            }

            this.driveMenuOpen = false;
            this.exportMenuOpen = !this.exportMenuOpen;
            this.error = '';
        },

        exportLoadingLabel() {
            return this.isExportLoading() ? 'Menyiapkan ekspor' : 'Ekspor';
        },

        driveButtonLabel() {
            if (!this.driveUploadAvailable) {
                return 'Upload Drive perlu koneksi akun pusat';
            }

            return this.isDriveLoading() ? 'Menyiapkan upload ke Google Drive' : 'Upload ke GDrive Kantor';
        },

        toggleDriveMenu() {
            if (this.isBusy() || !this.driveUploadAvailable) {
                return;
            }

            this.exportMenuOpen = false;
            this.driveMenuOpen = !this.driveMenuOpen;
            this.error = '';
        },

        async exportTablesAs(format) {
            if (!this.exportUrl || this.isBusy()) {
                return;
            }

            this.exportMenuOpen = false;
            this.driveMenuOpen = false;
            this.loadingAction = 'export';
            this.error = '';

            try {
                const isTableFormat = ['xlsx', 'csv'].includes(format);
                const contentHtml = isTableFormat && this.preferTableExtraction
                    ? await this.tableExportHtml()
                    : await this.fullContentHtml();

                const response = await fetch(this.exportUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': '*/*',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                    body: JSON.stringify({
                        content_html: contentHtml,
                        target_format: format,
                        file_name: this.fileName,
                    }),
                });

                if (!response.ok) {
                    throw new Error(await response.text() || 'Gagal mengekspor dokumen.');
                }

                const blob = await response.blob();
                this.downloadBlob(blob, this.filenameFromResponse(response, format));
            } catch (error) {
                console.error('Gagal mengekspor dokumen', error);
                this.error = error?.message || 'Gagal mengekspor dokumen.';
            } finally {
                this.loadingAction = null;
            }
        },

        async saveToGoogleDrive(format) {
            if (this.isBusy() || !this.driveUploadAvailable) {
                return;
            }

            this.exportMenuOpen = false;
            this.driveMenuOpen = false;
            this.loadingAction = 'drive';
            this.error = '';

            try {
                await this.$wire.saveToGoogleDrive(format);
            } catch (error) {
                console.error('Gagal menyimpan dokumen ke Google Drive', error);
                this.error = error?.message || 'Gagal menyimpan ke Google Drive.';
            } finally {
                this.loadingAction = null;
            }
        },

        async fullContentHtml() {
            if (this.contentHtml) {
                return this.contentHtml;
            }

            if (!this.contentUrl) {
                throw new Error('Konten dokumen belum tersedia untuk diekspor.');
            }

            const response = await fetch(this.contentUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            });

            let payload = null;

            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            if (!response.ok) {
                throw new Error(payload?.message || payload?.detail || 'Gagal mengekstrak isi dokumen.');
            }

            this.contentHtml = String(payload?.content_html || '').trim();

            if (!this.contentHtml) {
                throw new Error('Isi dokumen kosong atau tidak bisa diekstrak.');
            }

            return this.contentHtml;
        },

        async tableExportHtml() {
            const tables = await this.extractTables();

            if (tables.length === 0) {
                throw new Error('Tidak ada tabel yang bisa diekspor.');
            }

            return this.tablesToHtml(tables);
        },

        async extractTables() {
            if (Array.isArray(this.tables)) {
                return this.tables;
            }

            if (!this.extractUrl) {
                throw new Error('Ekstraksi tabel tidak tersedia untuk dokumen ini.');
            }

            const response = await fetch(this.extractUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            });

            let payload = null;

            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            if (!response.ok) {
                throw new Error(payload?.message || payload?.detail || 'Gagal mengekstrak tabel.');
            }

            this.tables = Array.isArray(payload?.tables) ? payload.tables : [];

            return this.tables;
        },

        tablesToHtml(tables) {
            const sections = tables.map((table, index) => {
                const pageLabel = table.page ? ` - Halaman ${table.page}` : '';
                const title = `Tabel ${index + 1}${pageLabel}`;
                const header = Array.isArray(table.header) ? table.header : [];
                const rows = Array.isArray(table.rows) ? table.rows : [];
                const headerHtml = header.length > 0
                    ? `<thead><tr>${header.map((cell) => `<th>${this.escapeHtml(cell)}</th>`).join('')}</tr></thead>`
                    : '';
                const bodyHtml = rows
                    .map((row) => `<tr>${(Array.isArray(row) ? row : []).map((cell) => `<td>${this.escapeHtml(cell)}</td>`).join('')}</tr>`)
                    .join('');

                return `
                    <section>
                        <h2>${this.escapeHtml(title)}</h2>
                        <table>
                            ${headerHtml}
                            <tbody>${bodyHtml}</tbody>
                        </table>
                    </section>
                `;
            }).join('');

            return `<article><h1>Ekstrak Tabel Dokumen</h1>${sections}</article>`;
        },

        escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value == null ? '' : String(value);

            return element.innerHTML;
        },

        getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        downloadBlob(blob, fileName) {
            const downloadUrl = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.download = fileName;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.URL.revokeObjectURL(downloadUrl);
        },

        filenameFromResponse(response, format) {
            const disposition = response.headers.get('Content-Disposition') || '';
            const fileNameMatch = disposition.match(/filename="?([^"]+)"?/i);

            if (fileNameMatch?.[1]) {
                return fileNameMatch[1];
            }

            return `${this.fileName}.${format}`;
        },
    }));
};

document.addEventListener('alpine:init', () => {
    registerChatPageData(window.Alpine);
});

if (window.Alpine) {
    registerChatPageData(window.Alpine);
}
