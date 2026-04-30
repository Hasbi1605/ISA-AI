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

    Alpine.data('chatAnswerActions', (config = {}) => ({
        html: config.html || '',
        exportUrl: config.exportUrl || '',
        exportFileName: config.exportFileName || 'ista-ai-export',
        exportMenuOpen: false,
        copied: false,
        exportLoading: false,
        exportError: '',

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

        toggleExportMenu() {
            if (this.exportLoading) {
                return;
            }

            this.exportMenuOpen = !this.exportMenuOpen;
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

    Alpine.data('documentViewerExport', (config = {}) => ({
        contentUrl: config.contentUrl || '',
        extractUrl: config.extractUrl || '',
        exportUrl: config.exportUrl || '',
        convertUrl: config.convertUrl || '',
        fileName: config.fileName || 'ista-ai-tabel-dokumen',
        preferTableExtraction: Boolean(config.preferTableExtraction),
        exportMenuOpen: false,
        loading: false,
        error: '',
        contentHtml: '',
        tables: null,

        toggleMenu() {
            if (this.loading) {
                return;
            }

            this.exportMenuOpen = !this.exportMenuOpen;
            this.error = '';
        },

        async exportTablesAs(format) {
            if (!this.exportUrl || this.loading) {
                return;
            }

            this.exportMenuOpen = false;
            this.loading = true;
            this.error = '';

            try {
                if (this.convertUrl) {
                    await this.convertOriginalAs(format);

                    return;
                }

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
                this.loading = false;
            }
        },

        async convertOriginalAs(format) {
            const response = await fetch(this.convertUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': '*/*',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                body: JSON.stringify({
                    target_format: format,
                    file_name: this.fileName,
                }),
            });

            if (!response.ok) {
                throw new Error(await response.text() || 'Gagal mengonversi dokumen.');
            }

            const blob = await response.blob();
            this.downloadBlob(blob, this.filenameFromResponse(response, format));
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
