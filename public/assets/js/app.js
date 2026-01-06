document.addEventListener('DOMContentLoaded', () => {
    const editor = document.getElementById('markdown-editor');
    const previewFrame = document.getElementById('preview-frame');
    const fileTree = document.getElementById('file-tree');
    const filePathInput = document.getElementById('file-path');
    const saveBtn = document.getElementById('save-file');
    const newFileBtn = document.getElementById('new-file');
    const newFolderBtn = document.getElementById('new-folder');
    const uploadImageBtn = document.getElementById('upload-image');
    const imageInput = document.getElementById('image-input');
    const copyLinkBtn = document.getElementById('copy-link');
    const logoutBtn = document.getElementById('logout');
    const cheatsheetBtn = document.getElementById('show-cheatsheet');
    const cheatsheetModal = document.getElementById('cheatsheet-modal');
    const closeBtn = cheatsheetModal.querySelector('.close-button');

    const imageModal = document.getElementById('image-modal');
    const imageModalCloseBtn = imageModal.querySelector('.close-button');
    const modalImage = document.getElementById('modal-image');
    const modalImageName = document.getElementById('modal-image-name');

    let currentPath = '';
    let currentHash = '';
    let isPreviewReady = false;
    let lastSavedContent = '';

    function isDirty() {
        return editor.value !== lastSavedContent;
    }

    async function confirmAndSave() {
        if (!isDirty()) return true;
        if (confirm('You have unsaved changes. Save and continue?')) {
            return await saveFile();
        }
        return false;
    }

    async function loadFileList() {
        const res = await fetch('api/files.php?action=list');
        const files = await res.json();
        renderFileTree(files, fileTree, true);
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
                    if (isAnyExpanded) {
                        container.classList.add('collapsed');
                    } else {
                        container.classList.remove('collapsed');
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
            const item = document.createElement('div');
            item.className = 'file-item-container';

            const div = document.createElement('div');
            div.className = `file-item ${file.is_dir ? 'dir-item' : ''}`;
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
                    item.classList.toggle('collapsed');
                };
                div.appendChild(toggleIcon);
            } else {
                const spacer = document.createElement('span');
                spacer.className = 'toggle-spacer';
                div.appendChild(spacer);
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
                    item.classList.toggle('collapsed');
                };
            }
            div.appendChild(iconSpan);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'file-name';
            nameSpan.textContent = file.name;
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
                    item.classList.toggle('collapsed');
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
        const res = await fetch('api/files.php?action=move', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ oldPath, newPath, csrf_token: CSRF_TOKEN })
        });
        if (res.ok) {
            if (currentPath === oldPath) {
                currentPath = newPath;
                filePathInput.value = newPath;
            }
            loadFileList();
        } else {
            const data = await res.json();
            alert('Move failed: ' + (data.error || 'Unknown error'));
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
        const res = await fetch(`api/files.php?action=get&path=${encodeURIComponent(path)}`);
        if (!res.ok) return; // ファイルが存在しない場合は何もしない
        const data = await res.json();
        if (data.content !== undefined) {
            currentPath = path;
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
        const res = await fetch('api/files.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path, csrf_token: CSRF_TOKEN })
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
            previewFrame.contentWindow.postMessage(markdown, '*');
        }
    }

    editor.addEventListener('input', updatePreview);

    async function saveFile() {
        let path = currentPath;
        if (!path) {
            const name = prompt('Enter file name (e.g. folder/note.md):');
            if (!name) return false;
            path = name.endsWith('.md') ? name : name + '.md';
        }

        const res = await fetch('api/files.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                path: path,
                content: editor.value,
                old_hash: currentHash,
                csrf_token: CSRF_TOKEN
            })
        });

        const result = await res.json();
        if (res.ok) {
            currentPath = path;
            currentHash = result.hash;
            lastSavedContent = editor.value;
            filePathInput.value = path;
            loadFileList();
            return true;
        } else {
            alert('Save failed: ' + (result.error || 'Unknown error'));
            return false;
        }
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

            const res = await fetch('api/files.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    path: path,
                    content: '',
                    csrf_token: CSRF_TOKEN
                })
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
                const data = await res.json();
                alert('Failed to create file: ' + (data.error || 'Unknown error'));
            }
        }
    }

    newFileBtn.onclick = () => createNewFile();

    async function createNewFolder(parentDir = '') {
        const name = prompt('Enter folder name:');
        if (!name) return;
        const path = parentDir ? parentDir + '/' + name : name;
        const res = await fetch('api/files.php?action=mkdir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: path, csrf_token: CSRF_TOKEN })
        });
        if (res.ok) {
            loadFileList();
        } else {
            const data = await res.json();
            alert('Failed to create folder: ' + (data.error || 'Unknown error'));
        }
    }

    newFolderBtn.onclick = () => createNewFolder();

    uploadImageBtn.onclick = () => {
        imageInput.click();
    };

    imageInput.onchange = async () => {
        if (imageInput.files.length === 0) return;
        const file = imageInput.files[0];
        const formData = new FormData();
        formData.append('image', file);
        formData.append('csrf_token', CSRF_TOKEN);
        // Upload to current directory if possible, otherwise root
        const currentDir = currentPath ? currentPath.split('/').slice(0, -1).join('/') : '';
        formData.append('dir', currentDir);

        const res = await fetch('api/files.php?action=upload', {
            method: 'POST',
            body: formData
        });

        if (res.ok) {
            const data = await res.json();
            const imageMarkdown = `![${file.name}](mds/${data.path})`;
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            editor.value = editor.value.substring(0, start) + imageMarkdown + editor.value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + imageMarkdown.length;
            updatePreview();
            loadFileList();
            imageInput.value = ''; // Reset input
        } else {
            const data = await res.json();
            alert('Upload failed: ' + (data.error || 'Unknown error'));
        }
    };

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

    copyLinkBtn.onclick = () => {
        if (!currentPath) {
            alert('Please save the file first.');
            return;
        }
        copyPublicLink(currentPath, copyLinkBtn);
    };

    logoutBtn.onclick = async () => {
        if (await confirmAndSave()) {
            window.location.href = 'api/auth.php?action=logout';
        }
    };

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
    };

    window.addEventListener('message', async (e) => {
        if (e.data === 'preview_ready') {
            isPreviewReady = true;
            updatePreview();
        } else if (e.data && e.data.type === 'open_file') {
            if (await confirmAndSave()) {
                loadFile(e.data.path);
            }
        } else if (e.data && e.data.type === 'show_image') {
            modalImage.src = e.data.src;
            modalImageName.textContent = e.data.name;
            imageModal.style.display = 'block';
        }
    });

    loadFileList();
    loadFile('index.md');

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

    editor.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            editor.value = editor.value.substring(0, start) + '  ' + editor.value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + 2;
            updatePreview();
        }
    });
});
