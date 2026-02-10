        // --- Settings Functions ---

        function handleSettingsUpdate() {
            const formData = new FormData();
            formData.append('action', 'save_settings');
            formData.append('hue', currentSettings.hue);
            formData.append('mode', currentSettings.mode);
            formData.append('radius', currentSettings.radius);
            formData.append('csrf_token', window.VENCHAT_CONFIG.csrfToken);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('设置保存成功');
                    savedSettings = JSON.parse(JSON.stringify(currentSettings));
                } else {
                    showToast('保存失败', 'error');
                    revertSettings(); // Revert on failure
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function revertSettings() {
            currentSettings = JSON.parse(JSON.stringify(savedSettings));
            applySettings(currentSettings);
            updateVisualSettingsControls();
        }

        function updateVisualSettingsControls() {
            // 更新圆角滑块
            const radiusSlider = document.getElementById('radiusSlider');
            radiusSlider.value = currentSettings.radius;
            document.getElementById('radiusValue').textContent = currentSettings.radius + ' px';
            const percentage = (currentSettings.radius - radiusSlider.min) / (radiusSlider.max - radiusSlider.min) * 100;
            radiusSlider.style.backgroundSize = `${percentage}% 100%, 100% 100%`;

            // 更新色相滑块
            const hueSlider = document.getElementById('hueSlider');
            if (hueSlider) {
                hueSlider.value = currentSettings.hue;
            }

            // 更新主题预览色条
            const theme = generateThemeFromHue(currentSettings.hue, currentSettings.mode);
            const previewBg = document.getElementById('previewBg');
            const previewChatBg = document.getElementById('previewChatBg');
            const previewAccent = document.getElementById('previewAccent');
            const previewOwnMsg = document.getElementById('previewOwnMsg');
            const previewText = document.getElementById('previewText');
            if (previewBg) previewBg.style.background = theme.bg;
            if (previewChatBg) previewChatBg.style.background = theme.chatBg;
            if (previewAccent) previewAccent.style.background = theme.accent;
            if (previewOwnMsg) previewOwnMsg.style.background = theme.ownMsgBg;
            if (previewText) previewText.style.background = theme.text;

            // 更新明暗模式按钮
            const lightModeBtn = document.getElementById('lightModeBtn');
            const darkModeBtn = document.getElementById('darkModeBtn');
            if (lightModeBtn && darkModeBtn) {
                lightModeBtn.classList.toggle('active', currentSettings.mode === 'light');
                darkModeBtn.classList.toggle('active', currentSettings.mode === 'dark');
            }
        }

        function applySettings(settings) {
            const theme = generateThemeFromHue(settings.hue, settings.mode);
            const root = document.documentElement;
            const propertyMap = {
                '--bg-color': theme.bg,
                '--text-color': theme.text,
                '--chat-bg': theme.chatBg,
                '--msg-bg': theme.msgBg,
                '--own-msg-bg': theme.ownMsgBg,
                '--input-bg': theme.inputBg,
                '--border-color': theme.border,
                '--accent-color': theme.accent,
                '--secondary-text': theme.secondary,
                '--username-color': theme.username,
                '--reply-bg': theme.replyBg,
                '--reply-border': theme.replyBorder,
                '--own-reply-bg': theme.ownReplyBg,
                '--own-reply-border': theme.ownReplyBorder,
                '--shadow-color': theme.shadow,
                '--danger-color': theme.danger,
                '--danger-color-hover': theme.dangerHover,
                '--border-radius-msg': `${settings.radius}px`,
            };
            for (const prop in propertyMap) {
                root.style.setProperty(prop, propertyMap[prop]);
            }

            // 设置 data-mode 属性，用于代码块明暗适配
            root.setAttribute('data-mode', settings.mode);

            // 根据模式切换代码块颜色
            const codeBlocks = document.querySelectorAll('pre code.hljs, code.hljs');
            if (settings.mode === 'dark') {
                codeBlocks.forEach(block => {
                    block.style.color = '#e2e8f0';
                    block.style.backgroundColor = '';
                });
            } else {
                codeBlocks.forEach(block => {
                    block.style.color = '#0f172a';
                    block.style.backgroundColor = '';
                });
            }

            // 更新圆角滑块填充
            const radiusSlider = document.getElementById('radiusSlider');
            if (radiusSlider) {
                const pct = (settings.radius - radiusSlider.min) / (radiusSlider.max - radiusSlider.min) * 100;
                radiusSlider.style.backgroundSize = `${pct}% 100%, 100% 100%`;
            }
        }
