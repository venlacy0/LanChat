        function showFileUploadModal(file) {
            pendingFile = file;
            fileInfoName.textContent = file.name;
            fileInfoSize.textContent = formatFileSize(file.size);
            fileInfoType.textContent = file.type || '未知';
            fileMessageInput.value = '';
            fileUploadOverlay.classList.remove('closing');
            fileUploadOverlay.classList.add('active');
        }

        function closeFileUploadModal() {
            if (!fileUploadOverlay.classList.contains('active')) {
                pendingFile = null;
                fileMessageInput.value = '';
                return;
            }

            let done_called = false;
            const onTransitionEnd = (e) => {
                if (e.target !== fileUploadOverlay) return;
                if (e.propertyName !== 'opacity') return;
                done();
            };
            const done = () => {
                if (done_called) return;
                done_called = true;
                fileUploadOverlay.classList.remove('active', 'closing');
                fileUploadOverlay.removeEventListener('transitionend', onTransitionEnd);
            };

            fileUploadOverlay.classList.add('closing');
            fileUploadOverlay.addEventListener('transitionend', onTransitionEnd);

            // 超时保险（prefers-reduced-motion 或个别浏览器不触发 transitionend）
            setTimeout(done, 520);

            pendingFile = null;
            fileMessageInput.value = '';
        }

        function handleFileDrop(e) {
            e.preventDefault();
            dragDropZone.classList.remove('active');

            // 从 DataTransferItemList 中获取文件
            let files = [];

            if (e.dataTransfer.items) {
                // 使用 DataTransferItemList 接口（推荐）
                for (let i = 0; i < e.dataTransfer.items.length; i++) {
                    if (e.dataTransfer.items[i].kind === 'file') {
                        files.push(e.dataTransfer.items[i].getAsFile());
                    }
                }
            } else if (e.dataTransfer.files) {
                // 备选：使用 FileList（较老的浏览器）
                files = Array.from(e.dataTransfer.files);
            }

            if (files.length > 0) {
                const file = files[0];

                // 验证文件大小
                if (file.size > MAX_UPLOAD_BYTES) {
                    showToast('文件大小超过限制（最大4MB）', 'error');
                    return;
                }

                showFileUploadModal(file);
            }
        }

        function uploadFile() {
            if (!pendingFile) return;

            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('file', pendingFile);
            formData.append('message', fileMessageInput.value.trim());
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);
            formData.append('is_private', !isPublicChat);

            if (!isPublicChat && selectedReceiverId) {
                formData.append('receiver_id', selectedReceiverId);
            }

            if (replyToId) {
                formData.append('reply_to', replyToId);
            }

            closeFileUploadModal();
            showToast('正在上传文件...', 'success');

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('文件上传成功', 'success');
                    appendMessage(data.new_message, true);
                    cancelReply();
                } else {
                    showToast('上传失败: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('上传失败，请重试', 'error');
            });
        }

        // --- End of File Upload Functions ---



        // --- Message File Attachment Functions ---
        function handleMessageFileSelect(e) {
            const file = e.target.files[0];
            if (!file) return;

            // 验证文件大小（4MB）
            if (file.size > MAX_UPLOAD_BYTES) {
                showToast('文件大小超过限制（最大4MB）', 'error');
                messageFileInput.value = '';
                return;
            }

            // 保存文件信息
            messageAttachment = file;
            displayMessageAttachmentPreview(file);
            messageFileInput.value = '';
        }

        function handleMessagePasteFile(e) {
            const clipboardData = e.clipboardData;
            if (!clipboardData || !clipboardData.items) return;

            const fileItem = Array.from(clipboardData.items).find(item => item.kind === 'file');
            if (!fileItem) return;

            const file = fileItem.getAsFile();
            if (!file) return;

            // 验证文件大小（4MB）
            if (file.size > MAX_UPLOAD_BYTES) {
                showToast('文件大小超过限制（最大4MB）', 'error');
                return;
            }

            e.preventDefault();
            messageAttachment = file;
            displayMessageAttachmentPreview(file);
            showToast('已从剪贴板添加文件');
        }

        function displayMessageAttachmentPreview(file) {
            const fileExt = file.name.split('.').pop().toLowerCase();
            const fileIcon = getFileIcon(fileExt);

            // 更新图标
            attachmentIcon.className = `fas ${fileIcon}`;

            // 更新文件名和大小
            attachmentName.textContent = file.name;
            attachmentSize.textContent = formatFileSize(file.size);

            // 显示预览
            messageAttachmentPreview.style.display = 'block';
            updateScrollBtnPosition();
        }

        function removeMessageAttachment() {
            messageAttachment = null;
            messageAttachmentPreview.style.display = 'none';
            messageFileInput.value = '';
            updateScrollBtnPosition();
        }

        // --- Markdown Editor Functions ---
        function openMarkdownEditor() {
            markdownEditorOpen = true;
            // 将输入框内容复制到编辑器
            markdownInput.value = messageInput.value;
            const container = markdownEditorOverlay.querySelector('.markdown-editor-container');
            markdownEditorOverlay.classList.remove('closing');
            if (container) container.classList.remove('closing');
            markdownEditorOverlay.classList.add('active');
            markdownEditorBtn.classList.add('active');

            // 首次打开时更新预览
            setTimeout(() => {
                updateMarkdownPreview();
                // 自动聚焦到编辑器
                markdownInput.focus();
            }, 150);
        }

        function closeMarkdownEditor() {
            markdownEditorOpen = false;
            const container = markdownEditorOverlay.querySelector('.markdown-editor-container');

            // 添加关闭动画
            markdownEditorOverlay.classList.add('closing');
            container.classList.add('closing');
            markdownEditorBtn.classList.remove('active');

            let done_called = false;
            const onTransitionEnd = (e) => {
                if (e.target !== container) return;
                if (e.propertyName !== 'transform') return;
                done();
            };
            const done = () => {
                if (done_called) return;
                done_called = true;
                markdownEditorOverlay.classList.remove('active', 'closing');
                container.classList.remove('closing');
                container.removeEventListener('transitionend', onTransitionEnd);
                // 清空内容但保留用户输入到主输入框
                markdownPreview.innerHTML = '';
            };

            container.addEventListener('transitionend', onTransitionEnd);

            // 超时保险
            setTimeout(done, 520);
        }

        function confirmMarkdownEditor() {
            // 将编辑后的内容回写到主输入框
            messageInput.value = markdownInput.value;
            resizeInput.call(messageInput);
            closeMarkdownEditor();
            showToast('内容已导入到输入框');
        }

        function updateMarkdownPreview() {
            const content = markdownInput.value;

            if (!content.trim()) {
                markdownPreview.innerHTML = '<p style="color: var(--secondary-text); text-align: center; padding: 40px 0;">预览内容将在此显示</p>';
                return;
            }

            // 检测内容中是否包含 HTML 标签
            const hasHtmlTags = /<[a-z][\s\S]*>/i.test(content);

            if (hasHtmlTags) {
                // 如果包含 HTML，直接使用 HTML 渲染，但需要过滤危险标签
                const sanitizedHtml = sanitizeHtml(content);
                markdownPreview.innerHTML = sanitizedHtml;
            } else {
                // 否则使用 Markdown 渲染
                let html = renderMarkdown(content);
                // 渲染结果仍需过滤（例如 markdown 链接可能带 javascript: 等危险协议）
                markdownPreview.innerHTML = sanitizeHtml(html);
            }

            // 高亮代码块
            if (typeof hljs !== 'undefined') {
                markdownPreview.querySelectorAll('pre code').forEach(block => {
                    hljs.highlightElement(block);
                    block.style.color = '#0f172a';
                    block.style.backgroundColor = '#ffffff';
                });
            }

            // 渲染 MathJax 数学公式
            if (typeof MathJax !== 'undefined' && typeof MathJax.typesetPromise === 'function') {
                MathJax.typesetPromise([markdownPreview]).catch(err => console.log(err));
            }
        }

        // HTML 安全过滤函数（前端最后一道防线）
        function sanitizeHtml(html) {
            if (html === null || html === undefined) return '';
            html = String(html);

            // 允许的 HTML 标签（扩展范围以支持 Markdown 渲染 / 卡片渲染）
            const allowedTags = [
                'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'del', 'ins',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'img',
                'div', 'span', 'hr', 'section', 'article', 'header', 'footer',
                'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
                'mark', 'sub', 'sup', 'small', 'kbd', 'var',
                'iframe'
            ];

            const allowedAttributes = {
                'a': ['href', 'target', 'title', 'rel'],
                'img': ['src', 'alt', 'width', 'height', 'title'],
                'iframe': ['src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy', 'sandbox', 'border', 'marginwidth', 'marginheight', 'scrolling'],
                'div': ['class', 'id'],
                'span': ['class', 'id'],
                'table': ['class', 'id', 'border'],
                'td': ['colspan', 'rowspan', 'class'],
                'th': ['colspan', 'rowspan', 'class'],
                'code': ['class'],
                'pre': ['class'],
                '*': ['class', 'id']
            };

            const allowedIframeHosts = [
                // 音乐平台
                'music.163.com',
                'y.qq.com',
                'open.spotify.com',
                'embed.music.apple.com',
                'music.apple.com',
                'w.soundcloud.com',
                'bandcamp.com',
                'widget.deezer.com',
                'www.deezer.com',
                'www.mixcloud.com',
                'embed.tidal.com',

                // 视频平台
                'www.youtube.com',
                'www.youtube-nocookie.com',
                'player.vimeo.com',
                'player.bilibili.com',
                'www.dailymotion.com',
                'player.twitch.tv',
                'www.tiktok.com',
                'player.youku.com',
                'player.video.iqiyi.com',
                'v.qq.com'
            ];

            function escapeHtmlLocal(text) {
                const div = document.createElement('div');
                const safe = (text === null || text === undefined) ? '' : text;
                div.textContent = String(safe);
                return div.innerHTML;
            }

            function normalizePotentialUrl(raw) {
                const safe = (raw === null || raw === undefined) ? '' : raw;
                return String(safe).trim();
            }

            function isSafeHref(raw) {
                const value = normalizePotentialUrl(raw);
                if (!value) return false;
                if (value.startsWith('#')) return true;
                try {
                    const url = new URL(value, window.location.origin);
                    const p = (url.protocol || '').toLowerCase();
                    return (p === 'http:' || p === 'https:' || p === 'mailto:' || p === 'tel:');
                } catch (e) {
                    return false;
                }
            }

            function isSafeSrc(raw) {
                const value = normalizePotentialUrl(raw);
                if (!value) return false;
                try {
                    const url = new URL(value, window.location.origin);
                    const p = (url.protocol || '').toLowerCase();
                    if (p === 'http:' || p === 'https:' || p === 'blob:') return true;
                    if (p === 'data:') {
                        // 保留一定“自由度”，但阻断明显可执行 HTML 载荷
                        return !/^data\s*:\s*text\s*\/\s*html/i.test(value);
                    }
                    return false;
                } catch (e) {
                    return false;
                }
            }

            function normalizeIframeSrc(src) {
                const trimmed = normalizePotentialUrl(src);
                if (!trimmed) return '';
                if (trimmed.startsWith('//')) return 'https:' + trimmed;
                return trimmed;
            }

            function isAllowedIframeSrc(src) {
                const normalized = normalizeIframeSrc(src);
                if (!normalized) return false;
                try {
                    const url = new URL(normalized, window.location.origin);
                    const p = (url.protocol || '').toLowerCase();
                    if (p !== 'https:' && p !== 'http:') return false;
                    return allowedIframeHosts.some(host => url.hostname === host);
                } catch (e) {
                    return false;
                }
            }

            try {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                function cleanNode(node) {
                    if (!node) return false;

                    if (node.nodeType === Node.TEXT_NODE) return true;

                    // 移除注释节点
                    if (node.nodeType === Node.COMMENT_NODE) {
                        node.remove();
                        return false;
                    }

                    if (node.nodeType !== Node.ELEMENT_NODE) {
                        // 其他类型节点直接移除
                        if (typeof node.remove === 'function') {
                            node.remove();
                        } else if (node.parentNode) {
                            node.parentNode.removeChild(node);
                        }
                        return false;
                    }

                    const tagName = node.tagName.toLowerCase();

                    // 不允许的标签：剥离标签但保留内容（并继续清理其子节点）
                    if (!allowedTags.includes(tagName)) {
                        const children = Array.from(node.childNodes);
                        const fragment = document.createDocumentFragment();
                        children.forEach(child => fragment.appendChild(child)); // move
                        node.replaceWith(fragment);
                        children.forEach(child => cleanNode(child));
                        return false;
                    }

                    // 清理属性
                    const allowedAttrs = allowedAttributes[tagName] || allowedAttributes['*'] || [];
                    const toRemove = [];

                    for (const attr of Array.from(node.attributes)) {
                        const name = String(attr.name || '').toLowerCase();
                        if (!allowedAttrs.includes(name)) {
                            toRemove.push(attr.name);
                            continue;
                        }

                        if (name === 'href') {
                            const href = node.getAttribute('href') || '';
                            if (!isSafeHref(href)) {
                                toRemove.push(attr.name);
                            }
                        }

                        if (name === 'src' && tagName !== 'iframe') {
                            const src = node.getAttribute('src') || '';
                            if (!isSafeSrc(src)) {
                                toRemove.push(attr.name);
                            }
                        }
                    }

                    toRemove.forEach(name => node.removeAttribute(name));

                    // a[target=_blank] 防 tabnabbing
                    if (tagName === 'a') {
                        const target = (node.getAttribute('target') || '').toLowerCase();
                        if (target === '_blank') {
                            const rel = (node.getAttribute('rel') || '').toLowerCase();
                            const needsNoopener = !rel.includes('noopener');
                            const needsNoreferrer = !rel.includes('noreferrer');
                            if (needsNoopener || needsNoreferrer) {
                                const parts = rel.split(/\s+/).filter(Boolean);
                                if (needsNoopener) parts.push('noopener');
                                if (needsNoreferrer) parts.push('noreferrer');
                                node.setAttribute('rel', parts.join(' '));
                            }
                        }
                    }

                    // iframe：强制白名单 host
                    if (tagName === 'iframe') {
                        const src = node.getAttribute('src') || '';
                        if (!isAllowedIframeSrc(src)) {
                            node.remove();
                            return false;
                        }
                        const normalized = normalizeIframeSrc(src);
                        if (normalized && normalized !== src) {
                            node.setAttribute('src', normalized);
                        }
                        if (!node.getAttribute('loading')) {
                            node.setAttribute('loading', 'lazy');
                        }
                        if (!node.getAttribute('referrerpolicy')) {
                            node.setAttribute('referrerpolicy', 'no-referrer');
                        }
                    }

                    // 递归清理子节点
                    for (const child of Array.from(node.childNodes)) {
                        cleanNode(child);
                    }

                    return true;
                }

                for (const child of Array.from(tempDiv.childNodes)) {
                    cleanNode(child);
                }

                const result = tempDiv.innerHTML;

                // 绝不能回退到原始 HTML（会导致 XSS 绕过）
                if (!result || !result.trim()) {
                    return escapeHtmlLocal(html);
                }

                return result;
            } catch (error) {
                console.error('sanitizeHtml 错误:', error);
                return escapeHtmlLocal(html);
            }
        }

        // Markdown 渲染函数
        function renderMarkdown(content) {
            let html = content;

            // 转义 HTML 特殊字符（除了我们要处理的 Markdown 标记）
            html = html.replace(/&/g, '&amp;')
                       .replace(/</g, '&lt;')
                       .replace(/>/g, '&gt;');

            // 处理代码块（```...```）
            html = html.replace(/```([\s\S]*?)```/g, (match, code) => {
                return `<pre><code>${code.trim()}</code></pre>`;
            });

            // 处理行内代码（`...`）
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

            // 处理标题（#, ##, ###等）
            html = html.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
            html = html.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
            html = html.replace(/^# (.*?)$/gm, '<h1>$1</h1>');

            // 处理加粗（**...**）
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/__([^_]+?)__/g, '<strong>$1</strong>');

            // 处理斜体（*...*）
            html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
            html = html.replace(/_([^_]+?)_/g, '<em>$1</em>');

            // 处理链接（[text](url)）
            html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank">$1</a>');

            // 处理无序列表（- 或 * 开头）
            html = html.replace(/^\s*[-*] (.*?)$/gm, '<li>$1</li>');
            html = html.replace(/(<li>[\s\S]*?<\/li>)/, '<ul>$1</ul>');

            // 处理有序列表（数字.开头）
            html = html.replace(/^\s*\d+\. (.*?)$/gm, '<li>$1</li>');

            // 处理引用块（> 开头）
            html = html.replace(/^&gt; (.*?)$/gm, '<blockquote>$1</blockquote>');

            // 处理段落（空行分隔）
            html = html.split('\n\n').map(para => {
                para = para.trim();
                if (para && !para.match(/^<[hpul]/)) {
                    return `<p>${para}</p>`;
                }
                return para;
            }).join('');

            return html;
        }
