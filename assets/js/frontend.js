/**
 * Pinned Comments – Frontend JavaScript
 * Vanilla JS, no external libraries.
 */
(function () {
    'use strict';

    var data = window.pcData || {};
    var ajaxUrl = data.ajaxUrl;
    var nonce = data.nonce;
    var postId = data.postId;
    var isLoggedIn = data.isLoggedIn;
    var currentUserId = data.userId;
    var i18n = data.i18n || {};

    var modeActive = false;
    var comments = [];
    var contentEl = null;
    var activeBubble = null;
    var activePinId = null;

    // ===== Helpers =====

    function ajax(action, params, callback) {
        var body = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce);
        for (var key in params) {
            if (params.hasOwnProperty(key)) {
                body += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        callback(null, res);
                    } catch (e) {
                        callback(e, null);
                    }
                } else {
                    callback(new Error('HTTP ' + xhr.status), null);
                }
            }
        };
        xhr.send(body);
    }

    function findContentEl() {
        var selectors = ['.entry-content', 'article .entry-content', 'article', 'main', '.post-content', '.content'];
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el && el.offsetWidth > 100) {
                return el;
            }
        }
        return document.querySelector('article') || document.querySelector('main') || document.body;
    }

    function formatTime(timeStr) {
        if (!timeStr || timeStr.indexOf('0000-00-00') === 0) return '';
        var d = new Date(timeStr.replace(' ', 'T'));
        var now = new Date();
        var diff = (now - d) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return d.toLocaleDateString();
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ===== Load Comments =====

    function loadComments() {
        ajax('pc_load_comments', { post_id: postId }, function (err, res) {
            if (err || !res || !res.success) return;
            comments = res.data.comments || [];
            renderPins();
        });
    }

    // ===== Render Pins =====

    function renderPins() {
        // Remove existing pins.
        var existing = contentEl.querySelectorAll('.pc-pin');
        for (var i = 0; i < existing.length; i++) {
            existing[i].remove();
        }

        // Render top-level comments (parent_id = 0) as pins.
        for (var j = 0; j < comments.length; j++) {
            var c = comments[j];
            if (c.parentId === 0) {
                renderPin(c);
            }
        }
    }

    function renderPin(comment) {
        var pin = document.createElement('div');
        pin.className = 'pc-pin';
        pin.dataset.id = comment.id;
        pin.style.left = comment.xPosition + '%';
        pin.style.top = comment.yPosition + '%';

        var dot = document.createElement('div');
        dot.className = 'pc-pin-dot';
        pin.appendChild(dot);

        pin.addEventListener('click', function (e) {
            e.stopPropagation();
            openThread(comment.id, pin);
        });

        contentEl.appendChild(pin);
    }

    // ===== Open Thread (Comment Bubble) =====

    function openThread(pinId, pinEl) {
        closeBubble();
        activePinId = pinId;

        // Mark pin active.
        var allPins = contentEl.querySelectorAll('.pc-pin');
        for (var i = 0; i < allPins.length; i++) {
            allPins[i].classList.remove('pc-pin-active');
        }
        pinEl.classList.add('pc-pin-active');

        // Build bubble.
        var bubble = document.createElement('div');
        bubble.className = 'pc-bubble';

        // Position bubble near pin.
        var pinRect = pinEl.getBoundingClientRect();
        var contentRect = contentEl.getBoundingClientRect();
        var bubbleWidth = 320;
        var left = pinEl.offsetLeft + 24;
        var top = pinEl.offsetTop - 10;

        // Keep bubble on screen.
        if (left + bubbleWidth > contentEl.offsetWidth) {
            left = pinEl.offsetLeft - bubbleWidth - 24;
        }
        if (left < 0) left = 8;
        if (top < 0) top = 8;

        bubble.style.left = left + 'px';
        bubble.style.top = top + 'px';

        // Header.
        var header = document.createElement('div');
        header.className = 'pc-bubble-header';
        var title = document.createElement('span');
        title.className = 'pc-bubble-title';
        title.textContent = i18n.comment || 'Comment';
        var closeBtn = document.createElement('button');
        closeBtn.className = 'pc-bubble-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeBubble();
        });
        header.appendChild(title);
        header.appendChild(closeBtn);
        bubble.appendChild(header);

        // Body (comments).
        var body = document.createElement('div');
        body.className = 'pc-bubble-body';

        // Find the main comment and its replies.
        var mainComment = null;
        var replies = [];
        for (var j = 0; j < comments.length; j++) {
            if (comments[j].id === pinId) {
                mainComment = comments[j];
            } else if (comments[j].parentId === pinId) {
                replies.push(comments[j]);
            }
        }

        if (mainComment) {
            body.appendChild(renderCommentItem(mainComment, true));
        }
        for (var k = 0; k < replies.length; k++) {
            body.appendChild(renderCommentItem(replies[k], false));
        }

        bubble.appendChild(body);

        // Form area.
        if (isLoggedIn) {
            var form = createReplyForm(pinId);
            bubble.appendChild(form);
        } else {
            var notice = document.createElement('div');
            notice.className = 'pc-login-notice';
            notice.textContent = i18n.loginToComment || 'Log in to leave comments.';
            bubble.appendChild(notice);
        }

        contentEl.appendChild(bubble);
        activeBubble = bubble;

        // Stop clicks inside bubble from creating new pins.
        bubble.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // Close on outside click.
        setTimeout(function () {
            document.addEventListener('click', outsideClickHandler);
        }, 0);
    }

    function outsideClickHandler(e) {
        if (activeBubble && !activeBubble.contains(e.target) && !e.target.classList.contains('pc-pin') && !e.target.closest('.pc-pin')) {
            closeBubble();
        }
    }

    function closeBubble() {
        if (activeBubble) {
            activeBubble.remove();
            activeBubble = null;
        }
        activePinId = null;
        var allPins = contentEl.querySelectorAll('.pc-pin');
        for (var i = 0; i < allPins.length; i++) {
            allPins[i].classList.remove('pc-pin-active');
        }
        document.removeEventListener('click', outsideClickHandler);
    }

    // ===== Render Comment Item =====

    function renderCommentItem(comment, isMain) {
        var item = document.createElement('div');
        item.className = 'pc-comment';
        item.dataset.id = comment.id;

        // Head.
        var head = document.createElement('div');
        head.className = 'pc-comment-head';

        if (comment.authorAvatar) {
            var avatar = document.createElement('img');
            avatar.className = 'pc-comment-avatar';
            avatar.src = comment.authorAvatar;
            avatar.alt = '';
            head.appendChild(avatar);
        }

        var nameTime = document.createElement('div');
        var name = document.createElement('div');
        name.className = 'pc-comment-author';
        name.textContent = comment.authorName;
        var time = document.createElement('div');
        time.className = 'pc-comment-time';
        time.textContent = formatTime(comment.createdAt);
        if (comment.createdAt !== comment.modifiedAt && comment.modifiedAt.indexOf('0000-00-00') !== 0) {
            var edited = document.createElement('span');
            edited.className = 'pc-comment-edited';
            edited.textContent = ' (' + (i18n.edited || 'edited') + ')';
            time.appendChild(edited);
        }
        nameTime.appendChild(name);
        nameTime.appendChild(time);
        head.appendChild(nameTime);

        item.appendChild(head);

        // Content.
        var content = document.createElement('div');
        content.className = 'pc-comment-content';
        content.textContent = comment.content;
        item.appendChild(content);

        // Actions (only for own comments).
        if (comment.isOwn) {
            var actions = document.createElement('div');
            actions.className = 'pc-comment-actions';

            var editBtn = document.createElement('button');
            editBtn.className = 'pc-comment-action';
            editBtn.textContent = i18n.edit || 'Edit';
            editBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                showEditForm(item, comment);
            });

            var deleteBtn = document.createElement('button');
            deleteBtn.className = 'pc-comment-action pc-action-delete';
            deleteBtn.textContent = i18n.delete || 'Delete';
            deleteBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                handleDelete(comment);
            });

            actions.appendChild(editBtn);
            actions.appendChild(deleteBtn);
            item.appendChild(actions);
        }

        // Reply button (for others' comments, if logged in).
        if (isLoggedIn && !comment.isOwn && isMain) {
            var replyActions = document.createElement('div');
            replyActions.className = 'pc-comment-actions';
            var replyBtn = document.createElement('button');
            replyBtn.className = 'pc-comment-action';
            replyBtn.textContent = i18n.reply || 'Reply';
            replyBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var form = activeBubble.querySelector('.pc-bubble-form textarea');
                if (form) form.focus();
            });
            replyActions.appendChild(replyBtn);
            item.appendChild(replyActions);
        }

        return item;
    }

    // ===== Edit Form =====

    function showEditForm(item, comment) {
        var existing = item.querySelector('.pc-comment-edit-form');
        if (existing) {
            existing.remove();
            return;
        }

        var formDiv = document.createElement('div');
        formDiv.className = 'pc-comment-edit-form';

        var textarea = document.createElement('textarea');
        textarea.value = comment.content;

        var actions = document.createElement('div');
        actions.className = 'pc-edit-actions';

        var saveBtn = document.createElement('button');
        saveBtn.className = 'pc-btn pc-btn-primary';
        saveBtn.textContent = i18n.save || 'Save';

        var cancelBtn = document.createElement('button');
        cancelBtn.className = 'pc-btn pc-btn-secondary';
        cancelBtn.textContent = i18n.cancel || 'Cancel';

        saveBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var val = textarea.value.trim();
            if (!val) return;
            saveBtn.disabled = true;
            ajax('pc_edit_comment', {
                comment_id: comment.id,
                content: val
            }, function (err, res) {
                saveBtn.disabled = false;
                if (err || !res || !res.success) {
                    alert(i18n.error || 'Something went wrong.');
                    return;
                }
                // Update local data.
                for (var i = 0; i < comments.length; i++) {
                    if (comments[i].id === comment.id) {
                        comments[i].content = res.data.comment.content;
                        comments[i].modifiedAt = res.data.comment.modifiedAt;
                        break;
                    }
                }
                // Re-render thread.
                if (activePinId) {
                    var pinEl = contentEl.querySelector('.pc-pin[data-id="' + activePinId + '"]');
                    if (pinEl) {
                        openThread(activePinId, pinEl);
                    }
                }
            });
        });

        cancelBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            formDiv.remove();
        });

        actions.appendChild(saveBtn);
        actions.appendChild(cancelBtn);
        formDiv.appendChild(textarea);
        formDiv.appendChild(actions);

        item.appendChild(formDiv);
        textarea.focus();
    }

    // ===== Reply Form =====

    function createReplyForm(parentId) {
        var form = document.createElement('div');
        form.className = 'pc-bubble-form';

        var textarea = document.createElement('textarea');
        textarea.placeholder = i18n.replyPlaceholder || 'Write a reply...';

        var actions = document.createElement('div');
        actions.className = 'pc-bubble-form-actions';

        var sendBtn = document.createElement('button');
        sendBtn.className = 'pc-btn pc-btn-primary';
        sendBtn.textContent = i18n.send || 'Send';

        sendBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var val = textarea.value.trim();
            if (!val) return;
            sendBtn.disabled = true;
            ajax('pc_create_comment', {
                post_id: postId,
                content: val,
                parent_id: parentId,
                x_position: 0,
                y_position: 0
            }, function (err, res) {
                sendBtn.disabled = false;
                if (err || !res || !res.success) {
                    alert(i18n.error || 'Something went wrong.');
                    return;
                }
                comments.push(res.data.comment);
                textarea.value = '';
                // Re-render thread.
                if (activePinId) {
                    var pinEl = contentEl.querySelector('.pc-pin[data-id="' + activePinId + '"]');
                    if (pinEl) {
                        openThread(activePinId, pinEl);
                    }
                }
            });
        });

        actions.appendChild(sendBtn);
        form.appendChild(textarea);
        form.appendChild(actions);

        // Submit on Ctrl+Enter.
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                sendBtn.click();
            }
        });

        return form;
    }

    // ===== Delete Comment =====

    function handleDelete(comment) {
        if (!confirm(i18n.confirmDelete || 'Delete this comment and all replies?')) return;

        ajax('pc_delete_comment', {
            comment_id: comment.id
        }, function (err, res) {
            if (err || !res || !res.success) {
                alert(i18n.error || 'Something went wrong.');
                return;
            }

            // Remove from local data.
            comments = comments.filter(function (c) {
                return c.id !== comment.id && c.parentId !== comment.id;
            });

            if (comment.parentId === 0) {
                // It was a pin — remove it and close bubble.
                closeBubble();
                renderPins();
            } else {
                // It was a reply — re-render thread.
                if (activePinId) {
                    var pinEl = contentEl.querySelector('.pc-pin[data-id="' + activePinId + '"]');
                    if (pinEl) {
                        openThread(activePinId, pinEl);
                    }
                }
            }
        });
    }

    // ===== New Comment (click on content) =====

    function handleContentClick(e) {
        if (!modeActive || !isLoggedIn) return;
        if (e.target.closest('.pc-bubble') || e.target.closest('.pc-pin') || e.target.closest('#pc-toggle-bar')) return;

        e.preventDefault();
        e.stopPropagation();

        var rect = contentEl.getBoundingClientRect();
        var x = ((e.clientX - rect.left) / rect.width) * 100;
        var y = ((e.clientY - rect.top) / rect.height) * 100;

        // Clamp values.
        x = Math.max(0, Math.min(100, x));
        y = Math.max(0, Math.min(100, y));

        showNewCommentForm(x, y);
    }

    function showNewCommentForm(x, y) {
        closeBubble();

        // Create temporary pin.
        var pin = document.createElement('div');
        pin.className = 'pc-pin pc-pin-new';
        pin.style.left = x + '%';
        pin.style.top = y + '%';
        var dot = document.createElement('div');
        dot.className = 'pc-pin-dot';
        pin.appendChild(dot);
        contentEl.appendChild(pin);

        // Create bubble with new comment form.
        var bubble = document.createElement('div');
        bubble.className = 'pc-bubble';

        var bubbleWidth = 320;
        var left = pin.offsetLeft + 24;
        var top = pin.offsetTop - 10;
        if (left + bubbleWidth > contentEl.offsetWidth) {
            left = pin.offsetLeft - bubbleWidth - 24;
        }
        if (left < 0) left = 8;
        if (top < 0) top = 8;

        bubble.style.left = left + 'px';
        bubble.style.top = top + 'px';

        // Header.
        var header = document.createElement('div');
        header.className = 'pc-bubble-header';
        var title = document.createElement('span');
        title.className = 'pc-bubble-title';
        title.textContent = i18n.comment || 'Comment';
        var closeBtn = document.createElement('button');
        closeBtn.className = 'pc-bubble-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeNewCommentForm(pin, bubble);
        });
        header.appendChild(title);
        header.appendChild(closeBtn);
        bubble.appendChild(header);

        // Form.
        var form = document.createElement('div');
        form.className = 'pc-bubble-form';

        var textarea = document.createElement('textarea');
        textarea.placeholder = i18n.placeholder || 'Write a comment...';

        var actions = document.createElement('div');
        actions.className = 'pc-bubble-form-actions';

        var sendBtn = document.createElement('button');
        sendBtn.className = 'pc-btn pc-btn-primary';
        sendBtn.textContent = i18n.send || 'Send';

        sendBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var val = textarea.value.trim();
            if (!val) return;
            sendBtn.disabled = true;
            ajax('pc_create_comment', {
                post_id: postId,
                content: val,
                parent_id: 0,
                x_position: x,
                y_position: y
            }, function (err, res) {
                sendBtn.disabled = false;
                if (err || !res || !res.success) {
                    alert(i18n.error || 'Something went wrong.');
                    return;
                }
                comments.push(res.data.comment);
                closeNewCommentForm(pin, bubble);
                renderPins();
            });
        });

        actions.appendChild(sendBtn);
        form.appendChild(textarea);
        form.appendChild(actions);
        bubble.appendChild(form);

        // Stop propagation.
        bubble.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        contentEl.appendChild(bubble);
        activeBubble = bubble;

        textarea.focus();

        // Ctrl+Enter to submit.
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                sendBtn.click();
            }
        });

        // Close on outside click.
        setTimeout(function () {
            document.addEventListener('click', function newCommentOutside(e) {
                if (bubble && !bubble.contains(e.target) && !pin.contains(e.target)) {
                    closeNewCommentForm(pin, bubble);
                    document.removeEventListener('click', newCommentOutside);
                }
            });
        }, 0);
    }

    function closeNewCommentForm(pin, bubble) {
        if (pin) pin.remove();
        if (bubble) bubble.remove();
        if (activeBubble === bubble) activeBubble = null;
    }

    // ===== Toggle Comment Mode =====

    function toggleMode() {
        modeActive = !modeActive;
        var btn = document.getElementById('pc-toggle-btn');
        var label = btn.querySelector('.pc-toggle-label');
        var overlay = document.getElementById('pc-overlay');

        if (modeActive) {
            document.body.classList.add('pc-mode-active');
            btn.setAttribute('aria-pressed', 'true');
            label.textContent = i18n.exitCommentMode || 'Exit Comment Mode';
            overlay.setAttribute('aria-hidden', 'false');
            contentEl = findContentEl();
            if (contentEl) {
                contentEl.addEventListener('click', handleContentClick);
                loadComments();
            }
        } else {
            document.body.classList.remove('pc-mode-active');
            btn.setAttribute('aria-pressed', 'false');
            label.textContent = i18n.commentMode || 'Comment Mode';
            overlay.setAttribute('aria-hidden', 'true');
            closeBubble();
            if (contentEl) {
                contentEl.removeEventListener('click', handleContentClick);
            }
            // Remove pins.
            var pins = document.querySelectorAll('.pc-pin');
            for (var i = 0; i < pins.length; i++) {
                pins[i].remove();
            }
        }
    }

    // ===== Init =====

    function init() {
        var btn = document.getElementById('pc-toggle-btn');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleMode();
        });

        // ESC to close bubble.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (activeBubble) {
                    closeBubble();
                } else if (modeActive) {
                    toggleMode();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
