/**
 * 虚拟滚动管理器 - 只渲染可见区域的消息
 * 优化大量消息时的性能和内存占用
 */
class VirtualScrollManager {
    constructor(container, options = {}) {
        this.container = container;
        this.messages = []; // 所有消息数据（旧→新）
        this.visibleRange = { start: 0, end: 0 }; // 当前可见范围
        this.itemHeight = options.itemHeight || 80; // 预估消息高度
        this.bufferSize = options.bufferSize || 10; // 上下缓冲区大小
        this.renderBatchSize = options.renderBatchSize || 30; // 首屏渲染数量
        this.itemGap = 0; // 消息间距（像素）

        // DOM 元素
        this.viewport = null;
        this.spacerTop = null;
        this.spacerBottom = null;
        this.contentContainer = null;

        // 状态
        this.isScrolling = false;
        this.scrollTimeout = null;
        this.heightCache = new Map(); // 缓存每条消息的实际高度
        this.isLoadingMore = false;
        this.hasMoreHistory = true;

        // 回调函数
        this.onLoadMore = options.onLoadMore || null;
        this.onRenderMessage = options.onRenderMessage || null;

        this.handleScrollBound = this.handleScroll.bind(this);

        this.init();
    }

    init() {
        // 防止重复绑定滚动事件
        this.container.removeEventListener('scroll', this.handleScrollBound);

        // 读取容器原本的 gap（来自 CSS），用于保持消息间距
        let rowGapValue = '0px';
        try {
            const previousGap = this.container.style.gap;
            this.container.style.gap = '';
            const computedStyle = window.getComputedStyle(this.container);
            rowGapValue = computedStyle.rowGap || computedStyle.gap || '0px';
            this.container.style.gap = previousGap;
        } catch (e) {
            rowGapValue = '0px';
        }
        this.itemGap = parseFloat(rowGapValue) || 0;

        // 创建虚拟滚动结构
        this.container.innerHTML = '';
        this.container.style.position = 'relative';
        this.container.style.overflow = 'auto';
        // 避免 flex 的 gap 影响占位高度计算；把间距下放到 contentContainer
        this.container.style.gap = '0px';

        this.spacerTop = document.createElement('div');
        this.spacerTop.className = 'virtual-scroll-spacer-top';
        this.spacerTop.style.height = '0px';
        this.spacerTop.style.flexShrink = '0';

        this.contentContainer = document.createElement('div');
        this.contentContainer.className = 'virtual-scroll-content';
        this.contentContainer.style.display = 'flex';
        this.contentContainer.style.flexDirection = 'column';
        this.contentContainer.style.gap = rowGapValue;
        this.contentContainer.style.flexShrink = '0';

        this.spacerBottom = document.createElement('div');
        this.spacerBottom.className = 'virtual-scroll-spacer-bottom';
        this.spacerBottom.style.height = '0px';
        this.spacerBottom.style.flexShrink = '0';

        this.container.appendChild(this.spacerTop);
        this.container.appendChild(this.contentContainer);
        this.container.appendChild(this.spacerBottom);

        // 绑定滚动事件
        this.container.addEventListener('scroll', this.handleScrollBound, { passive: true });
    }

    ensureStructure() {
        if (!this.container) return;
        if (
            this.spacerTop &&
            this.spacerBottom &&
            this.contentContainer &&
            this.container.contains(this.spacerTop) &&
            this.container.contains(this.contentContainer) &&
            this.container.contains(this.spacerBottom)
        ) {
            return;
        }
        this.init();
    }

    getItemHeightByIndex(index) {
        const msg = this.messages[index];
        if (!msg) return this.itemHeight;

        const cached = this.heightCache.get(msg.id);
        if (typeof cached === 'number' && cached > 0) return cached;
        return this.itemHeight;
    }

    getItemOuterHeightByIndex(index) {
        let height = this.getItemHeightByIndex(index);
        if (this.itemGap > 0 && index < this.messages.length - 1) {
            height += this.itemGap;
        }
        return height;
    }

    /**
     * 设置消息列表（旧→新顺序）
     */
    setMessages(messages) {
        this.ensureStructure();
        this.messages = messages;
        this.heightCache.clear();

        // 计算初始渲染范围（从底部开始）
        const totalCount = messages.length;
        const start = Math.max(0, totalCount - this.renderBatchSize);
        const end = totalCount;

        this.visibleRange = { start, end };
        this.render();

        // 滚动到底部
        requestAnimationFrame(() => {
            this.container.scrollTop = this.container.scrollHeight;
        });
    }

