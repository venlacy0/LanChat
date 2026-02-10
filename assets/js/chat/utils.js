        // --- NEW Notification & Modal Functions ---
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

            setTimeout(() => {
                toast.classList.add('hide');
                toast.addEventListener('transitionend', () => toast.remove(), { once: true });
            }, 3000);
        }

        function showConfirm(text, yesText = '确认', noText = '取消') {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmModalText').textContent = text;
            const yesBtn = document.getElementById('confirmModalYes');
            const noBtn = document.getElementById('confirmModalNo');
            yesBtn.textContent = yesText;
            noBtn.textContent = noText;

            modal.classList.add('active');

            return new Promise((resolve) => {
                const yesHandler = () => {
                    closeModal(modal);
                    cleanup();
                    resolve(true);
                };
                const noHandler = () => {
                    closeModal(modal);
                    cleanup();
                    resolve(false);
                };

                const cleanup = () => {
                    yesBtn.removeEventListener('click', yesHandler);
                    noBtn.removeEventListener('click', noHandler);
                };

                yesBtn.addEventListener('click', yesHandler, { once: true });
                noBtn.addEventListener('click', noHandler, { once: true });
            });
        }

        function closeModal(modal) {
            modal.classList.add('closing');
            modal.addEventListener('animationend', () => {
                modal.classList.remove('active', 'closing');
            }, { once: true });
        }

        // --- End of New Functions ---

        // --- File Upload Functions ---
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function getFileIcon(fileType) {
            const iconMap = {
                'jpg': 'fa-file-image',
                'jpeg': 'fa-file-image',
                'png': 'fa-file-image',
                'gif': 'fa-file-image',
                'webp': 'fa-file-image',
                'bmp': 'fa-file-image',
                'pdf': 'fa-file-pdf',
                'doc': 'fa-file-word',
                'docx': 'fa-file-word',
                'txt': 'fa-file-alt',
                'md': 'fa-file-code',
                'zip': 'fa-file-archive',
                'rar': 'fa-file-archive'
            };
            return iconMap[fileType] || 'fa-file';
        }

	        function highlightCodeBlocks(root) {
	            if (typeof hljs === 'undefined' || !root) return;
	            const blocks = root.querySelectorAll('pre code, code.hljs');
	            blocks.forEach(block => {
	                // 先将代码内容转义为纯文本，避免潜在的 HTML 注入
	                const rawCode = block.textContent || '';
	                block.textContent = rawCode;
	                hljs.highlightElement(block);
	                block.style.color = '#0f172a';
	                block.style.backgroundColor = '#ffffff';
	            });
	        }

	        // 低优先级渲染任务放到空闲时间，减少主线程阻塞
	        function scheduleIdle(task, timeout = 200) {
	            if (typeof requestIdleCallback === 'function') {
	                requestIdleCallback(task, { timeout });
	            } else {
	                setTimeout(task, 0);
	            }
	        }

	        function contentMayContainMath(content) {
	            if (!content || typeof content !== 'string') return false;
	            return /(\$\$[\s\S]+?\$\$|\$[^$]+\$|\\\(|\\\[)/.test(content);
	        }

	        function elementHasCodeBlocks(el) {
	            return !!(el && el.querySelector && el.querySelector('pre code, code.hljs'));
	        }

