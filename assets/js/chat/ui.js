        // ANIMATION FIX
	        function deactivateInputFocus() {
	            if (!isDesktop && inputContainer.classList.contains('focused')) {
	                if (outsideFocusClickHandler) {
	                    document.removeEventListener('click', outsideFocusClickHandler);
	                    outsideFocusClickHandler = null;
	                }
	                inputFocusOverlay.classList.remove('active');
	                inputContainer.classList.add('unfocusing');

                setTimeout(() => {
                    // 动画结束后移除 focused 和 unfocusing 类，恢复到固定位置
                    inputContainer.classList.remove('focused', 'unfocusing');
                    // 清除所有内联样式
                    inputContainer.style.position = '';
                    inputContainer.style.bottom = '';
                    inputContainer.style.left = '';
                    inputContainer.style.right = '';
                    inputContainer.style.width = '';
                    inputContainer.style.transform = '';
                    inputContainer.style.opacity = '';
                    inputContainer.style.transition = '';
                    document.body.style.overflow = '';
                }, 220);
            }
        }

        function resizeInput() {
            this.style.height = 'auto';
            // 在焦点状态下应用最大高度限制，防止输入框溢出
            const isFocused = inputContainer.classList.contains('focused');
            const maxHeight = isFocused ? (window.innerHeight * 0.5) : null; // 焦点时最大高度为视口的50%
            const newHeight = Math.min(this.scrollHeight, maxHeight || Infinity);
            this.style.height = newHeight + 'px';
        }

        function updateScrollBtnPosition() {
            let extraHeight = replyPreview.style.display !== 'none' ? replyPreview.offsetHeight : 0;
            if (isDesktop) {
                scrollToBottomBtn.style.bottom = `${inputContainer.offsetHeight + extraHeight + 20}px`;
            } else {
                const bottomOffset = inputContainer.offsetHeight + bottomNav.offsetHeight + extraHeight + 10;
                scrollToBottomBtn.style.bottom = `${bottomOffset}px`;
            }
        }

        function handleEnterKey(e) {
            // 仅在桌面端允许回车发送消息
            // 手机端回车表示换行，需要点击按钮发送
            if (e.key === 'Enter' && !e.shiftKey && isDesktop) {
                e.preventDefault();
                sendMessage();
            }
            // 手机端：回车换行（不阻止默认行为），Shift+Enter 也换行
        }


        function cancelReply() {
            replyToId = null;
            replyPreview.style.display = 'none';
            updateScrollBtnPosition();
        }
        
        function loadInitialMessages() {
            if (isPublicChat) {
                loadPublicMessages();
            } else if(selectedReceiverId) {
                loadPrivateMessages();
            }
        }

        // 主视图切换：把退场面板临时绝对定位为覆盖层，再做淡出，避免两个 flex:1 面板同时占位导致布局抖动
        function swapMainPanels(showEl, hideEl) {
            if (!showEl || !hideEl) return;

            const resetOverlayStyles = (el) => {
                el.classList.remove('fade-in', 'fade-out', 'closing');
                el.style.position = '';
                el.style.inset = '';
                el.style.width = '';
                el.style.height = '';
                el.style.zIndex = '';
            };

            resetOverlayStyles(showEl);
            resetOverlayStyles(hideEl);

            const showVisible = window.getComputedStyle(showEl).display !== 'none';
            const hideVisible = window.getComputedStyle(hideEl).display !== 'none';

            // 入场面板：如果原本不可见才播放入场动画，避免在同一面板内切换造成闪烁
            showEl.style.display = 'flex';
            if (!showVisible) {
                void showEl.offsetHeight;
                showEl.classList.add('fade-in');
            }

            // 退场面板本来就不可见，则直接收束
            if (!hideVisible) {
                hideEl.style.display = 'none';
                return;
            }

            // 退场面板：覆盖层淡出（不占据 flex 布局）
            hideEl.style.display = 'flex';
            hideEl.style.position = 'absolute';
            hideEl.style.inset = '0';
            hideEl.style.width = '100%';
            hideEl.style.height = '100%';
            hideEl.style.zIndex = '2';

            void hideEl.offsetHeight;
            hideEl.classList.add('fade-out');

            let cleaned = false;
            const cleanup = () => {
                if (cleaned) return;
                cleaned = true;
                hideEl.style.display = 'none';
                hideEl.classList.remove('fade-out');
                hideEl.style.position = '';
                hideEl.style.inset = '';
                hideEl.style.width = '';
                hideEl.style.height = '';
                hideEl.style.zIndex = '';
            };

            hideEl.addEventListener('animationend', (e) => {
                if (e.target !== hideEl) return;
                cleanup();
            }, { once: true });

            // 超时保险（避免某些环境不触发 animationend）
            setTimeout(cleanup, 420);
        }

        function switchTab(tab) {
            if (currentTab === 'settings' && tab !== 'settings') {
                revertSettings(); // Revert any unsaved changes when leaving settings tab
            }

            currentTab = tab;
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));

            if (tab === 'public') {
                if (appRoot) appRoot.classList.remove('settings-mode');
                publicChatBtn.classList.add('active');
                isPublicChat = true;
                selectedReceiverId = null;
                chatSelectorModal.classList.remove('active');
                cancelReply();

                swapMainPanels(chatContainer, settingsContainer);
                requestAnimationFrame(() => loadPublicMessages(autoScrollAfterSwitch));
            } else if (tab === 'private') {
                if (appRoot) appRoot.classList.remove('settings-mode');
                privateChatBtn.classList.add('active');
                isPublicChat = false;
                chatSelectorModal.classList.add('active');
                cancelReply();

                swapMainPanels(chatContainer, settingsContainer);
                requestAnimationFrame(() => {
                    if(!selectedReceiverId) {
                        messagesContainer.innerHTML = '<div class="loader"><i class="fas fa-spinner fa-spin"></i> 请选择聊天对象</div>';
                    } else {
	                        // 如果已经选择了私聊对象,重新加载消息
	                        loadPrivateMessages(autoScrollAfterSwitch);
                    }
                });
            } else if (tab === 'settings') {
                if (appRoot) appRoot.classList.add('settings-mode');
                if (settingsBtn) settingsBtn.classList.add('active');

                swapMainPanels(settingsContainer, chatContainer);
            }
        }

        // 显示加载遮罩
        function showChatLoadingOverlay() {
            const overlay = document.getElementById('chatLoadingOverlay');
            overlay.style.display = 'flex';
            requestAnimationFrame(() => {
                overlay.classList.add('active');
            });
        }

        // 隐藏加载遮罩
        function hideChatLoadingOverlay() {
            const overlay = document.getElementById('chatLoadingOverlay');
            overlay.classList.remove('active');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 300);
        }

        // 切换私聊用户时显示加载遮罩
        function switchPrivateChatUser(newReceiverId) {
            if (selectedReceiverId === newReceiverId) return;

            // 立即清空聊天界面所有消息
            messagesContainer.innerHTML = '';

	            const overlayStart = Date.now();
	            showChatLoadingOverlay();

            selectedReceiverId = newReceiverId;
            cancelReply();
            
		            loadPrivateMessages(() => {
		                autoScrollAfterSwitch();
		                const elapsed = Date.now() - overlayStart;
		                const remaining = Math.max(0, 250 - elapsed);
		                setTimeout(hideChatLoadingOverlay, remaining);
		            });
        }

        function selectPrivateChatUserMobile() {
            const newReceiverId = parseInt(this.getAttribute('data-user-id'));
            
            // 关闭用户选择器
            chatSelectorModal.classList.remove('active');
            chatSelectorModal.classList.add('closing');
            setTimeout(() => {
                chatSelectorModal.classList.remove('closing');
            }, 300);

            // 立即清空聊天界面所有消息
            messagesContainer.innerHTML = '';

	            const overlayStart = Date.now();
	            showChatLoadingOverlay();

            selectedReceiverId = newReceiverId;
            cancelReply();
            
		            loadPrivateMessages(() => {
		                autoScrollAfterSwitch();
		                const elapsed = Date.now() - overlayStart;
		                const remaining = Math.max(0, 250 - elapsed);
		                setTimeout(hideChatLoadingOverlay, remaining);
		            });
	        }

        function handleDesktopChatSelection(e) {
            const userItem = e.target.closest('.user-item');
            if (!userItem) return;

            if (currentTab === 'settings') {
                revertSettings();
            }

            document.querySelectorAll('#desktopUserList .user-item').forEach(item => item.classList.remove('active'));
            userItem.classList.add('active');

            currentTab = 'chat'; // Generic chat tab for desktop
            const chatType = userItem.getAttribute('data-chat-type');
            cancelReply();

            swapMainPanels(chatContainer, settingsContainer);

            if (chatType === 'public') {
                isPublicChat = true;
                const oldReceiverId = selectedReceiverId;
                selectedReceiverId = null;

	                if (oldReceiverId !== null) {
	                    const overlayStart = Date.now();
	                    showChatLoadingOverlay();
	                    requestAnimationFrame(() => {
		                        loadPublicMessages(() => {
		                            autoScrollAfterSwitch();
		                            const elapsed = Date.now() - overlayStart;
		                            const remaining = Math.max(0, 250 - elapsed);
		                            setTimeout(hideChatLoadingOverlay, remaining);
		                        });
	                    });
	                    return;
	                }

	                requestAnimationFrame(() => {
	                    loadPublicMessages(autoScrollAfterSwitch);
	                });
            } else if (chatType === 'private') {
                isPublicChat = false;
                const newReceiverId = parseInt(userItem.getAttribute('data-user-id'));

                if (selectedReceiverId !== null && selectedReceiverId !== newReceiverId) {
                    // 在不同私聊用户之间切换，显示加载遮罩
                    requestAnimationFrame(() => {
                        switchPrivateChatUser(newReceiverId);
                    });
                } else {
                    // 首次进入私聊或从公聊切到私聊
	                    selectedReceiverId = newReceiverId;
	                    requestAnimationFrame(() => {
	                        loadPrivateMessages(autoScrollAfterSwitch);
	                    });
	                }
            }
        }

        function closeChatSelectorModal(e) {
            if (e.target === chatSelectorModal) {
                chatSelectorModal.classList.remove('active');
                chatSelectorModal.classList.add('closing');
                setTimeout(() => {
                    chatSelectorModal.classList.remove('closing');
                }, 300);
            }
        }

        async function logout() {
            const confirmed = await showConfirm('确定要退出登录吗？', '退出', '取消');
            if (confirmed) {
                window.location.href = 'logout.php';
            }
        }

	        function handleScroll() {
	            // 向上滚动加载历史（每次 50 条）
	            if (messagesContainer.scrollTop <= 120) {
	                if (typeof window.maybeLoadMoreHistory === 'function') {
	                    window.maybeLoadMoreHistory();
	                }
	            }

	            const isAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 100;
	            scrollToBottomBtn.classList.toggle('visible', !isAtBottom);
	            if (!autoScrollProgrammatic && Date.now() <= autoScrollLockUntil && !isAtBottom) {
	                autoScrollUserInterrupted = true;
	                if (autoScrollResizeObserver) {
	                    autoScrollResizeObserver.disconnect();
	                    autoScrollResizeObserver = null;
	                }
	                if (autoScrollMutationObserver) {
	                    autoScrollMutationObserver.disconnect();
	                    autoScrollMutationObserver = null;
	                }
	            }
	        }

	        let autoScrollProgrammatic = false;
	        function scrollToBottom(smooth = false) {
	            autoScrollProgrammatic = true;
	            messagesContainer.scrollTo({ top: messagesContainer.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
	            requestAnimationFrame(() => { autoScrollProgrammatic = false; });
	        }

	        let autoScrollLockUntil = 0;
	        let autoScrollResizeObserver = null;
	        let autoScrollMutationObserver = null;
	        let autoScrollCleanupTimer = null;
	        let autoScrollUserInterrupted = false;
	        let autoScrollToken = 0;

	        function isNearBottom(threshold = 80) {
	            return (messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight) <= threshold;
	        }

	        function waitForRenderStable({ maxWaitMs = 4000, minWaitMs = 600, quietMs = 250 } = {}) {
	            return new Promise(resolve => {
	                const start = performance.now();
	                let lastHeight = messagesContainer.scrollHeight;
	                let lastChange = start;

	                function tick() {
	                    const now = performance.now();
	                    const h = messagesContainer.scrollHeight;
	                    if (h !== lastHeight) {
	                        lastHeight = h;
	                        lastChange = now;
	                    }

	                    const elapsed = now - start;
	                    const quietFor = now - lastChange;
	                    if (elapsed >= maxWaitMs || (elapsed >= minWaitMs && quietFor >= quietMs)) {
	                        resolve();
	                        return;
	                    }
	                    requestAnimationFrame(tick);
	                }

	                requestAnimationFrame(tick);
	            });
	        }

	        function startAutoScrollLock(lockMs) {
	            autoScrollLockUntil = Date.now() + lockMs;

	            if (typeof ResizeObserver === 'function') {
	                autoScrollResizeObserver = new ResizeObserver(() => {
	                    if (Date.now() > autoScrollLockUntil) return;
	                    if (autoScrollUserInterrupted) return;
	                    scrollToBottom(false);
	                });
	                autoScrollResizeObserver.observe(messagesContainer);
	            }

	            if (typeof MutationObserver === 'function') {
	                autoScrollMutationObserver = new MutationObserver(() => {
	                    if (Date.now() > autoScrollLockUntil) return;
	                    if (autoScrollUserInterrupted) return;
	                    scrollToBottom(false);
	                });
	                autoScrollMutationObserver.observe(messagesContainer, {
	                    childList: true,
	                    subtree: true,
	                    characterData: true,
	                    attributes: true
	                });
	            }

	            const imgs = messagesContainer.querySelectorAll('img');
	            imgs.forEach(img => {
	                if (img.complete) return;
	                const handler = () => {
	                    if (Date.now() <= autoScrollLockUntil && !autoScrollUserInterrupted) {
	                        scrollToBottom(false);
	                    }
	                    img.removeEventListener('load', handler);
	                    img.removeEventListener('error', handler);
	                };
	                img.addEventListener('load', handler);
	                img.addEventListener('error', handler);
	            });

	            autoScrollCleanupTimer = setTimeout(() => {
	                if (autoScrollResizeObserver) {
	                    autoScrollResizeObserver.disconnect();
	                    autoScrollResizeObserver = null;
	                }
	                if (autoScrollMutationObserver) {
	                    autoScrollMutationObserver.disconnect();
	                    autoScrollMutationObserver = null;
	                }
	                autoScrollCleanupTimer = null;
	                autoScrollLockUntil = 0;
	            }, lockMs + 200);
	        }

	        function autoScrollAfterSwitch() {
	            const token = ++autoScrollToken;
	            // 清理旧观察器 / 锁
	            autoScrollUserInterrupted = false;
	            autoScrollLockUntil = 0;
	            if (autoScrollResizeObserver) {
	                autoScrollResizeObserver.disconnect();
	                autoScrollResizeObserver = null;
	            }
	            if (autoScrollMutationObserver) {
	                autoScrollMutationObserver.disconnect();
	                autoScrollMutationObserver = null;
	            }
	            if (autoScrollCleanupTimer) {
	                clearTimeout(autoScrollCleanupTimer);
	                autoScrollCleanupTimer = null;
	            }

	            // 使用双重 requestAnimationFrame 确保 DOM 完全渲染
	            // 第一帧：浏览器处理 DOM 变更
	            // 第二帧：浏览器完成布局计算
	            requestAnimationFrame(() => {
	                if (token !== autoScrollToken) return;
	                requestAnimationFrame(() => {
	                    if (token !== autoScrollToken) return;

	                    // 首次滚动到底部
	                    scrollToBottom(false);

	                    // 短暂延迟后再次滚动，确保布局稳定
	                    setTimeout(() => {
	                        if (token !== autoScrollToken) return;
	                        if (autoScrollUserInterrupted) return;
	                        scrollToBottom(false);

	                        // 再次延迟滚动，处理复杂布局
	                        setTimeout(() => {
	                            if (token !== autoScrollToken) return;
	                            if (autoScrollUserInterrupted) return;
	                            scrollToBottom(false);
	                        }, 150);
	                    }, 50);

	                    // 设置锁定期（800ms）处理图片和异步内容加载
	                    startAutoScrollLock(800);
	                });
	            });
		        }

	        function handleProfileUpdate(e) {
            e.preventDefault();
            const formData = new FormData(profileForm);
            formData.append('action', 'update_profile');
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('个人信息更新成功');
                    profileAvatar.src = data.new_avatar;
                    document.getElementById('profileUsername').textContent = data.new_username;
                } else {
                    showToast('更新失败: ' + data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function previewAvatar(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    profileAvatar.src = event.target.result;
                    document.getElementById('fileName').textContent = file.name;
                };
                reader.readAsDataURL(file);
            }
        }

