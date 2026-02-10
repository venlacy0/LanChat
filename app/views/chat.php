<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>VenlanChat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.min.js" async></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true,
                processEnvironments: true
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                ignoreHtmlClass: 'tex2jax_ignore'
            }
        };
    </script>
        <style>
<?php
$cssFiles = [
    __DIR__ . '/../../assets/css/chat/base.css',
    __DIR__ . '/../../assets/css/chat/responsive.css',
    __DIR__ . '/../../assets/css/chat/markdown.css',
    __DIR__ . '/../../assets/css/chat/settings.css',
    __DIR__ . '/../../assets/css/chat/files.css',
];

foreach ($cssFiles as $cssPath) {
    if (is_readable($cssPath)) {
        echo file_get_contents($cssPath);
    }
}
?>
        </style>

</head>
<body>
    <div class="app-shell">
        <div id="app">
            <div id="inputFocusOverlay" class="input-focus-overlay"></div>

            <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h2>VenlanChat</h2>
                <div class="profile-btn" id="profileBtnDesktop">
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>
            <div class="user-list-container" id="desktopUserList">
                <div class="user-item active public" data-chat-type="public" data-chat-name="公共聊天">
                    <div class="user-name">公共聊天</div>
                </div>
                <?php foreach (getUserList() as $user): ?>
                <div class="user-item" data-chat-type="private" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['username']); ?>" data-last-seen="<?php echo htmlspecialchars($user['last_seen'] ?? ''); ?>">
                    <img src="<?php echo $user['avatar']; ?>" class="avatar" alt="<?php echo htmlspecialchars($user['username']); ?>">
                    <div class="user-name">
                        <?php echo htmlspecialchars($user['username']); ?>
                        <span class="online-status" title="用户在线状态"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="chat-container" id="chatContainer">
            <!-- 切换用户时的加载遮罩 -->
            <div class="chat-loading-overlay" id="chatLoadingOverlay" style="display: none;">
                <div class="chat-loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>加载中...</p>
                </div>
            </div>

            <div class="messages-container" id="messagesContainer">
                <div class="loader" id="loader"><i class="fas fa-spinner fa-spin"></i> 加载中...</div>
            </div>

            <div class="input-container">
                <div class="reply-preview" id="replyPreview" style="display: none;">
                    <span class="reply-preview-content"></span>
                    <button class="reply-cancel-btn" id="replyCancelBtn"><i class="fas fa-times"></i></button>
                </div>
                <div class="input-row">
                    <div class="file-upload-input-btn">
                        <input type="file" id="messageFileInput" accept="image/*" hidden>
                        <button type="button" id="messageFileBtn" class="input-action-btn" title="上传附件">
                            <i class="fas fa-paperclip"></i>
                        </button>
                    </div>
                    <div class="message-input-wrapper">
                        <textarea class="message-input" id="messageInput" placeholder="输入消息..." rows="1"></textarea>
                        <button class="markdown-editor-btn-inline" id="markdownEditorBtn" title="Markdown编辑器">M</button>
                    </div>
                    <button class="send-btn" id="sendBtn"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div class="message-attachment-preview" id="messageAttachmentPreview" style="display: none;">
                    <div class="attachment-item">
                        <i class="fas fa-file" id="attachmentIcon"></i>
                        <div class="attachment-info">
                            <div class="attachment-name" id="attachmentName"></div>
                            <div class="attachment-size" id="attachmentSize"></div>
                        </div>
                        <button type="button" id="removeAttachmentBtn" class="remove-attachment-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-container" id="settingsContainer" style="display: none;">
            <div class="settings-section">
                <h2>用户设置</h2>
                
                <div class="settings-group">
                    <div class="profile-header">
                        <img id="profileAvatar" src="<?php echo $current_user['avatar']; ?>" class="profile-avatar" alt="Avatar">
                        <div class="profile-username" id="profileUsername"><?php echo htmlspecialchars($current_user['username']); ?></div>
                    </div>
                    
                    <form id="profileForm">
                         <h3>个人资料</h3>
                        <div class="form-group">
                            <label for="usernameInput">用户名</label>
                            <input type="text" id="usernameInput" name="username" value="<?php echo htmlspecialchars($current_user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>头像</label>
                            <div class="file-input-wrapper">
                                <button type="button" class="file-input-btn">选择图片</button>
                                <input type="file" id="avatarInput" name="avatar" accept="image/*">
                            </div>
                            <span class="file-name" id="fileName">未选择文件</span>
                        </div>
                        <div class="settings-actions">
                             <button type="submit" class="action-btn save-btn">保存个人信息</button>
                        </div>
                    </form>
                </div>

                <div class="settings-group">
                    <h3>外观设置</h3>
                    <div class="setting-item">
                        <label>页面圆角 (px)</label>
                        <div class="slider-container">
                            <input type="range" min="0" max="50" class="slider" id="radiusSlider">
                            <div class="slider-value" id="radiusValue">20 px</div>
                        </div>
                    </div>
                    <div class="setting-item color-picker-card">
                        <label>主题色</label>
                        <div class="hue-slider-wrapper">
                            <input type="range" min="0" max="360" step="1" class="hue-slider" id="hueSlider">
                        </div>
                        <div class="theme-preview-strip" id="themePreviewStrip">
                            <div class="preview-swatch" id="previewBg"></div>
                            <div class="preview-swatch" id="previewChatBg"></div>
                            <div class="preview-swatch" id="previewAccent"></div>
                            <div class="preview-swatch" id="previewOwnMsg"></div>
                            <div class="preview-swatch" id="previewText"></div>
                        </div>
                        <div class="mode-toggle">
                            <button type="button" class="mode-btn active" id="lightModeBtn">
                                <i class="fas fa-sun"></i> 明亮
                            </button>
                            <button type="button" class="mode-btn" id="darkModeBtn">
                                <i class="fas fa-moon"></i> 暗黑
                            </button>
                        </div>
                    </div>
                    <div class="settings-actions">
                        <button class="action-btn save-btn" id="saveSettingsBtn">保存设置</button>
                        <button class="action-btn cancel-btn" id="cancelSettingsBtn">撤销更改</button>
                    </div>
                </div>

                <div class="settings-group">
                     <h3>账户操作</h3>
                     <button class="action-btn" id="logoutBtn">退出登录</button>
                </div>
            </div>
        </div>

        <div class="bottom-nav">
            <div class="nav-item active" id="publicChatBtn" title="公共聊天">
                <div class="nav-icon"><i class="fas fa-comments"></i></div>
                <div>聊天</div>
            </div>
            <div class="nav-item" id="privateChatBtn" title="私聊消息">
                <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                <div>私聊</div>
            </div>
            <div class="nav-item" id="settingsBtn" title="设置">
                <div class="nav-icon"><i class="fas fa-cog"></i></div>
                <div>设置</div>
            </div>
        </div>
        </div>
        <button id="scrollToBottomBtn"><i class="fas fa-arrow-down"></i></button>
    </div>

    <div class="chat-selector-modal" id="chatSelectorModal">
        <div class="chat-selector-content">
            <div style="padding: 16px 12px; border-bottom: 1px solid var(--border-color); flex-shrink: 0;">
                <h2 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--text-color);">选择聊天对象</h2>
            </div>
            <div class="user-list-container">
                <?php foreach (getUserList() as $user): ?>
                <div class="user-item" data-user-id="<?php echo $user['id']; ?>">
                    <img src="<?php echo $user['avatar']; ?>" class="avatar" alt="<?php echo htmlspecialchars($user['username']); ?>">
                    <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="modal" id="confirmModal">
        <div class="modal-content" id="confirmModalContent">
            <p id="confirmModalText"></p>
            <div class="confirm-modal-actions">
                <button id="confirmModalYes">确认</button>
                <button id="confirmModalNo">取消</button>
            </div>
        </div>
    </div>

    <!-- 拖拽上传提示区域 -->
    <div class="drag-drop-zone" id="dragDropZone">
        <div class="drag-drop-text">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>拖放文件到这里上传</p>
        </div>
    </div>

    <!-- Markdown 编辑器模态框 -->
    <div class="markdown-editor-overlay" id="markdownEditorOverlay">
        <div class="markdown-editor-container">
            <div class="markdown-editor-header">
                <h3><i class="fas fa-pen-fancy"></i> Markdown 编辑器</h3>
                <button class="markdown-editor-close" id="markdownEditorClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="markdown-editor-content">
                <textarea class="markdown-input" id="markdownInput" placeholder="输入 Markdown 内容..."></textarea>
                <div class="markdown-preview" id="markdownPreview"></div>
            </div>
            <div class="markdown-editor-actions">
                <button class="markdown-editor-action-btn secondary" id="markdownCancelBtn">
                    <i class="fas fa-times"></i> 取消
                </button>
                <button class="markdown-editor-action-btn primary" id="markdownConfirmBtn">
                    <i class="fas fa-check"></i> 完成
                </button>
            </div>
        </div>
    </div>

    <!-- 文件上传模态框 -->
    <div class="file-upload-overlay" id="fileUploadOverlay">
        <div class="file-upload-modal">
            <div class="file-upload-header">
                <h3><i class="fas fa-file-upload"></i> 上传文件</h3>
                <button class="file-upload-close" id="fileUploadClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="file-info" id="fileInfo">
                <div class="file-info-item">
                    <i class="fas fa-file"></i>
                    <span class="file-info-label">文件名:</span>
                    <span class="file-info-value" id="fileInfoName">-</span>
                </div>
                <div class="file-info-item">
                    <i class="fas fa-hdd"></i>
                    <span class="file-info-label">文件大小:</span>
                    <span class="file-info-value" id="fileInfoSize">-</span>
                </div>
                <div class="file-info-item">
                    <i class="fas fa-tag"></i>
                    <span class="file-info-label">文件类型:</span>
                    <span class="file-info-value" id="fileInfoType">-</span>
                </div>
            </div>
            <textarea class="file-message-input" id="fileMessageInput" placeholder="添加消息说明（可选）..."></textarea>
            <div class="file-upload-actions">
                <button class="file-upload-btn secondary" id="fileCancelBtn">
                    <i class="fas fa-times"></i> 取消
                </button>
                <button class="file-upload-btn primary" id="fileConfirmBtn">
                    <i class="fas fa-paper-plane"></i> 发送
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/chat_js.php'; ?>
</body>
</html>
