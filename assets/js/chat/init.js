        document.addEventListener('DOMContentLoaded', function() {
	            applySettings(savedSettings);
	            loadInitialMessages();
	            updateAllUserOnlineStatus();  // 初始化时更新用户在线状态
	            startPolling();

	            document.addEventListener('visibilitychange', () => {
	                if (document.hidden) {
	                    if (pollingInterval) clearInterval(pollingInterval);
	                    pollingInterval = null;
	                } else {
	                    pollNewMessages();
	                    startPolling();
	                }
	            });

            if (isDesktop) {
                document.getElementById('profileBtnDesktop').addEventListener('click', () => switchTab('settings'));
                desktopUserList.addEventListener('click', handleDesktopChatSelection);
            } else {
                // 手机端初始化
                publicChatBtn.addEventListener('click', () => {
                    switchTab('public');
                });
                privateChatBtn.addEventListener('click', () => {
                    switchTab('private');
                });
                settingsBtn.addEventListener('click', () => {
                    switchTab('settings');
                });
                // 处理列表项和网格项选择
                function setupUserItemListeners() {
                    // 列表项（侧边栏）
                    document.querySelectorAll('.chat-selector-content .user-item').forEach(item => {
                        item.addEventListener('click', function() {
                            selectedReceiverId = this.getAttribute('data-user-id');
                            chatSelectorModal.classList.remove('active');
                            chatSelectorModal.classList.add('closing');
                            setTimeout(() => {
                                chatSelectorModal.classList.remove('closing');
                            }, 300);
                            cancelReply();
	                            loadPrivateMessages(autoScrollAfterSwitch);
                        });
                    });

                    // 网格项（如果有）
                    document.querySelectorAll('.user-item.grid-item').forEach(item => {
                        item.addEventListener('click', selectPrivateChatUserMobile);
                    });
                }
                setupUserItemListeners();

                chatSelectorModal.addEventListener('click', closeChatSelectorModal);

	                messageInput.addEventListener('focus', () => {
	                    inputContainer.classList.add('focused');
	                    inputFocusOverlay.classList.add('active');
	                    document.body.style.overflow = 'hidden';

	                    if (!outsideFocusClickHandler) {
	                        outsideFocusClickHandler = (e) => {
	                            if (!inputContainer.classList.contains('focused')) return;
	                            if (inputContainer.contains(e.target)) return;
	                            deactivateInputFocus();
	                            messageInput.blur();
	                        };
	                    }
	                    document.addEventListener('click', outsideFocusClickHandler);
	                });
            }
            updateScrollBtnPosition();

	            // Initialize Highlight.js for existing content（按容器范围高亮，避免在移动端全局扫描）
	            highlightCodeBlocks(messagesContainer);

            // --- Event Listeners ---
            messageInput.addEventListener('input', () => {
                resizeInput.call(messageInput);
                updateScrollBtnPosition();
            });
            // 视口尺寸变化时重新计算滚动按钮位置
            window.addEventListener('resize', updateScrollBtnPosition);
            sendBtn.addEventListener('click', sendMessage);
            messageInput.addEventListener('keypress', handleEnterKey);
            messageInput.addEventListener('paste', handleMessagePasteFile);
            logoutBtn.addEventListener('click', logout);
            messagesContainer.addEventListener('scroll', handleScroll);
            scrollToBottomBtn.addEventListener('click', () => scrollToBottom(true));
            profileForm.addEventListener('submit', handleProfileUpdate);
            avatarInput.addEventListener('change', previewAvatar);
            replyCancelBtn.addEventListener('click', cancelReply);

            // --- Settings Page Event Listeners ---
            const radiusSlider = document.getElementById('radiusSlider');
            const hueSlider = document.getElementById('hueSlider');
            const lightModeBtn = document.getElementById('lightModeBtn');
            const darkModeBtn = document.getElementById('darkModeBtn');
            const saveSettingsBtn = document.getElementById('saveSettingsBtn');
            const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');

            radiusSlider.addEventListener('input', () => {
                currentSettings.radius = parseInt(radiusSlider.value);
                applySettings(currentSettings);
                document.getElementById('radiusValue').textContent = currentSettings.radius + ' px';
                const percentage = (radiusSlider.value - radiusSlider.min) / (radiusSlider.max - radiusSlider.min) * 100;
                radiusSlider.style.backgroundSize = `${percentage}% 100%, 100% 100%`;
            });

            // 色相滑块：拖动时实时预览
            hueSlider.addEventListener('input', () => {
                currentSettings.hue = parseInt(hueSlider.value);
                applySettings(currentSettings);
                updateVisualSettingsControls();
            });

            // 明暗模式切换
            lightModeBtn.addEventListener('click', () => {
                currentSettings.mode = 'light';
                applySettings(currentSettings);
                updateVisualSettingsControls();
            });
            darkModeBtn.addEventListener('click', () => {
                currentSettings.mode = 'dark';
                applySettings(currentSettings);
                updateVisualSettingsControls();
            });

            saveSettingsBtn.addEventListener('click', handleSettingsUpdate);
            cancelSettingsBtn.addEventListener('click', revertSettings);
            updateVisualSettingsControls();

            // --- File Upload Event Listeners ---
            // Drag and drop events
            let dragCounter = 0;

            document.body.addEventListener('dragenter', (e) => {
                // 检查是否是真实文件拖拽（不是链接或网站图片）
                const hasFiles = e.dataTransfer && e.dataTransfer.items &&
                    Array.from(e.dataTransfer.items).some(item => item.kind === 'file');

                if (hasFiles && currentTab !== 'settings') {
                    dragCounter++;
                    dragDropZone.classList.add('active');
                }
            });

            document.body.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // 再次验证是否是真实文件
                const hasFiles = e.dataTransfer && e.dataTransfer.items &&
                    Array.from(e.dataTransfer.items).some(item => item.kind === 'file');

                if (hasFiles && currentTab !== 'settings') {
                    dragDropZone.classList.add('active');
                    e.dataTransfer.dropEffect = 'copy';
                } else {
                    e.dataTransfer.dropEffect = 'none';
                }
            });

            document.body.addEventListener('dragleave', (e) => {
                dragCounter--;
                if (dragCounter === 0) {
                    dragDropZone.classList.remove('active');
                }
            });

            document.body.addEventListener('drop', (e) => {
                dragCounter = 0;
                dragDropZone.classList.remove('active');

                // 只处理真实文件
                const hasFiles = e.dataTransfer && e.dataTransfer.items &&
                    Array.from(e.dataTransfer.items).some(item => item.kind === 'file');

                if (hasFiles) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleFileDrop(e);
                }
            });

            // 处理拖拽离开窗口的情况
            document.body.addEventListener('dragend', (e) => {
                dragCounter = 0;
                dragDropZone.classList.remove('active');
            });

            // 处理窗口失焦的情况
            window.addEventListener('blur', () => {
                dragCounter = 0;
                dragDropZone.classList.remove('active');
            });

            // File upload modal events
            fileUploadClose.addEventListener('click', closeFileUploadModal);
            fileCancelBtn.addEventListener('click', closeFileUploadModal);
            fileConfirmBtn.addEventListener('click', uploadFile);

            // Close modal on overlay click
            fileUploadOverlay.addEventListener('click', (e) => {
                if (e.target === fileUploadOverlay) {
                    closeFileUploadModal();
                }
            });

            // --- Markdown Editor Event Listeners ---
            markdownEditorBtn.addEventListener('click', openMarkdownEditor);
            markdownEditorClose.addEventListener('click', closeMarkdownEditor);
            markdownCancelBtn.addEventListener('click', closeMarkdownEditor);
            markdownConfirmBtn.addEventListener('click', confirmMarkdownEditor);
            markdownInput.addEventListener('input', updateMarkdownPreview);

            // Close editor on overlay click
            markdownEditorOverlay.addEventListener('click', (e) => {
                if (e.target === markdownEditorOverlay) {
                    closeMarkdownEditor();
                }
            });

            // --- Message File Attachment Event Listeners ---
            messageFileBtn.addEventListener('click', () => messageFileInput.click());
            messageFileInput.addEventListener('change', handleMessageFileSelect);
            removeAttachmentBtn.addEventListener('click', removeMessageAttachment);
        });
