/**
 * 虚拟滚动集成模块
 * 将虚拟滚动功能集成到现有消息系统
 *
 * 临时禁用：如果遇到问题，将下面的 ENABLE_VIRTUAL_SCROLL 改为 false
 */

(function() {
    'use strict';

    // 临时开关：设置为 false 可以禁用虚拟滚动
    const ENABLE_VIRTUAL_SCROLL = false;

    if (!ENABLE_VIRTUAL_SCROLL) {
        console.log('虚拟滚动已禁用，使用传统加载方式');
        return;
    }

    let virtualScrollEnabled = false;
    let virtualScroll = null;

    // 等待 DOM 和依赖加载完成
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVirtualScroll);
    } else {
        initVirtualScroll();
    }

    function getMessagesContainer() {
        return document.getElementById('messagesContainer') || document.getElementById('messages-container');
    }

    function initVirtualScroll() {
        // 检查依赖是否存在
        if (typeof VirtualScrollManager === 'undefined') {
            console.warn('VirtualScrollManager 未加载，跳过虚拟滚动初始化');
            return;
        }

        // 容器在 DOMContentLoaded 后通常已存在，优先同步初始化，避免竞态
        const existingContainer = getMessagesContainer();
        if (existingContainer) {
            setupVirtualScroll(existingContainer);
            return;
        }

        // 等待消息容器准备就绪
        let timeoutId = null;
        const checkContainer = setInterval(() => {
            const container = getMessagesContainer();
            if (container) {
                clearInterval(checkContainer);
                if (timeoutId) clearTimeout(timeoutId);
                setupVirtualScroll(container);
            }
        }, 100);

        // 10秒后超时
        timeoutId = setTimeout(() => clearInterval(checkContainer), 10000);
    }

    function setupVirtualScroll(container) {
        // 创建虚拟滚动管理器实例
        virtualScroll = new VirtualScrollManager(container, {
            itemHeight: 100, // 预估消息高度（稍微大一点更安全）
            bufferSize: 15, // 上下缓冲区（增加缓冲区避免白屏）
            renderBatchSize: 50, // 首屏渲染数量（改为50条）
            onLoadMore: handleLoadMore,
            onRenderMessage: renderMessage
        });

        // 保存到全局以便其他模块访问
        window.virtualScrollManager = virtualScroll;
        virtualScrollEnabled = true;

        // 拦截原有的消息加载函数
        interceptMessageLoading(virtualScroll);

        console.log('虚拟滚动已启用：首屏加载50条，滚动到顶部自动加载更多');
    }

    /**
     * 处理加载更多历史消息
     */
    async function handleLoadMore(oldestMessage) {
        if (!oldestMessage || !oldestMessage.id) {
            console.log('没有最早的消息，无法加载更多');
            return { messages: [], hasMore: false };
        }

        console.log('开始加载历史消息，before_id:', oldestMessage.id);

        try {
            const isPublic = typeof isPublicChat !== 'undefined' ? isPublicChat : true;
            const beforeId = oldestMessage.id;

            let result;
            if (isPublic) {
                // 加载公聊历史消息
                result = await window.loadPublicMessages(null, beforeId);
            } else {
                // 加载私聊历史消息
                result = await window.loadPrivateMessages(null, beforeId);
            }

            if (result && result.messages) {
                console.log(`成功加载 ${result.messages.length} 条历史消息，hasMore: ${result.hasMore}`);

                // 同步状态：把历史消息纳入去重与内存窗口（避免轮询/撤回逻辑异常）
                try {
                    const historyMessages = Array.isArray(result.messages) ? result.messages : [];
                    if (typeof displayedMessageIds !== 'undefined' && displayedMessageIds && typeof displayedMessageIds.add === 'function') {
                        historyMessages.forEach(msg => {
                            if (msg && msg.id) displayedMessageIds.add(msg.id);
                        });
                    }
                    if (typeof currentDisplayedMessages !== 'undefined' && Array.isArray(currentDisplayedMessages)) {
                        currentDisplayedMessages = currentDisplayedMessages.concat(historyMessages);
                        if (currentDisplayedMessages.length > 1000) {
                            currentDisplayedMessages = currentDisplayedMessages.slice(-1000);
                        }
                    }
                } catch (e) {
                    console.warn('虚拟滚动：同步历史消息状态失败:', e);
                }

                return {
                    messages: result.messages.reverse(), // 反转为旧→新顺序
                    hasMore: result.hasMore || false
                };
            }

            console.log('没有加载到历史消息');
            return { messages: [], hasMore: false };
        } catch (error) {
            console.error('加载历史消息失败:', error);
            return { messages: [], hasMore: false };
        }
    }

    /**
     * 渲染单条消息
     */
    function renderMessage(msg) {
        // 使用现有的 createMessageElement 函数
        if (typeof window.createMessageElement === 'function') {
            return window.createMessageElement(msg);
        }

        // 降级方案：创建简单的消息元素
        const el = document.createElement('div');
        el.className = 'message';
        el.dataset.messageId = msg.id;
        el.innerHTML = `
            <div class="message-header">
                <span class="username">${escapeHtml(msg.username || msg.sender_username || '未知用户')}</span>
                <span class="timestamp">${formatTime(msg.timestamp)}</span>
            </div>
            <div class="message-content">${msg.message || ''}</div>
        `;
        return el;
    }

    /**
     * 拦截原有的消息加载函数
     */
    function interceptMessageLoading(virtualScroll) {
        // 保存原始函数
        const originalDisplayInitialMessages = window.displayInitialMessages;
        const originalAppendMessagesBatch = window.appendMessagesBatch;

        // 重写 displayInitialMessages
        if (typeof originalDisplayInitialMessages === 'function') {
            window.displayInitialMessages = function(messages) {
                if (virtualScrollEnabled && virtualScroll && Array.isArray(messages)) {
                    console.log(`虚拟滚动：初始化 ${messages.length} 条消息`);

                    // 同步状态（去重集合/最新时间戳/内存窗口），避免影响轮询与撤回
                    try {
                        if (typeof displayedMessageIds !== 'undefined' && displayedMessageIds && typeof displayedMessageIds.clear === 'function') {
                            displayedMessageIds.clear();
                        }
                        if (typeof isPublicChat !== 'undefined') {
                            if (isPublicChat && typeof latestPublicTimestamp !== 'undefined') latestPublicTimestamp = 0;
                            if (!isPublicChat && typeof latestPrivateTimestamp !== 'undefined') latestPrivateTimestamp = 0;
                        }
                        if (typeof currentDisplayedMessages !== 'undefined') {
                            currentDisplayedMessages = [...messages];
                        }
                        messages.forEach(msg => {
                            if (!msg || !msg.id) return;
                            if (typeof displayedMessageIds !== 'undefined' && displayedMessageIds && typeof displayedMessageIds.add === 'function') {
                                displayedMessageIds.add(msg.id);
                            }
                            if (typeof updateLatestTimestamp === 'function') {
                                updateLatestTimestamp(msg.timestamp);
                            }
                        });
                    } catch (e) {
                        console.warn('虚拟滚动：同步初始化状态失败:', e);
                    }

                    // 后端返回的是新→旧，需要反转为旧→新
                    const orderedMessages = [...messages].reverse();
                    virtualScroll.setMessages(orderedMessages);

                    // 仅对可见区域做一次低优先级增强渲染（数学公式/代码高亮）
                    if (typeof scheduleIdle === 'function') {
                        scheduleIdle(() => {
                            try {
                                if (typeof MathJax !== 'undefined' && typeof MathJax.typesetPromise === 'function') {
                                    MathJax.typesetPromise([virtualScroll.contentContainer])
                                        .catch(err => console.warn('MathJax 渲染失败:', err));
                                }
                                if (typeof highlightCodeBlocks === 'function') {
                                    highlightCodeBlocks(virtualScroll.contentContainer);
                                }
                            } catch (e) {
                                console.warn('虚拟滚动：后处理失败:', e);
                            }
                        }, 500);
                    }
                } else {
                    originalDisplayInitialMessages.call(this, messages);
                }
            };
        }

        // 重写 appendMessagesBatch
        if (typeof originalAppendMessagesBatch === 'function') {
            window.appendMessagesBatch = function(messages, isOwnMessage) {
                if (virtualScrollEnabled && virtualScroll && Array.isArray(messages)) {
                    console.log(`虚拟滚动：追加 ${messages.length} 条新消息`);

                    const appended = [];
                    messages.forEach(msg => {
                        if (!msg || !msg.id) return;
                        if (typeof displayedMessageIds !== 'undefined' && displayedMessageIds && typeof displayedMessageIds.has === 'function') {
                            if (displayedMessageIds.has(msg.id)) return;
                            displayedMessageIds.add(msg.id);
                        }
                        appended.push(msg);
                        if (typeof updateLatestTimestamp === 'function') {
                            updateLatestTimestamp(msg.timestamp);
                        }
                    });

                    if (appended.length === 0) return;

                    if (typeof currentDisplayedMessages !== 'undefined' && Array.isArray(currentDisplayedMessages)) {
                        currentDisplayedMessages = currentDisplayedMessages.concat(appended);
                        if (currentDisplayedMessages.length > 1000) {
                            currentDisplayedMessages = currentDisplayedMessages.slice(-1000);
                        }
                    }

                    virtualScroll.appendMessages(appended);

                    // 保持原有行为：自己发送的消息强制滚动到底部
                    if (isOwnMessage) {
                        requestAnimationFrame(() => virtualScroll.scrollToBottom(false));
                    }

                    if (typeof scheduleIdle === 'function') {
                        scheduleIdle(() => {
                            try {
                                if (typeof MathJax !== 'undefined' && typeof MathJax.typesetPromise === 'function') {
                                    MathJax.typesetPromise([virtualScroll.contentContainer])
                                        .catch(err => console.warn('MathJax 渲染失败:', err));
                                }
                                if (typeof highlightCodeBlocks === 'function') {
                                    highlightCodeBlocks(virtualScroll.contentContainer);
                                }
                            } catch (e) {
                                console.warn('虚拟滚动：增量后处理失败:', e);
                            }
                        }, 300);
                    }
                } else {
                    originalAppendMessagesBatch.call(this, messages, isOwnMessage);
                }
            };
        }
    }

    /**
     * HTML 转义
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * 格式化时间
     */
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

})();
