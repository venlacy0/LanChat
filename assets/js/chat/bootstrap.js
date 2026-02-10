        // --- State Variables ---
	        let latestPublicTimestamp = 0;
	        let latestPrivateTimestamp = 0;
	        let pollingInterval = null;
	        let pollingInFlight = false;
	        let isPublicChat = true;
	        let selectedReceiverId = null;
	        let replyToId = null;
	        let currentTab = 'public';
	        let outsideFocusClickHandler = null;
	        let publicLoadToken = 0;
	        let privateLoadToken = 0;
	        let publicLoadController = null;
	        let privateLoadController = null;

	        // --- Cache Configuration ---
        const CACHE_EXPIRY_DAYS = 7; // 缓存有效期：7天
        const CACHE_VERSION = 'v1'; // 缓存版本，用于清理旧缓存
        const MAX_UPLOAD_BYTES = 4 * 1024 * 1024; // 4MB 上传限制

        // --- Cache Management Functions ---
        // 生成缓存键，确保用户ID和聊天类型隔离
        function getCacheKey(type, userId = null) {
            const currentUser = window.VENCHAT_CONFIG.userId;
            if (type === 'public') {
                return `venchat_${CACHE_VERSION}_user${currentUser}_public`;
            } else if (type === 'private' && userId) {
                // 私聊缓存键包含当前用户ID和对方用户ID，确保不会混淆
                return `venchat_${CACHE_VERSION}_user${currentUser}_private_${userId}`;
            }
            return null;
        }

        // 保存消息到缓存
        function saveMessagesToCache(messages, type, userId = null) {
            try {
                const key = getCacheKey(type, userId);
                if (!key) return false;

                const cacheData = {
                    messages: messages,
                    timestamp: Date.now(),
                    userId: userId, // 额外保存用户ID用于验证
                    type: type,
                    currentUser: window.VENCHAT_CONFIG.userId // 保存当前用户ID
                };

                localStorage.setItem(key, JSON.stringify(cacheData));
                return true;
            } catch (e) {
                console.error('保存缓存失败:', e);
                return false;
            }
        }

        // 从缓存加载消息
        function loadMessagesFromCache(type, userId = null) {
            try {
                const key = getCacheKey(type, userId);
                if (!key) return null;

                const cached = localStorage.getItem(key);
                if (!cached) return null;

                const cacheData = JSON.parse(cached);
                
                // 验证缓存数据的完整性和正确性
                if (!cacheData || !cacheData.messages || !Array.isArray(cacheData.messages)) {
                    return null;
                }

                // 严格验证：确保缓存的用户ID匹配
                if (cacheData.currentUser !== window.VENCHAT_CONFIG.userId) {
                    console.warn('缓存用户ID不匹配，清除缓存');
                    localStorage.removeItem(key);
                    return null;
                }

                if (type === 'private' && cacheData.userId !== userId) {
                    console.warn('私聊对象ID不匹配，清除缓存');
                    localStorage.removeItem(key);
                    return null;
                }

                // 检查缓存是否过期
                const ageInDays = (Date.now() - cacheData.timestamp) / (1000 * 60 * 60 * 24);
                if (ageInDays > CACHE_EXPIRY_DAYS) {
                    localStorage.removeItem(key);
                    return null;
                }

                return cacheData.messages;
            } catch (e) {
                console.error('加载缓存失败:', e);
                return null;
            }
        }

        // 清理旧版本缓存
        function cleanOldCaches() {
            try {
                const currentUser = window.VENCHAT_CONFIG.userId;
                const keysToRemove = [];
                
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key.startsWith('venchat_') && !key.startsWith(`venchat_${CACHE_VERSION}_user${currentUser}_`)) {
                        keysToRemove.push(key);
                    }
                }
                
                keysToRemove.forEach(key => localStorage.removeItem(key));
                if (keysToRemove.length > 0) {
                    console.log(`清理了 ${keysToRemove.length} 个旧缓存`);
                }
            } catch (e) {
                console.error('清理缓存失败:', e);
            }
        }

        // --- Online Status Functions ---
        // 计算在线状态：返回 'online', 'away', 或 'offline'
        function calculateOnlineStatus(lastSeenStr) {
            if (!lastSeenStr) return 'offline';

            const lastSeen = new Date(lastSeenStr);
            const now = new Date();
            const diffMs = now - lastSeen;
            const diffMins = diffMs / (1000 * 60);

            if (diffMins < 5) return 'online';      // 5分钟内为在线
            if (diffMins < 15) return 'away';       // 5-15分钟为离开
            return 'offline';                        // 15分钟以上为离线
        }

        // 更新用户在线状态指示器
        function updateUserOnlineStatus(userElement, lastSeenStr) {
            const statusSpan = userElement.querySelector('.online-status');
            if (!statusSpan) return;

            const status = calculateOnlineStatus(lastSeenStr);
            statusSpan.className = 'online-status ' + status;

            // 设置 title 属性显示详细信息
            if (lastSeenStr) {
                const lastSeen = new Date(lastSeenStr);
                const now = new Date();
                const diffMs = now - lastSeen;
                const diffMins = Math.floor(diffMs / (1000 * 60));
                const diffHours = Math.floor(diffMins / 60);
                const diffDays = Math.floor(diffHours / 24);

                let timeStr = '';
                if (diffMins < 1) {
                    timeStr = '刚刚在线';
                } else if (diffMins < 60) {
                    timeStr = `${diffMins}分钟前在线`;
                } else if (diffHours < 24) {
                    timeStr = `${diffHours}小时前在线`;
                } else {
                    timeStr = `${diffDays}天前在线`;
                }

                statusSpan.title = timeStr;
            } else {
                statusSpan.title = '从未在线';
            }
        }

        // 更新所有用户的在线状态
        function updateAllUserOnlineStatus() {
            const userItems = document.querySelectorAll('.user-item[data-user-id]');
            userItems.forEach(item => {
                const lastSeen = item.getAttribute('data-last-seen');
                updateUserOnlineStatus(item, lastSeen);
            });
        }

        // --- Settings State ---
        // 自动迁移旧格式设置为新的 hue/mode 格式
        let savedSettings = migrateOldSettings(window.VENCHAT_CONFIG.initialSettings);
        let currentSettings = JSON.parse(JSON.stringify(savedSettings));

        // --- DOM Elements ---
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const publicChatBtn = document.getElementById('publicChatBtn');
        const privateChatBtn = document.getElementById('privateChatBtn');
        const settingsBtn = document.getElementById('settingsBtn');
        const chatSelectorModal = document.getElementById('chatSelectorModal');
        const logoutBtn = document.getElementById('logoutBtn');
        const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');
        const profileForm = document.getElementById('profileForm');
        const avatarInput = document.getElementById('avatarInput');
        const profileAvatar = document.getElementById('profileAvatar');
        const replyPreview = document.getElementById('replyPreview');
        const replyPreviewContent = replyPreview.querySelector('.reply-preview-content');
        const replyCancelBtn = document.getElementById('replyCancelBtn');
        const inputContainer = document.querySelector('.input-container');
        const bottomNav = document.querySelector('.bottom-nav');
        const desktopUserList = document.getElementById('desktopUserList');
        const inputFocusOverlay = document.getElementById('inputFocusOverlay');
        const chatContainer = document.getElementById('chatContainer');
        const settingsContainer = document.getElementById('settingsContainer');
        const appRoot = document.getElementById('app');

        // File upload elements
        const dragDropZone = document.getElementById('dragDropZone');
        const fileUploadOverlay = document.getElementById('fileUploadOverlay');
        const fileUploadClose = document.getElementById('fileUploadClose');
        const fileInfoName = document.getElementById('fileInfoName');
        const fileInfoSize = document.getElementById('fileInfoSize');
        const fileInfoType = document.getElementById('fileInfoType');
        const fileMessageInput = document.getElementById('fileMessageInput');
        const fileCancelBtn = document.getElementById('fileCancelBtn');
        const fileConfirmBtn = document.getElementById('fileConfirmBtn');

        const isDesktop = window.matchMedia("(min-width: 768px)").matches;
        const isMobile = !isDesktop;
        const currentUserId = window.VENCHAT_CONFIG.userId;

        // File upload state
        let pendingFile = null;

        // Markdown editor state
        let markdownEditorOpen = false;
        const markdownEditorOverlay = document.getElementById('markdownEditorOverlay');
        const markdownInput = document.getElementById('markdownInput');
        const markdownPreview = document.getElementById('markdownPreview');
        const markdownEditorBtn = document.getElementById('markdownEditorBtn');
        const markdownEditorClose = document.getElementById('markdownEditorClose');
        const markdownCancelBtn = document.getElementById('markdownCancelBtn');
        const markdownConfirmBtn = document.getElementById('markdownConfirmBtn');

        // File attachment state for messages
        let messageAttachment = null;
        const messageFileInput = document.getElementById('messageFileInput');
        const messageFileBtn = document.getElementById('messageFileBtn');
        const messageAttachmentPreview = document.getElementById('messageAttachmentPreview');
        const attachmentIcon = document.getElementById('attachmentIcon');
        const attachmentName = document.getElementById('attachmentName');
        const attachmentSize = document.getElementById('attachmentSize');
        const removeAttachmentBtn = document.getElementById('removeAttachmentBtn');

	        let currentDisplayedMessages = []; // 存储当前显示的消息数据
	        const displayedMessageIds = new Set(); // O(1) 去重与快速判断

        // --- HSL 主题生成系统 ---
        // 旧 theme 索引到 hue/mode 的映射表（向后兼容）
        const THEME_MIGRATION_MAP = [
            { hue: 217, mode: 'light' },  // 0: 默认白天
            { hue: 217, mode: 'dark' },   // 1: 默认夜晚
            { hue: 199, mode: 'light' },  // 2: 蓝色白天
            { hue: 199, mode: 'dark' },   // 3: 蓝色夜晚
            { hue: 142, mode: 'light' },  // 4: 绿色白天
            { hue: 142, mode: 'dark' },   // 5: 绿色夜晚
            { hue: 263, mode: 'light' },  // 6: 紫色白天
            { hue: 263, mode: 'dark' },   // 7: 紫色夜晚
            { hue: 25, mode: 'light' },   // 8: 橙色白天
            { hue: 25, mode: 'dark' },    // 9: 橙色夜晚
        ];

        // 将旧的 theme 索引格式迁移为新的 hue/mode 格式
        function migrateOldSettings(settings) {
            if (settings.hue !== undefined && settings.mode !== undefined) {
                return settings; // 已经是新格式
            }
            const themeIndex = settings.theme ?? 0;
            const mapped = THEME_MIGRATION_MAP[themeIndex] || THEME_MIGRATION_MAP[0];
            return {
                hue: mapped.hue,
                mode: mapped.mode,
                radius: settings.radius ?? 20
            };
        }

        // 根据色相值和明暗模式生成完整的 17 个主题颜色
        function generateThemeFromHue(hue, mode) {
            const h = Math.round(hue) % 360;
            if (mode === 'dark') {
                return {
                    bg:             `hsl(${h}, 35%, 8%)`,
                    text:           `hsl(${h}, 18%, 90%)`,
                    chatBg:         `hsl(${h}, 28%, 14%)`,
                    msgBg:          `hsl(${h}, 22%, 20%)`,
                    ownMsgBg:       `hsl(${h}, 30%, 26%)`,
                    inputBg:        `hsl(${h}, 28%, 14%)`,
                    border:         `hsl(${h}, 22%, 22%)`,
                    accent:         `hsl(${h}, 60%, 65%)`,
                    secondary:      `hsl(${h}, 18%, 60%)`,
                    username:       `hsl(${h}, 15%, 82%)`,
                    replyBg:        `hsl(${h}, 22%, 20%)`,
                    replyBorder:    `hsl(${h}, 60%, 65%)`,
                    ownReplyBg:     `hsl(${h}, 30%, 26%)`,
                    ownReplyBorder: `hsl(${h}, 60%, 65%)`,
                    shadow:         `hsla(0, 0%, 0%, 0.4)`,
                    danger:         `hsl(0, 75%, 65%)`,
                    dangerHover:    `hsl(0, 72%, 58%)`
                };
            }
            // light mode
            return {
                bg:             `hsl(${h}, 30%, 97%)`,
                text:           `hsl(${h}, 30%, 15%)`,
                chatBg:         `hsl(${h}, 22%, 95%)`,
                msgBg:          `hsl(${h}, 15%, 100%)`,
                ownMsgBg:       `hsl(${h}, 45%, 91%)`,
                inputBg:        `hsl(${h}, 15%, 100%)`,
                border:         `hsl(${h}, 20%, 88%)`,
                accent:         `hsl(${h}, 72%, 52%)`,
                secondary:      `hsl(${h}, 15%, 55%)`,
                username:       `hsl(${h}, 25%, 25%)`,
                replyBg:        `hsl(${h}, 30%, 95%)`,
                replyBorder:    `hsl(${h}, 72%, 52%)`,
                ownReplyBg:     `hsl(${h}, 40%, 88%)`,
                ownReplyBorder: `hsl(${h}, 72%, 52%)`,
                shadow:         `hsla(${h}, 15%, 20%, 0.06)`,
                danger:         `hsl(0, 72%, 58%)`,
                dangerHover:    `hsl(0, 72%, 48%)`
            };
        }
