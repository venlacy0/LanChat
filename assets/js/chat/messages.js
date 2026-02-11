        async function sendMessage() {
            const message = messageInput.value.trim();

            // 如果有附件或消息内容，则发送
            if (!message && !messageAttachment) return;

            if (messageAttachment) {
                // 如果有附件，使用文件上传流程
                if (isPublicChat) {
                    sendPublicMessageWithFile(message);
                } else if (selectedReceiverId) {
                    sendPrivateMessageWithFile(message, selectedReceiverId);
                } else {
                    showToast('请先选择聊天对象', 'error');
                }
            } else {
                // 没有附件，使用普通消息发送
                if (isPublicChat) {
                    sendPublicMessage(message);
                } else if (selectedReceiverId) {
                    sendPrivateMessage(message, selectedReceiverId);
                } else {
                    showToast('请先选择聊天对象', 'error');
                }
            }

            messageInput.value = '';
            resizeInput.call(messageInput);
            cancelReply();
            deactivateInputFocus();
            removeMessageAttachment();
        }

        function sendPublicMessage(message) {
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', message);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);
            if (replyToId) formData.append('reply_to', replyToId);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    appendMessage(data.new_message, true);
                } else {
                    showToast('发送失败: ' + data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function sendPrivateMessage(message, receiverId) {
            const formData = new FormData();
            formData.append('action', 'send_private_message');
            formData.append('private_message', message);
            formData.append('receiver_id', receiverId);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);
            if (replyToId) formData.append('reply_to', replyToId);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    appendMessage(data.new_message, true);
                } else {
                    showToast('发送失败: ' + data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function sendPublicMessageWithFile(message) {
            if (!messageAttachment) return;

            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('file', messageAttachment);
            formData.append('message', message);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);
            formData.append('is_private', 'false');
            if (replyToId) formData.append('reply_to', replyToId);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('文件发送成功');
                    appendMessage(data.new_message, true);
                } else {
                    showToast('发送失败: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('发送失败，请重试', 'error');
            });
        }

        function sendPrivateMessageWithFile(message, receiverId) {
            if (!messageAttachment) return;

            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('file', messageAttachment);
            formData.append('message', message);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);
            formData.append('is_private', 'true');
            formData.append('receiver_id', receiverId);
            if (replyToId) formData.append('reply_to', replyToId);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('文件发送成功');
                    appendMessage(data.new_message, true);
                } else {
                    showToast('发送失败: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('发送失败，请重试', 'error');
            });
        }

	        function getMessageSignature(messages) {
	            if (!Array.isArray(messages) || messages.length === 0) return 'empty';
	            const first = messages[0];
	            const last = messages[messages.length - 1];
	            const firstId = first && first.id ? first.id : '';
	            const lastId = last && last.id ? last.id : '';
	            const firstTs = first && first.timestamp ? first.timestamp : 0;
	            const lastTs = last && last.timestamp ? last.timestamp : 0;
	            return `${messages.length}:${firstId}:${lastId}:${firstTs}:${lastTs}`;
	        }

	        function getLatestTimestamp() {
	            return isPublicChat ? latestPublicTimestamp : latestPrivateTimestamp;
	        }

	        function getLatestId() {
	            return isPublicChat ? latestPublicId : latestPrivateId;
	        }

	        // 统一消息 ID 为 number，避免 string/number 混用导致 Set 去重/删除失效
	        function normalizeMessageId(id) {
	            const n = Number(id);
	            return (n && isFinite(n)) ? n : 0;
	        }

	        function serverHasAllDisplayed(serverMessages) {
	            if (!Array.isArray(serverMessages) || displayedMessageIds.size === 0) return true;
	            const serverIds = new Set();
	            serverMessages.forEach(msg => {
	                const id = normalizeMessageId(msg && msg.id);
	                if (id) serverIds.add(id);
	            });
	            for (const id of displayedMessageIds) {
	                if (!serverIds.has(normalizeMessageId(id))) return false;
	            }
	            return true;
	        }

	        function appendNewMessagesFromServer(serverMessages) {
	            const latestId = normalizeMessageId(getLatestId());
	            const fresh = serverMessages
	                .filter(msg => {
	                    const id = normalizeMessageId(msg && msg.id);
	                    return id && !displayedMessageIds.has(id) && id > latestId;
	                })
	                .reverse(); // server is new->old, append needs old->new
	            if (fresh.length > 0) {
	                appendMessagesBatch(fresh);
	            }
	        }

            // --- 分页/虚拟滑动：只加载最新一页（默认 50 条），滚动到顶部再继续加载 ---
            const HISTORY_PAGE_SIZE = 50;
            const publicHistoryState = { oldestId: 0, hasMore: false, loading: false, ready: false };
            const privateHistoryStateByUser = Object.create(null);
            let historyTopLoaderEl = null;

            function getPrivateHistoryState(receiverId) {
                const id = String(receiverId || '');
                if (!id) return null;
                if (!privateHistoryStateByUser[id]) {
                    privateHistoryStateByUser[id] = { oldestId: 0, hasMore: false, loading: false, ready: false };
                }
                return privateHistoryStateByUser[id];
            }

            function computeOldestId(messages) {
                if (!Array.isArray(messages) || messages.length === 0) return 0;
                let minId = 0;
                for (const m of messages) {
                    const id = m && m.id ? Number(m.id) : 0;
                    if (!id) continue;
                    if (minId === 0 || id < minId) minId = id;
                }
                return minId;
            }

            function getCurrentHistoryState() {
                if (typeof isPublicChat !== 'undefined' && isPublicChat) {
                    return publicHistoryState;
                }
                if (typeof selectedReceiverId === 'undefined' || !selectedReceiverId) return null;
                return getPrivateHistoryState(selectedReceiverId);
            }

            function ensureHistoryTopLoader() {
                if (historyTopLoaderEl && messagesContainer.contains(historyTopLoaderEl)) return historyTopLoaderEl;
                historyTopLoaderEl = document.createElement('div');
                historyTopLoaderEl.id = 'historyTopLoader';
                historyTopLoaderEl.className = 'loader';
                historyTopLoaderEl.style.cssText = 'position:absolute; top:8px; left:0; right:0; margin:0 auto; width:fit-content; pointer-events:none; opacity:0; transition: opacity 0.15s ease; z-index: 2;';
                historyTopLoaderEl.textContent = '加载更多...';
                messagesContainer.appendChild(historyTopLoaderEl);
                return historyTopLoaderEl;
            }

            function showHistoryTopLoader() {
                const el = ensureHistoryTopLoader();
                el.style.opacity = '1';
            }

            function hideHistoryTopLoader() {
                if (!historyTopLoaderEl) return;
                historyTopLoaderEl.style.opacity = '0';
            }

            function prependHistoryMessages(serverMessages) {
                if (!Array.isArray(serverMessages) || serverMessages.length === 0) return 0;

                const oldScrollHeight = messagesContainer.scrollHeight;
                const oldScrollTop = messagesContainer.scrollTop;

                const ordered = [...serverMessages].reverse(); // old -> new
                const fragment = document.createDocumentFragment();
                const mathTargets = [];
                const highlightTargets = [];
                let added = 0;

                ordered.forEach((msg) => {
                    const id = normalizeMessageId(msg && msg.id);
                    if (!id || displayedMessageIds.has(id)) return;
                    displayedMessageIds.add(id);
                    const el = createMessageElement(msg);
                    el.style.animation = 'none';
                    fragment.appendChild(el);
                    added++;

                    if (contentMayContainMath(msg.message)) {
                        const contentDiv = el.querySelector('.message-content');
                        if (contentDiv) mathTargets.push(contentDiv);
                    }
                    if (elementHasCodeBlocks(el)) {
                        highlightTargets.push(el);
                    }
                });

                if (added === 0) return 0;

                // 插入到最顶部（注意：loader 是 absolute，不会影响布局）
                const firstMessage = messagesContainer.querySelector('.message');
                if (firstMessage) {
                    messagesContainer.insertBefore(fragment, firstMessage);
                } else {
                    messagesContainer.appendChild(fragment);
                }

                // 维持用户视窗位置（避免 prepend 导致跳动）
                const newScrollHeight = messagesContainer.scrollHeight;
                const delta = newScrollHeight - oldScrollHeight;
                if (delta !== 0) {
                    messagesContainer.scrollTop = oldScrollTop + delta;
                }

                scheduleIdle(() => {
                    if (mathTargets.length > 0 && typeof MathJax !== 'undefined' && typeof MathJax.typesetPromise === 'function') {
                        MathJax.typesetPromise(mathTargets).catch(err => console.warn('MathJax 渲染失败:', err));
                    }
                    highlightTargets.forEach(el => {
                        try {
                            highlightCodeBlocks(el);
                        } catch (e) {
                            console.warn('代码块高亮失败:', e);
                        }
                    });
                }, 200);

                return added;
            }

            async function loadMoreHistoryIfNeeded() {
                const state = getCurrentHistoryState();
                if (!state || state.loading || !state.ready || !state.hasMore) return;
                if (messagesContainer.scrollTop > 120) return;

                const beforeId = state.oldestId || 0;
                if (!beforeId) return;

                state.loading = true;
                showHistoryTopLoader();
                try {
                    const result = (typeof isPublicChat !== 'undefined' && isPublicChat)
                        ? await loadPublicMessages(null, beforeId)
                        : await loadPrivateMessages(null, beforeId);

                    if (!result || !Array.isArray(result.messages)) {
                        return;
                    }

                    prependHistoryMessages(result.messages);
                    state.hasMore = !!result.hasMore;

                    const batchOldestId = computeOldestId(result.messages);
                    if (batchOldestId) {
                        state.oldestId = state.oldestId ? Math.min(state.oldestId, batchOldestId) : batchOldestId;
                    }
                } finally {
                    state.loading = false;
                    hideHistoryTopLoader();
                }
            }

            // 给 ui.js 调用：滚动到顶部时触发加载更多（内部自带节流/状态判断）
            window.maybeLoadMoreHistory = function maybeLoadMoreHistory() {
                loadMoreHistoryIfNeeded().catch(err => console.error('加载更多历史消息失败:', err));
            };

	        function loadPublicMessages(onComplete = null, beforeId = 0) {
	            if (publicLoadController) publicLoadController.abort();
	            publicLoadController = new AbortController();
	            const loadToken = ++publicLoadToken;
	            const isPublicAtLoad = isPublicChat;

                // 首屏加载：重置分页状态（避免切换 tab 后复用旧状态）
                if (beforeId <= 0) {
                    publicHistoryState.oldestId = 0;
                    publicHistoryState.hasMore = false;
                    publicHistoryState.loading = false;
                    publicHistoryState.ready = false;
                }

	            // 如果是加载历史消息，不使用缓存
	            if (beforeId > 0) {
	                const formData = new FormData();
	                formData.append('action', 'get_messages');
	                formData.append('before_id', beforeId);
	                formData.append('limit', '50');
	                formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

	                return fetch('api.php', { method: 'POST', body: formData, signal: publicLoadController.signal })
	                    .then(res => res.json())
	                    .then(data => {
	                        if (loadToken !== publicLoadToken || !isPublicAtLoad || !isPublicChat) return null;
	                        if (data.success) {
	                            return {
	                                messages: Array.isArray(data.messages) ? data.messages : [],
	                                hasMore: data.hasMore || false
	                            };
	                        }
	                        return null;
	                    })
	                    .catch(err => {
	                        if (err && err.name === 'AbortError') return null;
	                        console.error('加载历史消息失败:', err);
	                        return null;
	                    });
	            }

	            // 首次加载逻辑（使用缓存）
	            const cachedRaw = loadMessagesFromCache('public');
	            const cached = Array.isArray(cachedRaw) ? cachedRaw.slice(0, HISTORY_PAGE_SIZE) : cachedRaw;
	            const cachedSignature = getMessageSignature(cached);
	            const cachedHasMessages = cached && Array.isArray(cached) && cached.length > 0;
	            let doneCalled = false;
	            const done = () => {
	                if (doneCalled) return;
	                doneCalled = true;
	                if (typeof onComplete === 'function') onComplete();
	            };
	            if (cachedHasMessages) {
	                displayInitialMessages(cached);
	                done();
	            } else {
	                messagesContainer.innerHTML = '<div class="loader"><i class="fas fa-spinner fa-spin"></i> 加载中...</div>';
	            }

	            // 从服务器获取最新数据
	            const formData = new FormData();
	            formData.append('action', 'get_messages');
	            formData.append('limit', String(HISTORY_PAGE_SIZE)); // 首屏只加载最新一页
	            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

	            fetch('api.php', { method: 'POST', body: formData, signal: publicLoadController.signal })
	            .then(res => res.json())
	            .then(data => {
	                if (loadToken !== publicLoadToken || !isPublicAtLoad || !isPublicChat) return;
	                if (data.success) {
	                    const serverMessages = Array.isArray(data.messages) ? data.messages : [];
	                    const serverSignature = getMessageSignature(serverMessages);
	                    if (!cachedHasMessages) {
	                        displayInitialMessages(serverMessages);
	                    } else if (serverSignature !== cachedSignature) {
	                        // 缓存可能只保存了「最近 N 条」，而服务端返回了更完整的历史。
	                        // 这时不能只追加“新消息”，否则会永远看不到旧消息（典型现象：只显示约 50 条）。
	                        if (Array.isArray(cached) && serverMessages.length > cached.length) {
	                            displayInitialMessages(serverMessages);
	                        } else if (serverHasAllDisplayed(serverMessages)) {
	                            appendNewMessagesFromServer(serverMessages);
	                        } else {
	                            displayInitialMessages(serverMessages);
	                        }
	                    }
	                    saveMessagesToCache(serverMessages, 'public');

                        // 更新分页状态（以服务端返回为准）
                        publicHistoryState.oldestId = computeOldestId(serverMessages);
                        publicHistoryState.hasMore = !!data.hasMore;
                        publicHistoryState.ready = true;
	                } else {
	                    if (!cachedHasMessages) {
	                        messagesContainer.innerHTML = `<div class="loader">加载失败: ${data.message}</div>`;
	                    }
	                }
	                done();
	            })
	            .catch(err => {
	                if (err && err.name === 'AbortError') return;
	                console.error('加载公共消息失败:', err);
	                if (!cachedHasMessages) {
	                    messagesContainer.innerHTML = '<div class="loader">加载失败，请重试</div>';
	                }
	                done();
	            });
	        }

	        function loadPrivateMessages(onComplete = null, beforeId = 0) {
	            if (!selectedReceiverId) return;
	            if (privateLoadController) privateLoadController.abort();
	            privateLoadController = new AbortController();
	            const loadToken = ++privateLoadToken;
	            const receiverIdAtLoad = selectedReceiverId;
                const state = getPrivateHistoryState(receiverIdAtLoad);

                // 首屏加载：重置分页状态（避免切换对象后复用旧状态）
                if (state && beforeId <= 0) {
                    state.oldestId = 0;
                    state.hasMore = false;
                    state.loading = false;
                    state.ready = false;
                }

	            // 如果是加载历史消息，不使用缓存
	            if (beforeId > 0) {
	                const formData = new FormData();
	                formData.append('action', 'get_private_messages');
	                formData.append('receiver_id', receiverIdAtLoad);
	                formData.append('before_id', beforeId);
	                formData.append('limit', '50');
	                formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

	                return fetch('api.php', { method: 'POST', body: formData, signal: privateLoadController.signal })
	                    .then(res => res.json())
	                    .then(data => {
	                        if (loadToken !== privateLoadToken || receiverIdAtLoad !== selectedReceiverId) return null;
	                        if (data.success) {
	                            return {
	                                messages: Array.isArray(data.messages) ? data.messages : [],
	                                hasMore: data.hasMore || false
	                            };
	                        }
	                        return null;
	                    })
	                    .catch(err => {
	                        if (err && err.name === 'AbortError') return null;
	                        console.error('加载历史消息失败:', err);
	                        return null;
	                    });
	            }

	            // 首次加载逻辑（使用缓存）
	            const cachedRaw = loadMessagesFromCache('private', selectedReceiverId);
	            const cached = Array.isArray(cachedRaw) ? cachedRaw.slice(0, HISTORY_PAGE_SIZE) : cachedRaw;
	            const cachedSignature = getMessageSignature(cached);
	            const cachedHasMessages = cached && Array.isArray(cached) && cached.length > 0;
	            let doneCalled = false;
	            const done = () => {
	                if (doneCalled) return;
	                doneCalled = true;
	                if (typeof onComplete === 'function') onComplete();
	            };
	            if (cachedHasMessages) {
	                displayInitialMessages(cached);
	                done();
	            } else {
	                messagesContainer.innerHTML = '<div class="loader"><i class="fas fa-spinner fa-spin"></i> 加载中...</div>';
	            }

	            // 从服务器获取最新数据
	            const formData = new FormData();
	            formData.append('action', 'get_private_messages');
	            formData.append('receiver_id', receiverIdAtLoad);
	            formData.append('limit', String(HISTORY_PAGE_SIZE)); // 首屏只加载最新一页
	            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

	            fetch('api.php', { method: 'POST', body: formData, signal: privateLoadController.signal })
	            .then(res => res.json())
	            .then(data => {
	                if (loadToken !== privateLoadToken || receiverIdAtLoad !== selectedReceiverId) return;
	                if (data.success) {
	                    const serverMessages = Array.isArray(data.messages) ? data.messages : [];
	                    const serverSignature = getMessageSignature(serverMessages);
	                    if (!cachedHasMessages) {
	                        displayInitialMessages(serverMessages);
	                    } else if (serverSignature !== cachedSignature) {
	                        // 缓存可能只保存了「最近 N 条」，而服务端返回了更完整的历史。
	                        // 这时不能只追加“新消息”，否则会永远看不到旧消息（典型现象：只显示约 50 条）。
	                        if (Array.isArray(cached) && serverMessages.length > cached.length) {
	                            displayInitialMessages(serverMessages);
	                        } else if (serverHasAllDisplayed(serverMessages)) {
	                            appendNewMessagesFromServer(serverMessages);
	                        } else {
	                            displayInitialMessages(serverMessages);
	                        }
	                    }
	                    saveMessagesToCache(serverMessages, 'private', receiverIdAtLoad);

                        // 更新分页状态（以服务端返回为准）
                        if (state) {
                            state.oldestId = computeOldestId(serverMessages);
                            state.hasMore = !!data.hasMore;
                            state.ready = true;
                        }
	                    done();
	                } else {
	                    if (!cachedHasMessages) {
	                        messagesContainer.innerHTML = `<div class="loader">加载失败: ${data.message}</div>`;
	                    }
	                    done();
	                }
	            })
	            .catch(err => {
	                if (err && err.name === 'AbortError') return;
	                console.error('加载私聊消息失败:', err);
	                if (!cachedHasMessages) {
	                    messagesContainer.innerHTML = '<div class="loader">加载失败，请重试</div>';
	                }
	                done();
	            });
	        }

	        function displayInitialMessages(messages) {
	            messagesContainer.innerHTML = '';
	            displayedMessageIds.clear();
	            if (isPublicChat) {
	                latestPublicTimestamp = 0;
	                latestPublicId = 0;
	            } else {
	                latestPrivateTimestamp = 0;
	                latestPrivateId = 0;
	            }

            // 保存原始顺序（从后端来的就是「最新在前」）
            currentDisplayedMessages = [...messages];

            if (messages.length === 0) {
                messagesContainer.innerHTML = '<div class="loader">暂无消息</div>';
                return;
            }

            try {
                // 后端返回顺序：最新消息在数组前面（倒序）
                // 要让页面从上到下是「旧 → 新」，必须先反转，
                // 这样顶部是最早的，底部是最新的。
                const ordered = [...messages].reverse();

	                // 使用 DocumentFragment 批量插入以提高性能
	                const fragment = document.createDocumentFragment();
	                let needsMath = false;
	                let needsHighlight = false;

                ordered.forEach((msg) => {
                    try {
	                        const el = createMessageElement(msg);
	                        // 首屏初始化时禁用逐条动画，避免打断到底部定位
	                        el.style.animation = 'none';
	                        fragment.appendChild(el);
	                        updateLatestTimestamp(msg.timestamp);
	                        updateLatestId(msg.id);
	                        const mid = normalizeMessageId(msg.id);
	                        if (mid) displayedMessageIds.add(mid);
	                        if (!needsMath && contentMayContainMath(msg.message)) needsMath = true;
	                        if (!needsHighlight && typeof msg.message === 'string' && /<pre|<code/i.test(msg.message)) needsHighlight = true;
	                    } catch (error) {
	                        console.error('创建消息元素失败:', msg, error);
	                    }
	                });

                // 一次性添加所有消息到 DOM
                messagesContainer.appendChild(fragment);

                // 强制浏览器重排，确保 scrollHeight 正确计算
                void messagesContainer.offsetHeight;

                // 立即执行一次滚动（可能不完全准确，但比没有好）
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

		                // 初始化完毕后，重渲染放到空闲时间
                scheduleIdle(() => {
                    if (needsMath && typeof MathJax !== 'undefined' && typeof MathJax.typesetPromise === 'function') {
                        MathJax.typesetPromise([messagesContainer])
                            .catch(err => console.warn('MathJax 渲染失败:', err));
                    }
                    if (needsHighlight) {
                        highlightCodeBlocks(messagesContainer);
                    }
                }, 500);

		                // 延迟调用滚动函数，等待 DOM 完全稳定
		                setTimeout(() => {
		                    autoScrollAfterSwitch();
		                }, 50);
            } catch (error) {
                console.error('displayInitialMessages 失败:', error);
                messagesContainer.innerHTML = '<div class="loader" style="color: var(--danger-color);">加载消息失败</div>';
            }
        }

        function createMessageElement(msg) {
            const el = document.createElement('div');
            el.className = 'message';
            el.dataset.messageId = msg.id;
            if (Number(msg.user_id) === Number(currentUserId) || Number(msg.sender_id) === Number(currentUserId)) {
                el.classList.add('own');
            }

            // 回复预览必须做转义/过滤，避免 XSS（reply_to.message 可能包含 HTML 或文件 JSON）
            let replyHtml = '';
            if (msg.reply_to) {
                const replyUser = escapeHtml(String(msg.reply_to.username || ''));
                const rawReplyMsg = msg.reply_to.message;
                const replyMsgStr = (rawReplyMsg === null || rawReplyMsg === undefined) ? '' : String(rawReplyMsg);
                const hasHtmlTags = /<[a-z][\s\S]*>/i.test(replyMsgStr);
                const safeReplyMsg = hasHtmlTags ? sanitizeHtml(replyMsgStr) : escapeHtml(replyMsgStr);
                replyHtml = `<div class="reply-preview"><span><strong>回复 ${replyUser}:</strong> ${safeReplyMsg}</span></div>`;
            }
            const avatarUrl = resolveAvatarUrl(msg.avatar || msg.sender_avatar);
            const fallbackAvatar = resolveAvatarUrl(null);
            const username = msg.username || msg.sender_username;

            // 检查是否为文件消息
            let messageContent = msg.message;
            let isFileMessage = false;

	            // 仅在疑似文件 JSON 时才做 HTML 抽取，避免对所有消息创建临时 DOM
	            let rawMessage = msg.message;
	            let jsonCandidate = null;
	            if (typeof rawMessage === 'string') {
	                const trimmedRaw = rawMessage.trim();
	                if (trimmedRaw.startsWith('{')) {
	                    jsonCandidate = trimmedRaw;
	                } else if (trimmedRaw.startsWith('<') &&
	                    (rawMessage.includes('"type":"file"') || rawMessage.includes('"type": "file"'))) {
	                    const tempDiv = document.createElement('div');
	                    tempDiv.innerHTML = rawMessage;
	                    const extracted = (tempDiv.textContent || '').trim();
	                    if (extracted.startsWith('{')) jsonCandidate = extracted;
	                }
	            }

	            // 尝试检查消息是否是 JSON 格式的文件消息
	            if (jsonCandidate) {
	                try {
	                    const parsedMessage = JSON.parse(jsonCandidate);
                    if (parsedMessage.type === 'file' && parsedMessage.file) {
                        isFileMessage = true;
                        const fileInfo = parsedMessage.file;
                        const fileSize = formatFileSize(fileInfo.size);
                        const fileText = parsedMessage.text || '';
                        const fileType = fileInfo.type.toLowerCase();
                        const rawFilePath = fileInfo.path;
                        const displayFilePath = normalizeFileUrl(rawFilePath);

                        // 判断是否为图片或动图（直接显示）
                        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(fileType)) {
                            messageContent = `
                                <div class="file-message-card image-message">
                                    <img src="${escapeHtml(displayFilePath)}" alt="${escapeHtml(fileInfo.filename)}" class="file-image">
                                    ${fileText ? `<div class="file-message-text">${escapeHtml(fileText)}</div>` : ''}
                                </div>
                            `;
                        }
                        // 判断是否为文档类文件（添加预览）
                        else if (['pdf', 'doc', 'docx', 'txt', 'md'].includes(fileType)) {
                            const fileIcon = getFileIcon(fileType);
                            const isPreviewable = ['pdf', 'txt', 'md'].includes(fileType);

                            messageContent = `
                                <div class="file-message-card document-message">
                                    <div class="file-message-header">
                                        <div class="file-icon">
                                            <i class="fas ${fileIcon}"></i>
                                        </div>
                                        <div class="file-details">
                                            <div class="file-name">${escapeHtml(fileInfo.filename)}</div>
                                            <div class="file-size">${fileSize}</div>
                                        </div>
                                    </div>
                                    ${isPreviewable ? `<div class="file-preview-container" data-file-path="${escapeHtml(rawFilePath)}" data-file-type="${fileType}"><div class="file-preview-content">加载中...</div></div>` : ''}
                                    ${fileText ? `<div class="file-message-text">${escapeHtml(fileText)}</div>` : ''}
                                    <div class="file-message-footer">
                                        <a href="${escapeHtml(displayFilePath)}" download="${escapeHtml(fileInfo.filename)}" class="file-download-btn">
                                            <i class="fas fa-download"></i> 下载
                                        </a>
                                        ${isPreviewable ? `<button class="file-preview-btn" onclick="toggleFilePreview(this)"><i class="fas fa-eye"></i> 预览</button>` : ''}
                                    </div>
                                </div>
                            `;
                        }
                        // 其他文件类型（通用文件卡片）
                        else {
                            const fileIcon = getFileIcon(fileType);
                            messageContent = `
                                <div class="file-message-card">
                                    <div class="file-message-header">
                                        <div class="file-icon">
                                            <i class="fas ${fileIcon}"></i>
                                        </div>
                                        <div class="file-details">
                                            <div class="file-name">${escapeHtml(fileInfo.filename)}</div>
                                            <div class="file-size">${fileSize}</div>
                                        </div>
                                        <a href="${escapeHtml(displayFilePath)}" download="${escapeHtml(fileInfo.filename)}" class="file-download-btn">
                                            <i class="fas fa-download"></i> 下载
                                        </a>
                                    </div>
                                    ${fileText ? `<div class="file-message-text">${escapeHtml(fileText)}</div>` : ''}
                                </div>
                            `;
                        }
                    }
                } catch (e) {
                    // 不是有效的 JSON 格式，保持原样
                    console.debug('不是 JSON 文件消息:', e);
                }
            }

            // 如果不是文件消息，确保使用原始消息（已由后端进行 Markdown 解析）
            // （messageContent 已在前面赋值为 msg.message，除非被识别为文件消息）

            // 判断是否为私聊消息且是自己发送的
            const isPrivateMessage = msg.sender_id !== undefined;
            const isOwnMessage = msg.sender_id === currentUserId;

            // 已读指示器 HTML（已移除）
            let readIndicatorHtml = '';

            el.innerHTML = `
                ${replyHtml}
                <div class="message-header">
                    <img src="${avatarUrl}" class="avatar" style="${isPublicChat ? 'display: none;' : ''}" onerror="this.src='${fallbackAvatar}'; this.onerror=null;">
                    <span class="username">${escapeHtml(username)}</span>
                    <span class="timestamp">${formatTime(msg.timestamp)}</span>
                    ${readIndicatorHtml}
                </div>
                <div class="message-content"></div>
            `;

            // 使用 innerHTML 添加消息内容，但需要先检查是否包含 HTML 标签
            const contentDiv = el.querySelector('.message-content');

            // 确保 messageContent 是有效的字符串
            const validContent = messageContent && typeof messageContent === 'string' ? messageContent.trim() : '';

            if (!validContent) {
                // 如果内容为空，显示占位符
                contentDiv.innerHTML = '<em style="color: var(--secondary-text);">[空消息]</em>';
            } else if (isFileMessage) {
                // 文件消息直接显示（已包含完整 HTML）
                contentDiv.innerHTML = validContent;
            } else {
                // 对于普通文本消息，检查是否包含 HTML 标签
                const hasHtmlTags = /<[a-z][\s\S]*>/i.test(validContent);

                if (hasHtmlTags) {
                    // 如果包含 HTML 标签，使用安全过滤
                    const sanitizedContent = sanitizeHtml(validContent);
                    // 确保过滤后还有内容
                    if (sanitizedContent && sanitizedContent.trim()) {
                        contentDiv.innerHTML = sanitizedContent;
                    } else {
                        // 过滤后为空，显示原文本（已转义）
                        contentDiv.textContent = validContent;
                    }
                } else {
                    // 纯文本或 Markdown 渲染的 HTML，直接显示
                    contentDiv.innerHTML = validContent;
                }
            }
            normalizeContentImages(contentDiv);

            // 检测消息中是否包含 iframe，给 .message 添加标记 class 以撑宽显示
            if (contentDiv.querySelector('iframe')) {
                el.classList.add('has-embed');
            }

            el.addEventListener('contextmenu', e => {
                e.preventDefault();
                showContextMenu(e, msg.id, msg.sender_id === undefined);
            });
            return el;
        }


        // 刷新已读状态（立即获取最新消息状态）

        function normalizeFileUrl(url) {
            if (!url) return '';
            const normalized = String(url).trim();
            if (!normalized) return '';
            const lower = normalized.toLowerCase();

            // 如果已经是完整 URL 或特殊协议，直接返回
            if (lower.startsWith('http://') || lower.startsWith('https://') ||
                lower.startsWith('data:') || lower.startsWith('blob:')) {
                return normalized;
            }

            // 静态资源（assets/）不需要代理（file_proxy 仅允许 uploads/avatars，会导致 403）
            const stripped = normalized.replace(/^\/+/, '');
            if (stripped.toLowerCase().startsWith('assets/')) {
                return normalized;
            }

            // 如果已经包含 file_proxy.php，说明已经被处理过，直接返回
            if (lower.includes('file_proxy.php')) {
                return normalized;
            }

            // 否则添加 file_proxy.php 代理
            return `file_proxy.php?path=${encodeURIComponent(normalized)}`;
        }

        function normalizeContentImages(container) {
            if (!container) return;
            container.querySelectorAll('img').forEach(img => {
                const src = img.getAttribute('src');
                if (!src) return;
                const normalized = normalizeFileUrl(src);
                if (normalized && normalized !== src) {
                    img.setAttribute('src', normalized);
                }
            });
        }

        // 统一头像回退，避免请求不存在的默认头像文件
        function resolveAvatarUrl(url) {
            const fallback = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"%3E%3Ccircle cx="50" cy="50" r="50" fill="%23e2e8f0"/%3E%3Ccircle cx="50" cy="35" r="18" fill="%2394a3b8"/%3E%3Cpath d="M 20 85 Q 20 60 50 60 Q 80 60 80 85 Z" fill="%2394a3b8"/%3E%3C/svg%3E';
            if (!url) return fallback;
            const normalized = String(url).trim();
            if (!normalized || normalized.toLowerCase().includes('default_avatar.png')) {
                return fallback;
            }
            return normalizeFileUrl(normalized);
        }

        // 新增：HTML 转义函数
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 新增：文件预览切换函数 - 重新设计以支持流畅动画
        function toggleFilePreview(btn) {
            const card = btn.closest('.file-message-card');
            const previewContainer = card.querySelector('.file-preview-container');

            if (!previewContainer) return;

            const isActive = previewContainer.classList.contains('active') && !previewContainer.classList.contains('closing');

            if (!isActive) {
                // ===== 显示预览 =====
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-eye-slash"></i> 隐藏';

                previewContainer.classList.remove('closing');

                // 检查是否需要加载内容（TXT/MD 文件）
                const filePath = previewContainer.dataset.filePath;
                const fileType = previewContainer.dataset.fileType;
                const content = previewContainer.querySelector('.file-preview-content');

                if (filePath && fileType && content.textContent === '加载中...') {
                    // 异步加载文件内容，然后显示动画
                    loadFilePreview(filePath, fileType, content, () => {
                        previewContainer.style.display = 'block';
                        // 触发重排，确保浏览器应用初始样式
                        previewContainer.offsetHeight;
                        previewContainer.classList.add('active');
                    });
                } else {
                    // 直接显示（已加载或没有内容）
                    previewContainer.style.display = 'block';
                    // 触发重排，确保浏览器应用初始样式
                    previewContainer.offsetHeight;
                    previewContainer.classList.add('active');
                }
            } else {
                // ===== 隐藏预览 =====
                btn.classList.remove('active');
                btn.innerHTML = '<i class="fas fa-eye"></i> 预览';

                // 过渡到收缩状态（使用 transition，避免 height:auto 动画导致的卡顿）
                previewContainer.classList.add('closing');

                let done_called = false;
                const done = () => {
                    if (done_called) return;
                    done_called = true;
                    previewContainer.classList.remove('active', 'closing');
                    previewContainer.style.display = 'none';
                    previewContainer.removeEventListener('transitionend', onTransitionEnd);
                };

                const onTransitionEnd = (e) => {
                    if (e.target !== previewContainer) return;
                    // 多个属性会触发 transitionend，等待 max-height 完成再收尾更自然
                    if (e.propertyName !== 'max-height') return;
                    done();
                };

                previewContainer.addEventListener('transitionend', onTransitionEnd);

                // 超时保险（防止事件未触发）
                setTimeout(done, 520);
            }
        }

        // 新增：加载文件预览内容
        function loadFilePreview(filePath, fileType, contentElement, onComplete) {
            const formData = new FormData();
            formData.append('action', 'get_file_preview');
            formData.append('file_path', filePath);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

            fetch('api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // data.content 可能包含由服务端 Markdown 生成的 HTML，插入 DOM 前先做一次前端过滤
                        const raw = (data && data.content !== undefined && data.content !== null) ? String(data.content) : '';
                        const safe = (typeof sanitizeHtml === 'function') ? sanitizeHtml(raw) : raw;
                        contentElement.innerHTML = safe;
                        // 如果是 Markdown，重新渲染
                        if (fileType === 'md') {
                        if (typeof MathJax !== 'undefined' && typeof MathJax.typesetPromise === 'function') {
                            MathJax.typesetPromise([contentElement]).then(() => {
                                if (onComplete) onComplete();
                            });
                        } else if (onComplete) {
                            onComplete();
                        }
                            highlightCodeBlocks(contentElement);
                        } else {
                            // TXT 文件直接显示
                            if (onComplete) onComplete();
                        }
                    } else {
                        const msg = (data && data.message !== undefined && data.message !== null) ? String(data.message) : '未知错误';
                        contentElement.innerHTML = `<div class="file-preview-error">加载失败: ${escapeHtml(msg)}</div>`;
                        if (onComplete) onComplete();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentElement.innerHTML = '<div class="file-preview-error">加载失败，请重试</div>';
                    if (onComplete) onComplete();
                });
        }

	        function appendMessagesBatch(messages, isOwnMessage = false) {
	            if (!Array.isArray(messages) || messages.length === 0) return;

	            const loader = messagesContainer.querySelector('.loader');
	            if (loader) loader.remove();

	            // 判断是否应该滚动：自己发送的消息始终滚动，或者用户在底部附近
	            const isNearBottomNow = (messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight) <= 150;
	            const shouldScroll = isOwnMessage || isNearBottomNow;

	            const fragment = document.createDocumentFragment();
	            const mathTargets = [];
	            const highlightTargets = [];
	            const appended = [];

	            messages.forEach(msg => {
	                const id = normalizeMessageId(msg && msg.id);
	                if (!id || displayedMessageIds.has(id)) return;
	                displayedMessageIds.add(id);
	                const el = createMessageElement(msg);
	                // 使用 CSS 默认动画，避免重复设置导致的抖动
	                fragment.appendChild(el);
	                appended.push(msg);
	                updateLatestTimestamp(msg.timestamp);
	                updateLatestId(msg.id);

	                if (contentMayContainMath(msg.message)) {
	                    const contentDiv = el.querySelector('.message-content');
	                    if (contentDiv) mathTargets.push(contentDiv);
	                }
	                if (elementHasCodeBlocks(el)) {
	                    highlightTargets.push(el);
	                }
	            });

	            if (appended.length === 0) return;
	            messagesContainer.appendChild(fragment);

	            // 使用 requestAnimationFrame 确保在渲染后滚动
	            if (shouldScroll) {
	                requestAnimationFrame(() => {
	                    scrollToBottom(false);
	                    // 再延迟一小段时间确保滚动生效
	                    setTimeout(() => scrollToBottom(false), 50);
	                });
	            }

	            // 维护内存窗口，避免数组无限增长
	            currentDisplayedMessages = currentDisplayedMessages.concat(appended);
	            if (currentDisplayedMessages.length > 1000) {
	                currentDisplayedMessages = currentDisplayedMessages.slice(-1000);
	            }

	            scheduleIdle(() => {
                if (mathTargets.length > 0 && typeof MathJax !== 'undefined' && typeof MathJax.typesetPromise === 'function') {
                    MathJax.typesetPromise(mathTargets).catch(err => console.warn('MathJax 渲染失败:', err));
                }
	                highlightTargets.forEach(el => {
	                    try {
	                        highlightCodeBlocks(el);
	                    } catch (e) {
	                        console.warn('代码块高亮失败:', e);
	                    }
	                });
	            }, 300);
	        }

	        function appendMessage(msg, isOwnMessage = false) {
	            try {
	                appendMessagesBatch([msg], isOwnMessage);
	            } catch (error) {
	                console.error('appendMessage 失败:', error);
	                showToast('消息显示失败，请刷新页面', 'error');
	            }
	        }
        
        function updateLatestTimestamp(timestamp) {
            if (isPublicChat) {
                if (timestamp > latestPublicTimestamp) latestPublicTimestamp = timestamp;
            } else {
                if (timestamp > latestPrivateTimestamp) latestPrivateTimestamp = timestamp;
            }
        }

        function updateLatestId(id) {
            const messageId = Number(id || 0);
            if (!messageId) return;
            if (isPublicChat) {
                if (messageId > latestPublicId) latestPublicId = messageId;
            } else {
                if (messageId > latestPrivateId) latestPrivateId = messageId;
            }
        }

        function formatTime(timestamp) {
            if (!timestamp) return '未知时间';
            const date = new Date(timestamp * 1000);
            const now = new Date();
            const diff = (now - date) / 1000;
            if (diff < 60) return '刚刚';
            if (diff < 3600) return `${Math.floor(diff / 60)}分钟前`;

            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const msgDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            const timeDiffDays = (today - msgDate) / 86400000;
            const timeString = `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
            if (timeDiffDays < 1) return timeString;
            if (timeDiffDays < 2) return `昨天 ${timeString}`;
            return `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`;
        }
        
        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);
            const intervalMs = isDesktop ? 1000 : 2000;
            pollingInterval = setInterval(pollNewMessages, intervalMs);
        }

        // 占位的未读消息统计函数，避免缺失定义导致轮询报错
        function fetchUnreadMessages() {
            // 目前未实现未读消息角标逻辑，但轮询依赖该函数存在
            return;
        }

	        function pollNewMessages() {
	            if (pollingInFlight) return;
	            pollingInFlight = true;
	            const formData = new FormData();
            formData.append('action', 'check_new_messages');
            // ID 游标：避免同秒消息丢失
            formData.append('lastPublicId', latestPublicId);
            formData.append('lastPrivateId', latestPrivateId);
            // 兼容字段（服务端若未更新可继续按 timestamp 逻辑走）
            formData.append('lastPublicTimestamp', latestPublicTimestamp);
            formData.append('lastPrivateTimestamp', latestPrivateTimestamp);

            // 撤回检测：只关心当前已加载窗口范围内的消息
            formData.append('minPublicId', publicHistoryState && publicHistoryState.oldestId ? publicHistoryState.oldestId : 0);
            formData.append('maxPublicId', latestPublicId || 0);
            const privateState = (typeof getPrivateHistoryState === 'function' && selectedReceiverId)
                ? getPrivateHistoryState(selectedReceiverId)
                : null;
            formData.append('minPrivateId', privateState && privateState.oldestId ? privateState.oldestId : 0);
            formData.append('maxPrivateId', latestPrivateId || 0);

            formData.append('currentReceiverId', selectedReceiverId || 0);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

	            fetch('api.php', { method: 'POST', body: formData })
	            .then(res => res.json())
	            .then(data => {
	                if (data.success) {
	                    if (isPublicChat && Array.isArray(data.newPublicMessages) && data.newPublicMessages.length > 0) {
	                        appendMessagesBatch(data.newPublicMessages);
	                    }
	                    if (!isPublicChat && selectedReceiverId && Array.isArray(data.newPrivateMessages) && data.newPrivateMessages.length > 0) {
	                        appendMessagesBatch(data.newPrivateMessages);
	                    }

	                    // 处理已撤回的消息
	                    // 注意：公聊/私聊的消息 ID 不是全局唯一，必须只在当前面板处理，避免误删。
	                    if (isPublicChat && Array.isArray(data.recalledPublicIds) && data.recalledPublicIds.length > 0) {
	                        const recalled = new Set();
	                        data.recalledPublicIds.forEach(rawId => {
	                            const id = normalizeMessageId(rawId);
	                            if (id) recalled.add(id);
	                        });
	                        recalled.forEach(msgId => {
	                            const el = messagesContainer.querySelector(`[data-message-id="${msgId}"]`);
	                            if (el) {
	                                el.classList.add('message-removing');
	                                el.addEventListener('animationend', () => el.remove(), { once: true });
	                            }
	                            displayedMessageIds.delete(msgId);
	                        });
	                        if (recalled.size > 0 && Array.isArray(currentDisplayedMessages)) {
	                            currentDisplayedMessages = currentDisplayedMessages.filter(m => !recalled.has(normalizeMessageId(m && m.id)));
	                        }
	                    }
	                    if (!isPublicChat && selectedReceiverId && Array.isArray(data.recalledPrivateIds) && data.recalledPrivateIds.length > 0) {
	                        const recalled = new Set();
	                        data.recalledPrivateIds.forEach(rawId => {
	                            const id = normalizeMessageId(rawId);
	                            if (id) recalled.add(id);
	                        });
	                        recalled.forEach(msgId => {
	                            const el = messagesContainer.querySelector(`[data-message-id="${msgId}"]`);
	                            if (el) {
	                                el.classList.add('message-removing');
	                                el.addEventListener('animationend', () => el.remove(), { once: true });
	                            }
	                            displayedMessageIds.delete(msgId);
	                        });
	                        if (recalled.size > 0 && Array.isArray(currentDisplayedMessages)) {
	                            currentDisplayedMessages = currentDisplayedMessages.filter(m => !recalled.has(normalizeMessageId(m && m.id)));
	                        }
	                    }

	                    // 更新用户在线状态
	                    updateAllUserOnlineStatus();
	                }
	            })
	            .catch(error => console.error('Error polling messages:', error))
	            .finally(() => {
	                pollingInFlight = false;
	            });

            // 同时获取未读消息统计
            fetchUnreadMessages();

	        }

        // 检查当前显示消息的已读状态更新
        let lastReadStatusCheck = 0;
        function checkReadStatusUpdates() {
            const now = Date.now();
            // 每3秒检查一次（从5秒优化到3秒）
            if (now - lastReadStatusCheck < 3000) return;
            lastReadStatusCheck = now;

            // 获取当前显示的所有自己发送的未读消息
            const unreadMessages = currentDisplayedMessages.filter(msg =>
                Number(msg.sender_id) === Number(currentUserId) &&
                (msg.is_read === false || msg.is_read === 0 || msg.is_read === '0' || msg.is_read === 'false' || !msg.is_read)
            );

            // 如果没有未读消息，跳过检查
            if (unreadMessages.length === 0) return;

            console.debug(`检查 ${unreadMessages.length} 条未读消息的状态...`);

            // 请求这些消息的最新已读状态
            const formData = new FormData();
            formData.append('action', 'get_private_messages');
            formData.append('receiver_id', selectedReceiverId);
            // 不需要拉全量：已读状态通常只关心“最近一段”里自己发的未读消息
            formData.append('limit', '300');
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages) {
                    // 更新 currentDisplayedMessages 中的状态
                    data.messages.forEach(newMsg => {
                        const index = currentDisplayedMessages.findIndex(m => normalizeMessageId(m && m.id) === normalizeMessageId(newMsg && newMsg.id));
                        if (index !== -1) {
                            currentDisplayedMessages[index].is_read = newMsg.is_read;
                        }
                    });
                    // 更新已读指示器
                    updateReadIndicators(data.messages);
                }
            })
            .catch(err => console.error('检查已读状态失败:', err));
        }

        function calculateMenuPosition(clickX, clickY, menuWidth = 150, menuHeight = 120) {
            // 计算菜单水平位置（如果超出右边界则向左调整）
            const x = Math.min(clickX, window.innerWidth - menuWidth);

            // 判断是否需要向上展开（如果菜单超出下边界）
            const shouldExpandUp = (clickY + menuHeight) > window.innerHeight;
            const y = shouldExpandUp ? window.innerHeight - clickY : clickY;

            return { x, y, shouldExpandUp };
        }

        async function showContextMenu(event, messageId, isPublic) {
            document.querySelectorAll('.context-menu').forEach(menu => menu.remove());
            const menu = document.createElement('div');
            menu.className = 'context-menu';
            menu.innerHTML = `<div class="context-item" data-action="reply">回复</div><div class="context-item" data-action="recall">撤回</div>`;
            document.body.appendChild(menu);

            // 计算菜单位置和展开方向
            const { x, y, shouldExpandUp } = calculateMenuPosition(event.clientX, event.clientY);

            // 设置菜单位置
            menu.style.left = `${x}px`;
            if (shouldExpandUp) {
                menu.style.bottom = `${y}px`;
                menu.style.top = 'auto';
            } else {
                menu.style.top = `${y}px`;
                menu.style.bottom = 'auto';
            }

            // 添加展开动画类（延迟 80ms，持续 250ms）
            menu.classList.add(shouldExpandUp ? 'animate-expand-up' : 'animate-expand-down');

            // 菜单项点击处理
            menu.addEventListener('click', async (e) => {
                const item = e.target.closest('.context-item');
                if (!item) return;

                const action = item.dataset.action;
                if (action === 'reply') {
                    handleMenuReply(messageId);
                } else if (action === 'recall') {
                    const confirmed = await showConfirm('确定要撤回这条消息吗？', '撤回', '取消');
                    if (confirmed) recallMessage(messageId, isPublic);
                }
                menu.remove();
            });

            // 点击外部关闭菜单
            setTimeout(() => {
                const closeHandler = () => {
                    menu.remove();
                    document.removeEventListener('click', closeHandler);
                };
                document.addEventListener('click', closeHandler);
            }, 0);
        }

        function handleMenuReply(messageId) {
            replyToId = messageId;
            const msgEl = messagesContainer.querySelector(`[data-message-id="${messageId}"]`);
            if (msgEl) {
                const username = msgEl.querySelector('.username').textContent;
                const safeUsername = (typeof escapeHtml === 'function') ? escapeHtml(username) : username;
                const content = msgEl.querySelector('.message-content').innerHTML;
                replyPreviewContent.innerHTML = `<strong>回复 ${safeUsername}:</strong> ${content}`;
                replyPreview.style.display = 'flex';
                messageInput.focus();
                updateScrollBtnPosition();
            }
        }

        function recallMessage(messageId, isPublic) {
            const formData = new FormData();
            formData.append('action', 'recall_message');
            formData.append('message_id', messageId);
            formData.append('is_private', !isPublic);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const normalizedId = normalizeMessageId(messageId);
                    const el = messagesContainer.querySelector(`[data-message-id="${normalizedId || messageId}"]`);
                    if (el) {
                        // Smooth remove animation
                        el.classList.add('message-removing');
                        el.addEventListener('animationend', () => el.remove(), { once: true });
                    }
                    if (normalizedId) {
                        displayedMessageIds.delete(normalizedId);
                        if (Array.isArray(currentDisplayedMessages) && currentDisplayedMessages.length > 0) {
                            currentDisplayedMessages = currentDisplayedMessages.filter(m => normalizeMessageId(m && m.id) !== normalizedId);
                        }
                    }
                } else {
                    showToast('撤回失败: ' + data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