    /**
     * 追加新消息
     */
    appendMessages(newMessages) {
        if (!Array.isArray(newMessages) || newMessages.length === 0) return;

        this.ensureStructure();
        const wasAtBottom = this.isNearBottom();
        this.messages.push(...newMessages);

        // 如果在底部，扩展可见范围
        if (wasAtBottom) {
            this.visibleRange.end = this.messages.length;
            this.render();

            requestAnimationFrame(() => {
                this.container.scrollTop = this.container.scrollHeight;
            });
        }
    }

    /**
     * 前置历史消息（用于向上滚动加载）
     */
    prependMessages(oldMessages) {
        if (!Array.isArray(oldMessages) || oldMessages.length === 0) return Promise.resolve();

        this.ensureStructure();

        const oldScrollHeight = this.container.scrollHeight;
        const oldScrollTop = this.container.scrollTop;

        // 以当前可见区域的第一条消息作为锚点，避免预估高度导致的跳动
        const anchorEl = this.contentContainer ? this.contentContainer.firstElementChild : null;
        const anchorId = anchorEl && anchorEl.dataset ? anchorEl.dataset.messageId : null;
        let anchorOffset = null;
        if (anchorEl && anchorId) {
            const containerRect = this.container.getBoundingClientRect();
            const anchorRect = anchorEl.getBoundingClientRect();
            anchorOffset = anchorRect.top - containerRect.top;
        }

        // 插入到数组开头
        this.messages.unshift(...oldMessages);

        // 调整可见范围
        this.visibleRange.start += oldMessages.length;
        this.visibleRange.end += oldMessages.length;

        this.render();

        // 保持滚动位置：等待布局稳定后再校正
        return new Promise(resolve => {
            requestAnimationFrame(() => {
                // 优先使用锚点校正（更稳定），失败则回退到 scrollHeight 差值
                if (anchorId && anchorOffset !== null && this.contentContainer) {
                    const newAnchorEl = this.contentContainer.querySelector(`[data-message-id="${anchorId}"]`);
                    if (newAnchorEl) {
                        const containerRect = this.container.getBoundingClientRect();
                        const newAnchorRect = newAnchorEl.getBoundingClientRect();
                        const newOffset = newAnchorRect.top - containerRect.top;
                        const delta = newOffset - anchorOffset;
                        if (Number.isFinite(delta) && delta !== 0) {
                            this.container.scrollTop += delta;
                        }
                        resolve();
                        return;
                    }
                }

                const newScrollHeight = this.container.scrollHeight;
                this.container.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
                resolve();
            });
        });
    }

    /**
     * 渲染可见范围的消息
     */
    render() {
        this.ensureStructure();
        const { start, end } = this.visibleRange;
        const fragment = document.createDocumentFragment();

        // 清空当前内容
        this.contentContainer.innerHTML = '';

        // 渲染可见消息
        for (let i = start; i < end; i++) {
            const msg = this.messages[i];
            if (!msg) continue;

            const el = this.onRenderMessage ? this.onRenderMessage(msg) : this.createDefaultElement(msg);
            fragment.appendChild(el);

            // 缓存实际高度
            requestAnimationFrame(() => {
                if (el.offsetHeight > 0) {
                    this.heightCache.set(msg.id, el.offsetHeight);
                }
            });
        }

        this.contentContainer.appendChild(fragment);

        // 更新占位符高度
        this.updateSpacers();
    }

    /**
     * 更新上下占位符高度
     */
    updateSpacers() {
        const { start, end } = this.visibleRange;

        // 计算顶部占位高度
        let topHeight = 0;
        for (let i = 0; i < start; i++) {
            topHeight += this.getItemOuterHeightByIndex(i);
        }

        // 计算底部占位高度
        let bottomHeight = 0;
        for (let i = end; i < this.messages.length; i++) {
            bottomHeight += this.getItemOuterHeightByIndex(i);
        }
        // 补齐「最后一条可见消息」与「第一条不可见消息」之间的 gap
        if (this.itemGap > 0 && end > 0 && end < this.messages.length) {
            bottomHeight += this.itemGap;
        }

        this.spacerTop.style.height = `${topHeight}px`;
        this.spacerBottom.style.height = `${bottomHeight}px`;
    }

