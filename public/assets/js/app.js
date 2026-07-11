document.addEventListener('DOMContentLoaded', () => {
    const isAnonymousEdit = typeof IS_ANONYMOUS_EDIT !== 'undefined' && IS_ANONYMOUS_EDIT === true;
    const anonymousEditToken = typeof ANONYMOUS_EDIT_TOKEN !== 'undefined' ? ANONYMOUS_EDIT_TOKEN : '';
    const anonymousEditPath = typeof ANONYMOUS_EDIT_PATH !== 'undefined' ? ANONYMOUS_EDIT_PATH : '';
    const anonymousEditInactivityLifetime = typeof ANONYMOUS_EDIT_INACTIVITY_LIFETIME !== 'undefined' ? ANONYMOUS_EDIT_INACTIVITY_LIFETIME : 300;

    // Session Management Interceptor
    let isSessionAlertShowing = false;
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        const response = await originalFetch(...args);
        if (response.status === 401 && !isSessionAlertShowing) {
            isSessionAlertShowing = true;
            if (isAnonymousEdit) {
                alert('この限定編集リンクは利用できません。');
            } else {
                alert('セッションの有効期限が切れました。ログイン画面に戻ります。');
                window.location.href = 'editor.php';
            }
        }
        return response;
    };

    const editor = document.getElementById('markdown-editor');
    const previewFrame = document.getElementById('preview-frame');
    const fileTree = document.getElementById('file-tree');
    const filePathInput = document.getElementById('file-path');
    const saveBtn = document.getElementById('save-file');
    const newFileBtn = document.getElementById('new-file');
    const newFolderBtn = document.getElementById('new-folder');
    const toggleHiddenBtn = document.getElementById('toggle-hidden');
    const copyLinkBtn = document.getElementById('copy-link');
    const logoutBtn = document.getElementById('logout');
    const cheatsheetBtn = document.getElementById('show-cheatsheet');
    const cheatsheetModal = document.getElementById('cheatsheet-modal');
    const closeBtn = cheatsheetModal.querySelector('.close-button');

    const unsavedModal = document.getElementById('unsaved-modal');
    const unsavedSaveBtn = document.getElementById('unsaved-save');
    const unsavedDiscardBtn = document.getElementById('unsaved-discard');
    const unsavedCancelBtn = document.getElementById('unsaved-cancel');
    const unsavedCloseBtn = unsavedModal.querySelector('.close-button');

    const imageModal = document.getElementById('image-modal');
    const imageModalCloseBtn = imageModal.querySelector('.close-button');
    const modalImage = document.getElementById('modal-image');
    const modalImageName = document.getElementById('modal-image-name');
    const anonymousLockNotice = document.getElementById('anonymous-lock-notice');
    const anonymousEditStatus = document.getElementById('anonymous-edit-status');
    const anonymousInactivityWarning = document.getElementById('anonymous-inactivity-warning');

    const sidebar = document.getElementById('sidebar');
    const resizer = document.getElementById('sidebar-resizer');

    let isResizing = false;

    if (resizer && sidebar) {
        resizer.addEventListener('mousedown', (e) => {
            isResizing = true;
            document.body.classList.add('resizing');
            resizer.classList.add('resizing');
        });
    }

    document.addEventListener('mousemove', (e) => {
        if (!isResizing || !sidebar) return;
        const newWidth = e.clientX;
        if (newWidth > 150 && newWidth < 600) {
            sidebar.style.width = `${newWidth}px`;
            document.documentElement.style.setProperty('--sidebar-width', `${newWidth}px`);
        }
    });

    document.addEventListener('mouseup', () => {
        isResizing = false;
        document.body.classList.remove('resizing');
        if (resizer) resizer.classList.remove('resizing');
    });

    let currentPath = '';
    let currentHash = '';
    let isPreviewReady = false;
    let lastSavedContent = '';
    const expandedFolders = new Set();
    let isFirstTreeLoad = true;
    let isScrollingFromPreview = false;
    let scrollSyncTimeout;
    let showHiddenFiles = localStorage.getItem('mdiki_show_hidden') === 'true';
    let resolveUnsavedAction = null;
    let isAnonymousEditLocked = false;
    let lastAnonymousActivityAt = Date.now();
    let anonymousLockCheckInFlight = false;
    const anonymousInactivityLimit = anonymousEditInactivityLifetime * 1000;
    const anonymousInactivityWarningThreshold = 60 * 1000;

    function showAnonymousLockNotice(message = '') {
        if (!isAnonymousEdit) return;
        isAnonymousEditLocked = true;
        editor.readOnly = true;
        editor.classList.add('readonly');
        saveBtn.disabled = true;
        saveBtn.title = 'Locked';
        hideAnonymousInactivityWarning();
        if (anonymousLockNotice) {
            const text = anonymousLockNotice.querySelector('span:not(.material-icons)');
            if (text && message) {
                text.textContent = message;
            }
            anonymousLockNotice.hidden = false;
        }
    }

    function enableAnonymousEditing() {
        if (!isAnonymousEdit) return;
        isAnonymousEditLocked = false;
        editor.readOnly = false;
        editor.classList.remove('readonly');
        saveBtn.disabled = false;
        saveBtn.title = 'Save';
        if (anonymousLockNotice) {
            anonymousLockNotice.hidden = true;
        }
        updateAnonymousInactivityWarning();
    }

    function setAnonymousViewerCount(count) {
        if (!anonymousEditStatus) return;
        const text = anonymousEditStatus.querySelector('span:not(.material-icons)');
        if (text) {
            text.textContent = `この編集リンクを開いている人: ${count}人`;
        }
        anonymousEditStatus.hidden = false;
    }

    function hideAnonymousInactivityWarning() {
        if (anonymousInactivityWarning) {
            anonymousInactivityWarning.hidden = true;
        }
    }

    function updateAnonymousInactivityWarning() {
        if (!isAnonymousEdit || isAnonymousEditLocked) {
            hideAnonymousInactivityWarning();
            return;
        }

        const remainingMs = anonymousInactivityLimit - (Date.now() - lastAnonymousActivityAt);
        if (remainingMs <= 0) {
            hideAnonymousInactivityWarning();
            checkAnonymousEditLock();
            return;
        }

        if (remainingMs > anonymousInactivityWarningThreshold) {
            hideAnonymousInactivityWarning();
            return;
        }

        if (anonymousInactivityWarning) {
            const remainingSeconds = Math.ceil(remainingMs / 1000);
            const text = anonymousInactivityWarning.querySelector('span:not(.material-icons)');
            if (text) {
                text.textContent = `このまま操作がなければ${remainingSeconds}秒後に編集権を手放します。現状の内容を保存する場合は、作業内容を保存してください。`;
            }
            anonymousInactivityWarning.hidden = false;
        }
    }

    function isAnonymousActive() {
        return Date.now() - lastAnonymousActivityAt < anonymousInactivityLimit;
    }

    function markAnonymousActivity() {
        if (!isAnonymousEdit) return;
        lastAnonymousActivityAt = Date.now();
        hideAnonymousInactivityWarning();
        if (isAnonymousEditLocked) {
            checkAnonymousEditLock();
        }
    }

    if (isAnonymousEdit) {
        showAnonymousLockNotice('編集状態を確認しています。ファイル内容は閲覧できます。');
    }

    function isDirty() {
        return editor.value !== lastSavedContent;
    }

    function closeUnsavedModal(action = 'cancel') {
        if (unsavedModal.style.display === 'block') {
            unsavedModal.style.display = 'none';
        }
        if (resolveUnsavedAction) {
            resolveUnsavedAction(action);
            resolveUnsavedAction = null;
        }
    }

    function showUnsavedModal() {
        return new Promise((resolve) => {
            resolveUnsavedAction = resolve;
            unsavedModal.style.display = 'block';
        });
    }

    function utf8_to_b64(str) {
        return btoa(new TextEncoder().encode(str).reduce((data, byte) => data + String.fromCharCode(byte), ''));
    }

    async function confirmAndSave() {
        if (!isDirty()) return true;
        const action = await showUnsavedModal();
        if (action === 'save') {
            return await saveFile();
        }
        if (action === 'discard') {
            return true;
        }
        return false;
    }

    unsavedSaveBtn.onclick = () => closeUnsavedModal('save');
    unsavedDiscardBtn.onclick = () => closeUnsavedModal('discard');
    unsavedCancelBtn.onclick = () => closeUnsavedModal('cancel');
    unsavedCloseBtn.onclick = () => closeUnsavedModal('cancel');

    async function loadFileList() {
        if (!fileTree) return;
        const res = await fetch('api/files.php?action=list');
        const files = await res.json();
        renderFileTree(files, fileTree, true);
        isFirstTreeLoad = false;
    }

    function renderFileTree(files, container, isRoot = false) {
        container.innerHTML = '';

        if (isRoot) {
            const rootDiv = document.createElement('div');
            rootDiv.className = 'file-item root-item';
            rootDiv.innerHTML = '<span class="material-icons toggle-icon root-toggle" title="Toggle All Folders">expand_more</span><span class="material-icons file-icon">home</span><span class="file-name">(Root)</span>';

            const rootToggle = rootDiv.querySelector('.root-toggle');
            rootToggle.onclick = (e) => {
                e.stopPropagation();
                const allContainers = fileTree.querySelectorAll('.file-item-container');
                // Check if any folder is currently expanded
                const isAnyExpanded = Array.from(allContainers).some(c => !c.classList.contains('collapsed'));

                allContainers.forEach(container => {
                    const folderDiv = container.querySelector('.dir-item');
                    const path = folderDiv ? folderDiv.dataset.path : null;

                    if (isAnyExpanded) {
                        container.classList.add('collapsed');
                        if (path) expandedFolders.delete(path);
                    } else {
                        container.classList.remove('collapsed');
                        if (path) expandedFolders.add(path);
                    }
                });
                rootToggle.style.transform = isAnyExpanded ? 'rotate(-90deg)' : 'rotate(0deg)';
            };

            rootDiv.ondragover = (e) => {
                e.preventDefault();
                rootDiv.classList.add('drag-over');
            };
            rootDiv.ondragleave = () => rootDiv.classList.remove('drag-over');
            rootDiv.ondrop = async (e) => {
                e.preventDefault();
                rootDiv.classList.remove('drag-over');
                const sourcePath = e.dataTransfer.getData('text/plain');
                const fileName = sourcePath.split('/').pop();
                if (sourcePath !== fileName) { // すでにルートにある場合は何もしない
                    await moveFile(sourcePath, fileName);
                }
            };
            container.appendChild(rootDiv);
        }

        files.forEach(file => {
            if (!showHiddenFiles && file.name.startsWith('.') && file.name !== '.data') {
                return;
            }
            // Explicitly hide .data if showHiddenFiles is false
            if (!showHiddenFiles && file.name === '.data') {
                return;
            }

            const item = document.createElement('div');
            item.className = 'file-item-container';
            if (file.is_dir) {
                if (isFirstTreeLoad && isRoot) {
                    expandedFolders.add(file.path);
                }

                if (expandedFolders.has(file.path)) {
                    item.classList.remove('collapsed');
                } else {
                    item.classList.add('collapsed');
                }
            }

            const div = document.createElement('div');
            div.className = `file-item ${file.is_dir ? 'dir-item' : ''}`;
            if (file.path === currentPath) div.classList.add('active');
            div.draggable = true;
            div.dataset.path = file.path;
            div.dataset.isDir = file.is_dir;

            const isImage = /\.(jpe?g|png|gif|webp)$/i.test(file.name);
            const icon = file.is_dir ? 'folder' : (isImage ? 'image' : 'article');

            if (file.is_dir) {
                const toggleIcon = document.createElement('span');
                toggleIcon.className = 'toggle-icon material-icons';
                toggleIcon.textContent = 'expand_more';
                toggleIcon.onclick = (e) => {
                    e.stopPropagation();
                    const isCollapsed = item.classList.toggle('collapsed');
                    if (isCollapsed) {
                        expandedFolders.delete(file.path);
                    } else {
                        expandedFolders.add(file.path);
                    }
                };
                div.appendChild(toggleIcon);
            }

            const iconSpan = document.createElement('span');
            iconSpan.textContent = icon;
            iconSpan.className = 'file-icon material-icons';
            if (isImage) {
                iconSpan.style.cursor = 'zoom-in';
                iconSpan.title = 'Click to preview image';
                iconSpan.onclick = (e) => {
                    e.stopPropagation();
                    modalImage.src = 'mds/' + file.path;
                    modalImageName.textContent = file.name;
                    imageModal.style.display = 'block';
                };
            } else if (file.is_dir) {
                iconSpan.onclick = (e) => {
                    e.stopPropagation();
                    const isCollapsed = item.classList.toggle('collapsed');
                    if (isCollapsed) {
                        expandedFolders.delete(file.path);
                    } else {
                        expandedFolders.add(file.path);
                    }
                };
            }
            div.appendChild(iconSpan);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'file-name';
            nameSpan.textContent = file.name;
            nameSpan.title = file.name;
            nameSpan.onclick = async () => {
                if (!file.is_dir) {
                    if (isImage) {
                        // Show image in modal
                        modalImage.src = 'mds/' + file.path;
                        modalImageName.textContent = file.name;
                        imageModal.style.display = 'block';
                    } else {
                        if (await confirmAndSave()) {
                            loadFile(file.path);
                        }
                    }
                } else {
                    const isCollapsed = item.classList.toggle('collapsed');
                    if (isCollapsed) {
                        expandedFolders.delete(file.path);
                    } else {
                        expandedFolders.add(file.path);
                    }
                }
            };
            div.appendChild(nameSpan);

            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'file-actions';

            if (file.is_dir) {
                const addFileBtn = document.createElement('button');
                addFileBtn.textContent = 'note_add';
                addFileBtn.className = 'add-file-btn material-icons';
                addFileBtn.title = 'New File in this folder';
                addFileBtn.onclick = (e) => {
                    e.stopPropagation();
                    createNewFile(file.path);
                };
                actionsDiv.appendChild(addFileBtn);

                const addFolderBtn = document.createElement('button');
                addFolderBtn.textContent = 'create_new_folder';
                addFolderBtn.className = 'add-folder-btn material-icons';
                addFolderBtn.title = 'New Folder in this folder';
                addFolderBtn.onclick = (e) => {
                    e.stopPropagation();
                    createNewFolder(file.path);
                };
                actionsDiv.appendChild(addFolderBtn);
            }

            const renameBtn = document.createElement('button');
            renameBtn.textContent = 'edit';
            renameBtn.className = 'rename-btn material-icons';
            renameBtn.title = 'Rename';
            renameBtn.onclick = (e) => {
                e.stopPropagation();
                renameItem(file.path, file.name, file.is_dir);
            };
            actionsDiv.appendChild(renameBtn);

            if (!file.is_dir) {
                if (isImage) {
                    const insertBtn = document.createElement('button');
                    insertBtn.textContent = 'add_photo_alternate';
                    insertBtn.className = 'insert-btn material-icons';
                    insertBtn.title = 'Insert Image Markdown';
                    insertBtn.onclick = (e) => {
                        e.stopPropagation();
                        const imageMarkdown = `![${file.name}](mds/${file.path})`;
                        const start = editor.selectionStart;
                        const end = editor.selectionEnd;
                        editor.value = editor.value.substring(0, start) + imageMarkdown + editor.value.substring(end);
                        editor.selectionStart = editor.selectionEnd = start + imageMarkdown.length;
                        updatePreview();
                    };
                    actionsDiv.appendChild(insertBtn);
                } else {
                    const copyItemBtn = document.createElement('button');
                    copyItemBtn.textContent = 'link';
                    copyItemBtn.className = 'copy-item-btn material-icons';
                    copyItemBtn.title = 'Copy Public Link';
                    copyItemBtn.onclick = (e) => {
                        e.stopPropagation();
                        copyPublicLink(file.path, copyItemBtn);
                    };
                    actionsDiv.appendChild(copyItemBtn);

                    const copyEditItemBtn = document.createElement('button');
                    copyEditItemBtn.textContent = 'vpn_key';
                    copyEditItemBtn.className = 'copy-item-btn material-icons';
                    copyEditItemBtn.title = 'Copy Edit Link';
                    copyEditItemBtn.onclick = (e) => {
                        e.stopPropagation();
                        copyEditLink(file.path, copyEditItemBtn);
                    };
                    actionsDiv.appendChild(copyEditItemBtn);
                }
            }

            const delBtn = document.createElement('button');
            delBtn.textContent = 'delete';
            delBtn.className = 'del-btn material-icons';
            delBtn.title = 'Delete';
            delBtn.onclick = (e) => {
                e.stopPropagation();
                deleteFile(file.path);
            };
            actionsDiv.appendChild(delBtn);

            div.appendChild(actionsDiv);
            item.appendChild(div);

            if (file.is_dir && file.children) {
                const childrenContainer = document.createElement('div');
                childrenContainer.className = 'dir-children';
                renderFileTree(file.children, childrenContainer);
                item.appendChild(childrenContainer);
            }

            // Drag and Drop
            div.ondragstart = (e) => {
                e.dataTransfer.setData('text/plain', file.path);
                e.dataTransfer.effectAllowed = 'move';
            };

            div.ondragover = (e) => {
                e.preventDefault();
                if (file.is_dir) {
                    div.classList.add('drag-over');
                }
            };

            div.ondragleave = () => {
                div.classList.remove('drag-over');
            };

            div.ondrop = async (e) => {
                e.preventDefault();
                div.classList.remove('drag-over');
                const sourcePath = e.dataTransfer.getData('text/plain');
                if (sourcePath === file.path) return;

                if (file.is_dir) {
                    const fileName = sourcePath.split('/').pop();
                    const targetPath = file.path + '/' + fileName;
                    await moveFile(sourcePath, targetPath);
                }
            };

            container.appendChild(item);
        });
    }

    async function moveFile(oldPath, newPath) {
        const res = await fetch('api/files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'move',
                oldPath: oldPath,
                newPath: newPath,
                csrf_token: CSRF_TOKEN
            })
        });
        if (res.ok) {
            if (currentPath === oldPath) {
                currentPath = newPath;
                filePathInput.value = newPath;
            }
            loadFileList();
        } else {
            let errorMsg = 'Unknown error';
            const contentType = res.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                const data = await res.json();
                errorMsg = data.error || errorMsg;
            } else {
                const text = await res.text();
                errorMsg = `Server returned ${res.status}. ${text.substring(0, 100)}...`;
            }
            alert('Move failed: ' + errorMsg);
        }
    }

    async function renameItem(oldPath, oldName, isDir) {
        let newName = prompt('Enter new name:', oldName);
        if (!newName || newName === oldName) return;

        if (!isDir) {
            const dotIndex = oldName.lastIndexOf('.');
            if (dotIndex !== -1) {
                const ext = oldName.substring(dotIndex);
                if (!newName.toLowerCase().endsWith(ext.toLowerCase())) {
                    newName += ext;
                }
            }
        }

        const pathParts = oldPath.split('/');
        pathParts[pathParts.length - 1] = newName;
        const newPath = pathParts.join('/');

        await moveFile(oldPath, newPath);
    }

    async function loadFile(path) {
        currentPath = path;
        // Auto-expand parent folders
        const parts = path.split('/');
        let currentLevel = '';
        for (let i = 0; i < parts.length - 1; i++) {
            currentLevel += (currentLevel ? '/' : '') + parts[i];
            expandedFolders.add(currentLevel);
        }

        const url = isAnonymousEdit
            ? `api/files.php?action=get&edit_token=${encodeURIComponent(anonymousEditToken)}`
            : `api/files.php?action=get&path=${encodeURIComponent(path)}`;
        const res = await fetch(url);
        if (!res.ok) return; // ファイルが存在しない場合は何もしない
        const data = await res.json();
        if (data.content !== undefined) {
            currentHash = data.hash;
            filePathInput.value = path;
            editor.value = data.content;
            lastSavedContent = data.content;
            updatePreview();

            document.querySelectorAll('.file-item').forEach(el => {
                el.classList.toggle('active', el.dataset.path === path);
            });
        }
    }

    async function deleteFile(path) {
        if (!confirm(`Are you sure you want to delete ${path}?`)) return;
        const res = await fetch('api/files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'delete',
                path: path,
                csrf_token: CSRF_TOKEN
            })
        });
        if (res.ok) {
            if (currentPath === path) {
                currentPath = '';
                editor.value = '';
                lastSavedContent = '';
                filePathInput.value = '';
                updatePreview();
            }
            loadFileList();
        }
    }

    function updatePreview() {
        const markdown = editor.value;
        if (previewFrame && previewFrame.contentWindow) {
            previewFrame.contentWindow.postMessage({
                type: 'update',
                content: markdown
            }, '*');
        }
    }

    editor.addEventListener('input', updatePreview);

    // Drag and Drop for Editor
    editor.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        editor.classList.add('drag-over');
    });

    editor.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        editor.classList.remove('drag-over');
    });

    editor.addEventListener('drop', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        editor.classList.remove('drag-over');
        if (isAnonymousEditLocked) {
            showAnonymousLockNotice();
            return;
        }

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            for (const file of files) {
                if (file.type.startsWith('image/')) {
                    await handleImageUpload(file, true);
                }
            }
        }
    });

    // Scroll Synchronization: Editor -> Preview
    editor.addEventListener('scroll', () => {
        if (isScrollingFromPreview) return;
        const maxScroll = editor.scrollHeight - editor.clientHeight;
        const percent = maxScroll > 0 ? editor.scrollTop / maxScroll : 0;
        if (previewFrame && previewFrame.contentWindow) {
            previewFrame.contentWindow.postMessage({
                type: 'scroll',
                percent: percent
            }, '*');
        }
    });

    async function saveFile() {
        if (isAnonymousEditLocked) {
            showAnonymousLockNotice();
            return false;
        }

        let path = currentPath;
        if (!path) {
            const name = prompt('Enter file name (e.g. folder/note.md):');
            if (!name) return false;
            path = name.endsWith('.md') ? name : name + '.md';
        }

        const data = {
            action: 'save',
            path: path,
            content: 'base64:' + utf8_to_b64(editor.value),
            is_base64: true,
            old_hash: currentHash,
            csrf_token: CSRF_TOKEN
        };
        if (isAnonymousEdit) {
            data.edit_token = anonymousEditToken;
            data.path = anonymousEditPath;
        }

        const res = await fetch('api/files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        let result;
        const contentType = res.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
            result = await res.json();
        } else {
            const text = await res.text();
            alert(`Save failed: Server returned ${res.status}. ${text.substring(0, 100)}...`);
            return false;
        }

        if (res.ok) {
            currentPath = path;
            currentHash = result.hash;
            if (result.content !== undefined) {
                editor.value = result.content;
                updatePreview();
            }
            lastSavedContent = editor.value;
            filePathInput.value = path;
            if (!isAnonymousEdit) {
                loadFileList();
            }
            return true;
        } else {
            if (isAnonymousEdit && res.status === 409) {
                showAnonymousLockNotice(result.error || '他のユーザーがこの編集リンクを開いているため、この画面では編集・保存できません。時間をおいて再読み込みしてください。');
                return false;
            }
            alert('Save failed: ' + (result.error || 'Unknown error'));
            return false;
        }
    }

    // Hidden files toggle
    function updateHiddenFilesUI() {
        if (!toggleHiddenBtn) return;
        const icon = toggleHiddenBtn.querySelector('.material-icons');
        if (showHiddenFiles) {
            toggleHiddenBtn.classList.add('active');
            icon.textContent = 'visibility';
        } else {
            toggleHiddenBtn.classList.remove('active');
            icon.textContent = 'visibility_off';
        }
    }
    updateHiddenFilesUI();

    if (toggleHiddenBtn) {
        toggleHiddenBtn.onclick = () => {
            showHiddenFiles = !showHiddenFiles;
            localStorage.setItem('mdiki_show_hidden', showHiddenFiles);
            updateHiddenFilesUI();
            loadFileList();
        };
    }

    saveBtn.onclick = async () => {
        if (await saveFile()) {
            const icon = saveBtn.querySelector('.material-icons');
            const originalIcon = icon.textContent;
            icon.textContent = 'done';
            saveBtn.classList.add('saved-success');

            setTimeout(() => {
                icon.textContent = originalIcon;
                saveBtn.classList.remove('saved-success');
            }, 2000);
        }
    };

    async function createNewFile(dir = '') {
        if (await confirmAndSave()) {
            const name = prompt('Enter file name (e.g. note.md):');
            if (!name) return;
            let path = name.toLowerCase().endsWith('.md') ? name : name + '.md';
            if (dir) {
                path = dir + '/' + path;
            }

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('path', path);
            formData.append('content', '');
            formData.append('is_base64', 'true');
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch('api/files.php', {
                method: 'POST',
                body: formData
            });

            if (res.ok) {
                const result = await res.json();
                currentPath = path;
                currentHash = result.hash;
                filePathInput.value = path;
                editor.value = '';
                lastSavedContent = '';
                updatePreview();
                loadFileList();
            } else {
                let errorMsg = 'Unknown error';
                const contentType = res.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    const data = await res.json();
                    errorMsg = data.error || errorMsg;
                } else {
                    const text = await res.text();
                    errorMsg = `Server returned ${res.status}. ${text.substring(0, 100)}...`;
                }
                alert('Failed to create file: ' + errorMsg);
            }
        }
    }

    if (newFileBtn) {
        newFileBtn.onclick = () => createNewFile();
    }

    async function createNewFolder(parentDir = '') {
        const name = prompt('Enter folder name:');
        if (!name) return;
        const path = parentDir ? parentDir + '/' + name : name;
        const res = await fetch('api/files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'mkdir',
                path: path,
                csrf_token: CSRF_TOKEN
            })
        });
        if (res.ok) {
            loadFileList();
        } else {
            let errorMsg = 'Unknown error';
            const contentType = res.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                const data = await res.json();
                errorMsg = data.error || errorMsg;
            } else {
                const text = await res.text();
                errorMsg = `Server returned ${res.status}. ${text.substring(0, 100)}...`;
            }
            alert('Failed to create folder: ' + errorMsg);
        }
    }

    if (newFolderBtn) {
        newFolderBtn.onclick = () => createNewFolder();
    }

    async function handleImageUpload(file, useDotData = false) {
        if (file.size > MAX_UPLOAD_SIZE * 1024 * 1024) {
            alert(`File is too large. Maximum size allowed is ${MAX_UPLOAD_SIZE}MB.`);
            return;
        }

        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('image', file);
        formData.append('csrf_token', CSRF_TOKEN);
        if (isAnonymousEdit) {
            formData.append('edit_token', anonymousEditToken);
        }

        const currentDir = currentPath ? currentPath.split('/').slice(0, -1).join('/') : '';
        let targetDir = currentDir;
        if (useDotData) {
            targetDir = currentDir ? currentDir + '/.data' : '.data';
        }
        formData.append('dir', targetDir);

        const res = await fetch('api/files.php', {
            method: 'POST',
            body: formData
        });

        if (res.ok) {
            const data = await res.json();
            // Use absolute-ish path from the web root (mds/...) for reliability in view/preview
            const imageMarkdown = `![${file.name}](mds/${data.path})`;

            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            editor.value = editor.value.substring(0, start) + imageMarkdown + editor.value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + imageMarkdown.length;
            updatePreview();
            if (!isAnonymousEdit) {
                loadFileList();
            }
        } else {
            let errorMsg = 'Unknown error';
            const contentType = res.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                const data = await res.json();
                errorMsg = data.error || errorMsg;
                if (isAnonymousEdit && res.status === 409) {
                    showAnonymousLockNotice(errorMsg);
                    return;
                }
            } else {
                const text = await res.text();
                errorMsg = `Server returned ${res.status}. ${text.substring(0, 100)}...`;
            }
            alert('Upload failed: ' + errorMsg);
        }
    }

    function showSuccess(btn, originalIcon = 'link') {
        const icon = btn.classList.contains('material-icons') ? btn : btn.querySelector('.material-icons');
        const prevIcon = icon.textContent;
        icon.textContent = 'done';
        btn.classList.add('copy-success');
        setTimeout(() => {
            icon.textContent = prevIcon;
            btn.classList.remove('copy-success');
        }, 2000);
    }

    function copyPublicLink(path, btn = null) {
        const url = new URL('view.html', window.location.href);
        url.searchParams.set('file', path);
        navigator.clipboard.writeText(url.href).then(() => {
            if (btn) {
                showSuccess(btn);
            }
        });
    }

    async function copyEditLink(path, btn = null) {
        const res = await fetch('api/files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'create_edit_link',
                path: path,
                csrf_token: CSRF_TOKEN
            })
        });
        const data = await res.json();
        if (!res.ok) {
            alert('編集リンクの作成に失敗しました: ' + (data.error || 'Unknown error'));
            return;
        }

        const url = new URL('editor.php', window.location.href);
        url.searchParams.set('edit_token', data.token);
        navigator.clipboard.writeText(url.href).then(() => {
            if (btn) {
                showSuccess(btn);
            }
        });
    }

    copyLinkBtn.onclick = () => {
        if (!currentPath) {
            alert('Please save the file first.');
            return;
        }
        if (isAnonymousEdit) {
            navigator.clipboard.writeText(window.location.href).then(() => {
                showSuccess(copyLinkBtn);
            });
            return;
        }
        copyPublicLink(currentPath, copyLinkBtn);
    };

    if (logoutBtn) {
        logoutBtn.onclick = async () => {
            if (await confirmAndSave()) {
                window.location.href = 'api/auth.php?action=logout';
            }
        };
    }

    async function copyCheatsheetSample(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
    }

    cheatsheetModal.querySelectorAll('.cheat-sheet-item pre').forEach(pre => {
        const sample = document.createElement('div');
        sample.className = 'cheat-sheet-sample';
        pre.parentNode.insertBefore(sample, pre);
        sample.appendChild(pre);

        const copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'cheat-sheet-copy-button';
        copyButton.title = 'Copy sample';
        copyButton.setAttribute('aria-label', 'Copy sample code');
        copyButton.innerHTML = '<span class="material-icons" aria-hidden="true">content_copy</span><span>Copy</span>';
        sample.appendChild(copyButton);

        copyButton.addEventListener('click', async () => {
            try {
                await copyCheatsheetSample(pre.textContent);
                copyButton.classList.add('copied');
                copyButton.innerHTML = '<span class="material-icons" aria-hidden="true">done</span><span>Copied</span>';
                setTimeout(() => {
                    copyButton.classList.remove('copied');
                    copyButton.innerHTML = '<span class="material-icons" aria-hidden="true">content_copy</span><span>Copy</span>';
                }, 2000);
            } catch (error) {
                console.error('Failed to copy the Markdown sample:', error);
            }
        });
    });

    cheatsheetBtn.onclick = () => {
        cheatsheetModal.style.display = 'block';
    };

    closeBtn.onclick = () => {
        cheatsheetModal.style.display = 'none';
    };

    imageModalCloseBtn.onclick = () => {
        imageModal.style.display = 'none';
    };

    window.onclick = (event) => {
        if (event.target == cheatsheetModal) {
            cheatsheetModal.style.display = 'none';
        }
        if (event.target == imageModal) {
            imageModal.style.display = 'none';
        }
        if (event.target == unsavedModal) {
            closeUnsavedModal('cancel');
        }
    };

    window.addEventListener('message', async (e) => {
        if (e.data === 'preview_ready') {
            isPreviewReady = true;
            updatePreview();
        } else if (e.data && e.data.type === 'scroll') {
            // Scroll Synchronization: Preview -> Editor
            isScrollingFromPreview = true;
            const maxScroll = editor.scrollHeight - editor.clientHeight;
            editor.scrollTop = e.data.percent * maxScroll;
            clearTimeout(scrollSyncTimeout);
            scrollSyncTimeout = setTimeout(() => {
                isScrollingFromPreview = false;
            }, 100);
        } else if (e.data && e.data.type === 'open_file') {
            if (isAnonymousEdit) return;
            if (await confirmAndSave()) {
                loadFile(e.data.path);
            }
        } else if (e.data && e.data.type === 'show_image') {
            modalImage.src = e.data.src;
            modalImageName.textContent = e.data.name;
            imageModal.style.display = 'block';
        }
    });

    if (isAnonymousEdit) {
        loadFile(anonymousEditPath);
    } else {
        loadFileList();

        // Check for file parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        const fileParam = urlParams.get('file');
        if (fileParam) {
            loadFile(fileParam);
            // Clean up URL without reloading
            window.history.replaceState({}, document.title, window.location.pathname);
        } else {
            loadFile('index.md');
        }
    }

    window.addEventListener('beforeunload', (e) => {
        if (isDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveBtn.click();
        }
    });

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            if (editor.readOnly) return;
            e.preventDefault();
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            editor.value = editor.value.substring(0, start) + '  ' + editor.value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + 2;
            updatePreview();
        }
    });

    // Session Management
    function setupSessionCheck() {
        if (isAnonymousEdit) return;
        const checkInterval = 10 * 1000; // 10秒ごとにチェック
        setInterval(async () => {
            try {
                // インターセプターが401を監視しているため、fetchするだけでOK
                await fetch('api/auth.php?action=check');
            } catch (e) {
                console.error('Session check failed', e);
            }
        }, checkInterval);
    }

    setupSessionCheck();

    async function checkAnonymousEditLock() {
        if (!isAnonymousEdit) return;
        if (anonymousLockCheckInFlight) return;
        anonymousLockCheckInFlight = true;

        try {
            const active = isAnonymousActive();
            const res = await fetch('api/files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'refresh_lock',
                    edit_token: anonymousEditToken,
                    active: active
                })
            });

            const data = await res.json();
            if (!res.ok || !data.valid) {
                showAnonymousLockNotice('この限定編集リンクは利用できません。');
                return;
            }

            setAnonymousViewerCount(data.viewer_count || 1);
            if (data.can_edit) {
                enableAnonymousEditing();
            } else if (!active) {
                showAnonymousLockNotice('しばらく操作がないため編集権を解放しました。編集を再開するには画面をクリックしてください。ファイル内容は閲覧できます。');
            } else {
                showAnonymousLockNotice('他のユーザーが編集中のため、現在は読み取り専用です。ファイル内容は閲覧できます。');
            }
        } catch (e) {
            console.error('Anonymous edit lock check failed', e);
        } finally {
            anonymousLockCheckInFlight = false;
        }
    }

    function setupAnonymousEditLock() {
        if (!isAnonymousEdit) return;

        ['mousedown', 'keydown', 'touchstart'].forEach((eventName) => {
            window.addEventListener(eventName, markAnonymousActivity, {
                passive: true
            });
        });

        checkAnonymousEditLock();
        setInterval(checkAnonymousEditLock, 30000);
        setInterval(updateAnonymousInactivityWarning, 1000);

        window.addEventListener('unload', () => {
            const payload = JSON.stringify({
                action: 'release_lock',
                edit_token: anonymousEditToken
            });
            navigator.sendBeacon('api/files.php', new Blob([payload], {
                type: 'application/json'
            }));
        });
    }

    setupAnonymousEditLock();
});