    /**
     * 处理滚动事件
     */
    handleScroll() {
        if (this.isScrolling) return;

        this.isScrolling = true;
        clearTimeout(this.scrollTimeout);

        this.scrollTimeout = setTimeout(() => {
            this.isScrolling = false;
            this.updateVisibleRange();

            // 检查是否需要加载更多历史消息
            if (this.isNearTop() && !this.isLoadingMore && this.hasMoreHistory) {
                this.loadMoreHistory();
            }
        }, 100);
    }

    /**
     * 更新可见范围
     */
    updateVisibleRange() {
        this.ensureStructure();
        const scrollTop = this.container.scrollTop;
        const viewportHeight = this.container.clientHeight;
        const bufferPixels = this.bufferSize * (this.itemHeight + this.itemGap);

        // 计算可见范围
        let accumulatedHeight = 0;
        let start = 0;
        let end = this.messages.length;

        // 找到起始索引
        for (let i = 0; i < this.messages.length; i++) {
            const height = this.getItemOuterHeightByIndex(i);

            if (accumulatedHeight + height >= scrollTop - bufferPixels) {
                start = Math.max(0, i - this.bufferSize);
                break;
            }
            accumulatedHeight += height;
        }

        // 找到结束索引
        accumulatedHeight = 0;
        for (let i = start; i < this.messages.length; i++) {
            const height = this.getItemOuterHeightByIndex(i);
            accumulatedHeight += height;

            if (accumulatedHeight >= viewportHeight + bufferPixels) {
                end = Math.min(this.messages.length, i + this.bufferSize);
                break;
            }
        }

        // 如果范围变化，重新渲染
        if (start !== this.visibleRange.start || end !== this.visibleRange.end) {
            this.visibleRange = { start, end };
            this.render();
        }
    }

    /**
     * 加载更多历史消息
     */
    async loadMoreHistory() {
        this.ensureStructure();
        if (!this.onLoadMore || this.isLoadingMore || !this.hasMoreHistory) return;

        this.isLoadingMore = true;
        this.showLoadingIndicator();

        try {
            const oldestMessage = this.messages[0];
            const result = await this.onLoadMore(oldestMessage);

            if (result && result.messages && result.messages.length > 0) {
                await this.prependMessages(result.messages);
                this.hasMoreHistory = result.hasMore !== false;
            } else {
                this.hasMoreHistory = false;
            }
        } catch (error) {
            console.error('加载历史消息失败:', error);
        } finally {
            this.isLoadingMore = false;
            this.hideLoadingIndicator();
        }
    }

    /**
     * 显示加载指示器
     */
    showLoadingIndicator() {
        if (this.loadingIndicator) return;

        this.loadingIndicator = document.createElement('div');
        this.loadingIndicator.className = 'virtual-scroll-loading';
        this.loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 加载历史消息...';
        // 使用绝对定位避免影响 scrollHeight，减少加载时的跳动
        this.loadingIndicator.style.cssText = 'position: absolute; top: 8px; left: 0; right: 0; z-index: 2; text-align: center; padding: 10px; color: var(--secondary-text); pointer-events: none;';

        this.container.insertBefore(this.loadingIndicator, this.spacerTop);
    }

    /**
     * 隐藏加载指示器
     */
    hideLoadingIndicator() {
        if (this.loadingIndicator) {
            this.loadingIndicator.remove();
            this.loadingIndicator = null;
        }
    }

    /**
     * 判断是否在顶部附近
     */
    isNearTop() {
        return this.container.scrollTop < 200;
    }

    /**
     * 判断是否在底部附近
     */
    isNearBottom() {
        const threshold = 150;
        return (this.container.scrollHeight - this.container.scrollTop - this.container.clientHeight) <= threshold;
    }

    /**
     * 滚动到底部
     */
    scrollToBottom(smooth = false) {
        if (smooth) {
            this.container.scrollTo({
                top: this.container.scrollHeight,
                behavior: 'smooth'
            });
        } else {
            this.container.scrollTop = this.container.scrollHeight;
        }
    }

    /**
     * 默认消息元素创建（如果没有提供自定义渲染函数）
     */
    createDefaultElement(msg) {
        const el = document.createElement('div');
        el.className = 'message';
        el.textContent = msg.message || '空消息';
        return el;
    }

    /**
     * 清理资源
     */
    destroy() {
        this.container.removeEventListener('scroll', this.handleScrollBound);
        clearTimeout(this.scrollTimeout);
        this.messages = [];
        this.heightCache.clear();
    }
}

// 导出到全局
window.VirtualScrollManager = VirtualScrollManager;
