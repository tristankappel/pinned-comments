/**
 * Pinned Comments – Frontend JavaScript
 * Vanilla JS, no external libraries.
 */
(function () {
	"use strict";

	var data = window.pcData || {};
	var ajaxUrl = data.ajaxUrl;
	var nonce = data.nonce;
	var postId = data.postId;
	var isLoggedIn = data.isLoggedIn;
	var currentUserId = data.userId;
	var contentWidth = data.contentWidth || 800;
	var mobileWidth = data.mobileWidth || 390;
	var isFrame = !!data.isFrame;
	var canUpload = !!data.canUpload;
	var maxImages = data.maxImages || 5;
	var i18n = data.i18n || {};

	var VIEWPORT_STORAGE_KEY = "pcViewport";
	var ORIGIN = window.location.protocol + "//" + window.location.host;

	var modeActive = false;
	var comments = [];
	var contentEl = null;
	var pinLayer = null;
	var activeBubble = null;
	var activePinId = null;
	var viewport = readStoredViewport();

	// ===== Helpers =====

	function readStoredViewport() {
		var stored = null;
		try {
			stored = window.localStorage.getItem(VIEWPORT_STORAGE_KEY);
		} catch (e) {
			stored = null;
		}
		if (stored === "mobile" || stored === "desktop") {
			return stored;
		}
		return window.innerWidth <= 768 ? "mobile" : "desktop";
	}

	function storeViewport(value) {
		try {
			window.localStorage.setItem(VIEWPORT_STORAGE_KEY, value);
		} catch (e) {
			/* storage unavailable – keep the choice for this page view only */
		}
	}

	function viewportWidth() {
		return viewport === "mobile" ? mobileWidth : contentWidth;
	}

	// Keep in sync with the .pc-bubble widths in frontend.css.
	function bubbleSize() {
		return Math.min(380, Math.max(220, window.innerWidth - 48));
	}

	/**
	 * @param {string}   action
	 * @param {Object}   params
	 * @param {Function} callback
	 * @param {Object}   [extra] Optional { files: File[], removals: string[] }.
	 */
	function ajax(action, params, callback, extra) {
		var body = new FormData();
		body.append("action", action);
		body.append("nonce", nonce);
		for (var key in params) {
			if (params.hasOwnProperty(key)) {
				body.append(key, params[key]);
			}
		}
		if (extra && extra.files) {
			for (var i = 0; i < extra.files.length; i++) {
				body.append("pc_images[]", extra.files[i]);
			}
		}
		if (extra && extra.removals) {
			for (var j = 0; j < extra.removals.length; j++) {
				body.append("remove_attachments[]", extra.removals[j]);
			}
		}
		var xhr = new XMLHttpRequest();
		xhr.open("POST", ajaxUrl, true);
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
					callback(new Error("HTTP " + xhr.status), null);
				}
			}
		};
		xhr.send(body);
	}

	function findContentEl() {
		var selectors = [
			".entry-content",
			"article .entry-content",
			"article",
			"main",
			".post-content",
			".content",
		];
		for (var i = 0; i < selectors.length; i++) {
			var el = document.querySelector(selectors[i]);
			if (el && el.offsetWidth > 100) {
				return el;
			}
		}
		return (
			document.querySelector("article") ||
			document.querySelector("main") ||
			document.body
		);
	}

	function formatTime(timeStr) {
		if (!timeStr || timeStr.indexOf("0000-00-00") === 0) return "";
		var d = new Date(timeStr.replace(" ", "T"));
		var now = new Date();
		var diff = (now - d) / 1000;
		if (diff < 60) return "just now";
		if (diff < 3600) return Math.floor(diff / 60) + "m ago";
		if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
		if (diff < 604800) return Math.floor(diff / 86400) + "d ago";
		return d.toLocaleDateString();
	}

	function escapeHtml(str) {
		var div = document.createElement("div");
		div.textContent = str;
		return div.innerHTML;
	}

	// ===== Image Uploader =====

	/**
	 * Thumbnail list plus file picker, capped at `maxImages` entries.
	 *
	 * @param {Array} existing Already stored attachments ({ file, url }), edit mode only.
	 */
	function createUploader(existing) {
		var kept = (existing || []).slice();
		var removals = [];
		var picked = [];
		var previews = [];

		var wrap = document.createElement("div");
		wrap.className = "pc-uploader";

		var list = document.createElement("div");
		list.className = "pc-upload-list";

		var bar = document.createElement("div");
		bar.className = "pc-upload-bar";

		var input = document.createElement("input");
		input.type = "file";
		input.className = "pc-upload-input";
		input.accept = "image/jpeg,image/png,image/gif,image/webp";
		input.multiple = true;

		var addBtn = document.createElement("button");
		addBtn.type = "button";
		addBtn.className = "pc-upload-add";
		addBtn.textContent = i18n.addImages || "Add images";

		var info = document.createElement("span");
		info.className = "pc-upload-info";

		function total() {
			return kept.length + picked.length;
		}

		function thumb(src, onRemove) {
			var item = document.createElement("div");
			item.className = "pc-upload-thumb";

			var img = document.createElement("img");
			img.src = src;
			img.alt = "";
			item.appendChild(img);

			var remove = document.createElement("button");
			remove.type = "button";
			remove.className = "pc-upload-remove";
			remove.innerHTML = "&times;";
			remove.title = i18n.removeImage || "Remove image";
			remove.addEventListener("click", function (e) {
				e.stopPropagation();
				onRemove();
				render();
			});
			item.appendChild(remove);

			return item;
		}

		function render() {
			list.innerHTML = "";

			kept.forEach(function (attachment) {
				list.appendChild(
					thumb(attachment.url, function () {
						removals.push(attachment.file);
						kept = kept.filter(function (a) {
							return a.file !== attachment.file;
						});
					})
				);
			});

			picked.forEach(function (file, index) {
				var url = previews[index];
				list.appendChild(
					thumb(url, function () {
						URL.revokeObjectURL(url);
						picked.splice(index, 1);
						previews.splice(index, 1);
					})
				);
			});

			info.textContent = total() + " / " + maxImages;
			addBtn.disabled = total() >= maxImages;
		}

		addBtn.addEventListener("click", function (e) {
			e.stopPropagation();
			input.click();
		});

		input.addEventListener("change", function () {
			var files = Array.prototype.slice.call(input.files || []);
			var room = maxImages - total();
			if (files.length > room) {
				files = files.slice(0, Math.max(0, room));
				alert(i18n.maxImagesReached || "Too many images.");
			}
			files.forEach(function (file) {
				picked.push(file);
				previews.push(URL.createObjectURL(file));
			});
			input.value = "";
			render();
		});

		bar.appendChild(addBtn);
		bar.appendChild(info);
		wrap.appendChild(list);
		wrap.appendChild(bar);
		wrap.appendChild(input);
		render();

		return {
			el: wrap,
			files: function () {
				return picked;
			},
			removals: function () {
				return removals;
			},
			count: function () {
				return total();
			},
			reset: function () {
				previews.forEach(function (url) {
					URL.revokeObjectURL(url);
				});
				picked = [];
				previews = [];
				removals = [];
				render();
			},
		};
	}

	function renderAttachments(comment) {
		if (!comment.attachments || !comment.attachments.length) return null;

		var grid = document.createElement("div");
		grid.className = "pc-comment-attachments";

		comment.attachments.forEach(function (attachment) {
			var link = document.createElement("a");
			link.className = "pc-comment-attachment";
			link.href = attachment.url;
			link.target = "_blank";
			link.rel = "noopener noreferrer";

			var img = document.createElement("img");
			img.src = attachment.url;
			img.alt = i18n.attachments || "Images";
			img.loading = "lazy";
			link.appendChild(img);

			grid.appendChild(link);
		});

		return grid;
	}

	// ===== Load Comments =====

	function loadComments() {
		var requestedViewport = viewport;
		ajax(
			"pc_load_comments",
			{ post_id: postId, viewport: requestedViewport },
			function (err, res) {
				if (err || !res || !res.success) return;
				// Ignore stale responses after a viewport switch.
				if (requestedViewport !== viewport || !pinLayer) return;
				comments = res.data.comments || [];
				renderPins();
			}
		);
	}

	// ===== Render Pins =====

	function renderPins() {
		if (!pinLayer) return;

		// Remove existing pins.
		var existing = pinLayer.querySelectorAll(".pc-pin");
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
		var pin = document.createElement("div");
		pin.className = "pc-pin";
		pin.dataset.id = comment.id;
		pin.style.left = comment.xPosition + "%";
		pin.style.top = comment.yPosition + "%";

		var dot = document.createElement("div");
		dot.className = "pc-pin-dot";
		pin.appendChild(dot);

		pin.addEventListener("click", function (e) {
			e.stopPropagation();
			openThread(comment.id, pin);
		});

		pinLayer.appendChild(pin);
	}

	// ===== Open Thread (Comment Bubble) =====

	function openThread(pinId, pinEl) {
		closeBubble();
		activePinId = pinId;

		// Mark pin active.
		var allPins = pinLayer.querySelectorAll(".pc-pin");
		for (var i = 0; i < allPins.length; i++) {
			allPins[i].classList.remove("pc-pin-active");
		}
		pinEl.classList.add("pc-pin-active");

		// Build bubble.
		var bubble = document.createElement("div");
		bubble.className = "pc-bubble";

		// Position bubble near pin.
		var pinRect = pinEl.getBoundingClientRect();
		var layerRect = pinLayer.getBoundingClientRect();
		var bubbleWidth = bubbleSize();
		var left = pinEl.offsetLeft + 24;
		var top = pinEl.offsetTop - 10;

		// Keep bubble on screen.
		if (left + bubbleWidth > pinLayer.offsetWidth) {
			left = pinEl.offsetLeft - bubbleWidth - 24;
		}
		if (left < 0) left = 8;
		if (top < 0) top = 8;

		bubble.style.left = left + "px";
		bubble.style.top = top + "px";

		// Header.
		var header = document.createElement("div");
		header.className = "pc-bubble-header";
		var title = document.createElement("span");
		title.className = "pc-bubble-title";
		title.textContent = i18n.comment || "Comment";
		var closeBtn = document.createElement("button");
		closeBtn.className = "pc-bubble-close";
		closeBtn.innerHTML = "&times;";
		closeBtn.addEventListener("click", function (e) {
			e.stopPropagation();
			closeBubble();
		});
		header.appendChild(title);
		header.appendChild(closeBtn);
		bubble.appendChild(header);

		// Body (comments).
		var body = document.createElement("div");
		body.className = "pc-bubble-body";

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
			var notice = document.createElement("div");
			notice.className = "pc-login-notice";
			notice.textContent = i18n.loginToComment || "Log in to leave comments.";
			bubble.appendChild(notice);
		}

		pinLayer.appendChild(bubble);
		activeBubble = bubble;

		// Stop clicks inside bubble from creating new pins.
		bubble.addEventListener("click", function (e) {
			e.stopPropagation();
		});

		// Close on outside click.
		setTimeout(function () {
			document.addEventListener("click", outsideClickHandler);
		}, 0);
	}

	function outsideClickHandler(e) {
		if (
			activeBubble &&
			!activeBubble.contains(e.target) &&
			!e.target.classList.contains("pc-pin") &&
			!e.target.closest(".pc-pin")
		) {
			closeBubble();
		}
	}

	function closeBubble() {
		if (activeBubble) {
			activeBubble.remove();
			activeBubble = null;
		}
		activePinId = null;
		if (pinLayer) {
			var allPins = pinLayer.querySelectorAll(".pc-pin");
			for (var i = 0; i < allPins.length; i++) {
				allPins[i].classList.remove("pc-pin-active");
			}
		}
		document.removeEventListener("click", outsideClickHandler);
	}

	// ===== Render Comment Item =====

	function renderCommentItem(comment, isMain) {
		var item = document.createElement("div");
		item.className = "pc-comment";
		item.dataset.id = comment.id;

		// Head.
		var head = document.createElement("div");
		head.className = "pc-comment-head";

		if (comment.authorAvatar) {
			var avatar = document.createElement("img");
			avatar.className = "pc-comment-avatar";
			avatar.src = comment.authorAvatar;
			avatar.alt = "";
			head.appendChild(avatar);
		}

		var nameTime = document.createElement("div");
		var name = document.createElement("div");
		name.className = "pc-comment-author";
		name.textContent = comment.authorName;
		var time = document.createElement("div");
		time.className = "pc-comment-time";
		time.textContent = formatTime(comment.createdAt);
		if (
			comment.createdAt !== comment.modifiedAt &&
			comment.modifiedAt.indexOf("0000-00-00") !== 0
		) {
			var edited = document.createElement("span");
			edited.className = "pc-comment-edited";
			edited.textContent = " (" + (i18n.edited || "edited") + ")";
			time.appendChild(edited);
		}
		nameTime.appendChild(name);
		nameTime.appendChild(time);
		head.appendChild(nameTime);

		item.appendChild(head);

		// Content.
		var content = document.createElement("div");
		content.className = "pc-comment-content";
		content.textContent = comment.content;
		item.appendChild(content);

		var attachments = renderAttachments(comment);
		if (attachments) {
			item.appendChild(attachments);
		}

		// Actions (only for own comments).
		if (comment.isOwn) {
			var actions = document.createElement("div");
			actions.className = "pc-comment-actions";

			var editBtn = document.createElement("button");
			editBtn.className = "pc-comment-action";
			editBtn.textContent = i18n.edit || "Edit";
			editBtn.addEventListener("click", function (e) {
				e.stopPropagation();
				showEditForm(item, comment);
			});

			var deleteBtn = document.createElement("button");
			deleteBtn.className = "pc-comment-action pc-action-delete";
			deleteBtn.textContent = i18n.delete || "Delete";
			deleteBtn.addEventListener("click", function (e) {
				e.stopPropagation();
				handleDelete(comment);
			});

			actions.appendChild(editBtn);
			actions.appendChild(deleteBtn);
			item.appendChild(actions);
		}

		// Reply button (for others' comments, if logged in).
		if (isLoggedIn && !comment.isOwn && isMain) {
			var replyActions = document.createElement("div");
			replyActions.className = "pc-comment-actions";
			var replyBtn = document.createElement("button");
			replyBtn.className = "pc-comment-action";
			replyBtn.textContent = i18n.reply || "Reply";
			replyBtn.addEventListener("click", function (e) {
				e.stopPropagation();
				var form = activeBubble.querySelector(".pc-bubble-form textarea");
				if (form) form.focus();
			});
			replyActions.appendChild(replyBtn);
			item.appendChild(replyActions);
		}

		return item;
	}

	// ===== Edit Form =====

	function showEditForm(item, comment) {
		var existing = item.querySelector(".pc-comment-edit-form");
		if (existing) {
			existing.remove();
			return;
		}

		var formDiv = document.createElement("div");
		formDiv.className = "pc-comment-edit-form";

		var textarea = document.createElement("textarea");
		textarea.value = comment.content;

		var uploader = canUpload ? createUploader(comment.attachments) : null;

		var actions = document.createElement("div");
		actions.className = "pc-edit-actions";

		var saveBtn = document.createElement("button");
		saveBtn.className = "pc-btn pc-btn-primary";
		saveBtn.textContent = i18n.save || "Save";

		var cancelBtn = document.createElement("button");
		cancelBtn.className = "pc-btn pc-btn-secondary";
		cancelBtn.textContent = i18n.cancel || "Cancel";

		saveBtn.addEventListener("click", function (e) {
			e.stopPropagation();
			var val = textarea.value.trim();
			var hasImages = uploader
				? uploader.count() > 0
				: (comment.attachments || []).length > 0;
			if (!val && !hasImages) return;
			saveBtn.disabled = true;
			ajax(
				"pc_edit_comment",
				{
					comment_id: comment.id,
					content: val,
				},
				function (err, res) {
					saveBtn.disabled = false;
					if (err || !res || !res.success) {
						alert(
							(res && res.data && res.data.message) ||
								i18n.error ||
								"Something went wrong."
						);
						return;
					}
					// Update local data.
					for (var i = 0; i < comments.length; i++) {
						if (comments[i].id === comment.id) {
							comments[i].content = res.data.comment.content;
							comments[i].attachments = res.data.comment.attachments;
							comments[i].modifiedAt = res.data.comment.modifiedAt;
							break;
						}
					}
					// Re-render thread.
					if (activePinId) {
						var pinEl = contentEl.querySelector(
							'.pc-pin[data-id="' + activePinId + '"]'
						);
						if (pinEl) {
							openThread(activePinId, pinEl);
						}
					}
				},
				uploader
					? { files: uploader.files(), removals: uploader.removals() }
					: null
			);
		});

		cancelBtn.addEventListener("click", function (e) {
			e.stopPropagation();
			if (uploader) uploader.reset();
			formDiv.remove();
		});

		actions.appendChild(saveBtn);
		actions.appendChild(cancelBtn);
		formDiv.appendChild(textarea);
		if (uploader) {
			formDiv.appendChild(uploader.el);
		}
		formDiv.appendChild(actions);

		item.appendChild(formDiv);
		textarea.focus();
	}

	// ===== Reply Form =====

	function createReplyForm(parentId) {
		var form = document.createElement("div");
		form.className = "pc-bubble-form";

		var textarea = document.createElement("textarea");
		textarea.placeholder = i18n.replyPlaceholder || "Write a reply...";

		var uploader = canUpload ? createUploader() : null;

		var actions = document.createElement("div");
		actions.className = "pc-bubble-form-actions";

		var sendBtn = document.createElement("button");
		sendBtn.className = "pc-btn pc-btn-primary";
		sendBtn.textContent = i18n.send || "Send";

		sendBtn.addEventListener("click", function (e) {
			e.stopPropagation();
			var val = textarea.value.trim();
			if (!val && !(uploader && uploader.count())) return;
			sendBtn.disabled = true;
			ajax(
				"pc_create_comment",
				{
					post_id: postId,
					content: val,
					parent_id: parentId,
					x_position: 0,
					y_position: 0,
					viewport: viewport,
				},
				function (err, res) {
					sendBtn.disabled = false;
					if (err || !res || !res.success) {
						alert(
							(res && res.data && res.data.message) ||
								i18n.error ||
								"Something went wrong."
						);
						return;
					}
					comments.push(res.data.comment);
					textarea.value = "";
					if (uploader) uploader.reset();
					// Re-render thread.
					if (activePinId) {
						var pinEl = contentEl.querySelector(
							'.pc-pin[data-id="' + activePinId + '"]'
						);
						if (pinEl) {
							openThread(activePinId, pinEl);
						}
					}
				},
				uploader ? { files: uploader.files() } : null
			);
		});

		actions.appendChild(sendBtn);
		form.appendChild(textarea);
		if (uploader) {
			form.appendChild(uploader.el);
		}
		form.appendChild(actions);

		// Submit on Ctrl+Enter.
		textarea.addEventListener("keydown", function (e) {
			if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
				e.preventDefault();
				sendBtn.click();
			}
		});

		return form;
	}

	// ===== Delete Comment =====

	function handleDelete(comment) {
		if (!confirm(i18n.confirmDelete || "Delete this comment and all replies?"))
			return;

		ajax(
			"pc_delete_comment",
			{
				comment_id: comment.id,
			},
			function (err, res) {
				if (err || !res || !res.success) {
					alert(i18n.error || "Something went wrong.");
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
						var pinEl = contentEl.querySelector(
							'.pc-pin[data-id="' + activePinId + '"]'
						);
						if (pinEl) {
							openThread(activePinId, pinEl);
						}
					}
				}
			}
		);
	}

	// ===== New Comment (click on content) =====

	function handleContentClick(e) {
		if (!modeActive) return;
		if (
			e.target.closest(".pc-bubble") ||
			e.target.closest(".pc-pin") ||
			e.target.closest("#pc-toggle-bar")
		)
			return;

		if (!isLoggedIn) {
			alert(i18n.loginToComment || "Log in to leave comments.");
			return;
		}

		e.preventDefault();
		e.stopPropagation();

		var rect = pinLayer.getBoundingClientRect();
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
		var pin = document.createElement("div");
		pin.className = "pc-pin pc-pin-new";
		pin.style.left = x + "%";
		pin.style.top = y + "%";
		var dot = document.createElement("div");
		dot.className = "pc-pin-dot";
		pin.appendChild(dot);
		pinLayer.appendChild(pin);

		// Create bubble with new comment form.
		var bubble = document.createElement("div");
		bubble.className = "pc-bubble";

		var bubbleWidth = bubbleSize();
		var left = pin.offsetLeft + 24;
		var top = pin.offsetTop - 10;
		if (left + bubbleWidth > pinLayer.offsetWidth) {
			left = pin.offsetLeft - bubbleWidth - 24;
		}
		if (left < 0) left = 8;
		if (top < 0) top = 8;

		bubble.style.left = left + "px";
		bubble.style.top = top + "px";

		// Header.
		var header = document.createElement("div");
		header.className = "pc-bubble-header";
		var title = document.createElement("span");
		title.className = "pc-bubble-title";
		title.textContent = i18n.comment || "Comment";
		var closeBtn = document.createElement("button");
		closeBtn.className = "pc-bubble-close";
		closeBtn.innerHTML = "&times;";
		closeBtn.addEventListener("click", function (e) {
			e.stopPropagation();
			closeNewCommentForm(pin, bubble);
		});
		header.appendChild(title);
		header.appendChild(closeBtn);
		bubble.appendChild(header);

		// Form.
		var form = document.createElement("div");
		form.className = "pc-bubble-form";

		var textarea = document.createElement("textarea");
		textarea.placeholder = i18n.placeholder || "Write a comment...";

		var uploader = canUpload ? createUploader() : null;

		var actions = document.createElement("div");
		actions.className = "pc-bubble-form-actions";

		var sendBtn = document.createElement("button");
		sendBtn.className = "pc-btn pc-btn-primary";
		sendBtn.textContent = i18n.send || "Send";

		sendBtn.addEventListener("click", function (e) {
			e.stopPropagation();
			var val = textarea.value.trim();
			if (!val && !(uploader && uploader.count())) return;
			sendBtn.disabled = true;
			ajax(
				"pc_create_comment",
				{
					post_id: postId,
					content: val,
					parent_id: 0,
					x_position: x,
					y_position: y,
					viewport: viewport,
				},
				function (err, res) {
					sendBtn.disabled = false;
					if (err || !res || !res.success) {
						alert(
							(res && res.data && res.data.message) ||
								i18n.error ||
								"Something went wrong."
						);
						return;
					}
					comments.push(res.data.comment);
					if (uploader) uploader.reset();
					closeNewCommentForm(pin, bubble);
					renderPins();
				},
				uploader ? { files: uploader.files() } : null
			);
		});

		actions.appendChild(sendBtn);
		form.appendChild(textarea);
		if (uploader) {
			form.appendChild(uploader.el);
		}
		form.appendChild(actions);
		bubble.appendChild(form);

		// Stop propagation.
		bubble.addEventListener("click", function (e) {
			e.stopPropagation();
		});

		pinLayer.appendChild(bubble);
		activeBubble = bubble;

		textarea.focus();

		// Ctrl+Enter to submit.
		textarea.addEventListener("keydown", function (e) {
			if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
				e.preventDefault();
				sendBtn.click();
			}
		});

		// Close on outside click.
		setTimeout(function () {
			document.addEventListener("click", function newCommentOutside(e) {
				if (bubble && !bubble.contains(e.target) && !pin.contains(e.target)) {
					closeNewCommentForm(pin, bubble);
					document.removeEventListener("click", newCommentOutside);
				}
			});
		}, 0);
	}

	function closeNewCommentForm(pin, bubble) {
		if (pin) pin.remove();
		if (bubble) bubble.remove();
		if (activeBubble === bubble) activeBubble = null;
	}

	// ===== Pin Layer (inside the frame) =====

	function createPinLayer() {
		contentEl = findContentEl();
		if (!contentEl) return;

		// Transparent layer spanning the content element. Inside the frame the
		// viewport itself is fixed to the wrapper width, so percentage based pin
		// coordinates always resolve to the exact same spot.
		pinLayer = document.createElement("div");
		pinLayer.className = "pc-pin-layer";
		contentEl.appendChild(pinLayer);
		pinLayer.addEventListener("click", handleContentClick);
	}

	function destroyPinLayer() {
		if (pinLayer) {
			pinLayer.removeEventListener("click", handleContentClick);
			pinLayer.remove();
			pinLayer = null;
		}
		contentEl = null;
	}

	function scrollToRatio(ratio) {
		if (!ratio) return;
		var max = Math.max(
			0,
			document.documentElement.scrollHeight - window.innerHeight
		);
		window.scrollTo(0, Math.round(ratio * max));
	}

	// ===== Frame Role =====

	function activateFrame(nextViewport, scrollRatio) {
		viewport = nextViewport === "mobile" ? "mobile" : "desktop";
		modeActive = true;
		document.body.classList.add("pc-mode-active");
		closeBubble();
		comments = [];
		destroyPinLayer();
		createPinLayer();
		if (pinLayer) {
			loadComments();
		}
		scrollToRatio(scrollRatio);
	}

	function initFrame() {
		document.documentElement.classList.add("pc-frame-root");
		document.body.classList.add("pc-frame");

		window.addEventListener("message", function (e) {
			if (e.origin !== ORIGIN) return;
			var msg = e.data;
			if (!msg || "object" !== typeof msg) return;

			if ("pc:activate" === msg.type) {
				activateFrame(msg.viewport, msg.scrollRatio);
			} else if ("pc:viewport" === msg.type) {
				// Width change happens on the host; only the data set changes here.
				activateFrame(msg.viewport, 0);
			}
		});

		// Keep the frame on the commented document.
		document.addEventListener(
			"click",
			function (e) {
				var link = e.target.closest("a");
				if (link && !link.closest(".pc-bubble")) {
					e.preventDefault();
				}
			},
			true
		);

		document.addEventListener("keydown", function (e) {
			if ("Escape" !== e.key) return;
			if (activeBubble) {
				closeBubble();
			} else {
				postToHost({ type: "pc:exit" });
			}
		});

		postToHost({ type: "pc:ready" });
	}

	function postToHost(msg) {
		if (window.parent && window.parent !== window) {
			window.parent.postMessage(msg, ORIGIN);
		}
	}

	// ===== Host Role: fixed-width stage =====

	var stage = null;
	var stageInner = null;
	var stageFrame = null;
	var frameReady = false;
	var frameTimer = null;
	var hostScrollTop = 0;

	function frameUrl() {
		var url = window.location.href.split("#")[0];
		url += (url.indexOf("?") === -1 ? "?" : "&") + "pc_frame=1";
		return url;
	}

	function hostScrollRatio() {
		var max = Math.max(
			1,
			document.documentElement.scrollHeight - window.innerHeight
		);
		return Math.min(1, window.pageYOffset / max);
	}

	function applyStageWidth() {
		if (stageInner) {
			stageInner.style.width = viewportWidth() + "px";
		}
	}

	function postToFrame(msg) {
		if (stageFrame && stageFrame.contentWindow) {
			stageFrame.contentWindow.postMessage(msg, ORIGIN);
		}
	}

	function openStage() {
		if (!stage) return;
		applyStageWidth();
		stage.setAttribute("aria-hidden", "false");
		stage.dataset.scrollRatio = hostScrollRatio();

		// Only the frame scrolls from here on.
		hostScrollTop = window.pageYOffset;
		document.documentElement.classList.add("pc-mode-lock");

		frameReady = false;
		stageFrame.src = frameUrl();

		// A security plugin denying framing would leave us with a blank stage.
		window.clearTimeout(frameTimer);
		frameTimer = window.setTimeout(function () {
			if (modeActive && !frameReady) {
				alert(
					i18n.frameError || "The page could not be loaded in comment mode."
				);
				toggleMode();
			}
		}, 8000);
	}

	function closeStage() {
		if (!stage) return;
		window.clearTimeout(frameTimer);
		frameReady = false;
		stage.setAttribute("aria-hidden", "true");
		stageFrame.removeAttribute("src");
		document.documentElement.classList.remove("pc-mode-lock");
		window.scrollTo(0, hostScrollTop);
	}

	// ===== Viewport Switch (desktop / mobile) =====

	function updateViewportButtons() {
		var buttons = document.querySelectorAll(".pc-viewport-btn");
		for (var i = 0; i < buttons.length; i++) {
			var isActive = buttons[i].dataset.viewport === viewport;
			buttons[i].classList.toggle("is-active", isActive);
			buttons[i].setAttribute("aria-pressed", isActive ? "true" : "false");
		}
		document.body.classList.toggle("pc-viewport-mobile", viewport === "mobile");
	}

	function setViewport(value) {
		if (value !== "desktop" && value !== "mobile") return;
		if (value === viewport) return;

		viewport = value;
		storeViewport(viewport);
		updateViewportButtons();

		if (!modeActive) return;

		// Resizing the frame changes its viewport, so the theme re-runs its media
		// queries. The frame then swaps to the comments of that viewport.
		applyStageWidth();
		postToFrame({ type: "pc:viewport", viewport: viewport });
	}

	// ===== Toggle Comment Mode =====

	function toggleMode() {
		modeActive = !modeActive;
		var btn = document.getElementById("pc-toggle-btn");
		var label = btn.querySelector(".pc-toggle-label");

		if (modeActive) {
			document.body.classList.add("pc-mode-active");
			btn.setAttribute("aria-pressed", "true");
			label.textContent = i18n.exitCommentMode || "Exit Comment Mode";
			openStage();
		} else {
			document.body.classList.remove("pc-mode-active");
			btn.setAttribute("aria-pressed", "false");
			label.textContent = i18n.commentMode || "Comment Mode";
			closeStage();
		}
	}

	function initHost() {
		var btn = document.getElementById("pc-toggle-btn");
		if (!btn) return;

		stage = document.getElementById("pc-stage");
		stageInner = document.getElementById("pc-stage-inner");
		stageFrame = document.getElementById("pc-stage-frame");

		btn.addEventListener("click", function (e) {
			e.preventDefault();
			toggleMode();
		});

		updateViewportButtons();

		var viewportButtons = document.querySelectorAll(".pc-viewport-btn");
		for (var i = 0; i < viewportButtons.length; i++) {
			viewportButtons[i].addEventListener("click", function (e) {
				e.preventDefault();
				setViewport(this.dataset.viewport);
			});
		}

		window.addEventListener("message", function (e) {
			if (e.origin !== ORIGIN) return;
			var msg = e.data;
			if (!msg || "object" !== typeof msg) return;

			if ("pc:ready" === msg.type) {
				frameReady = true;
				window.clearTimeout(frameTimer);
				// Hand over keyboard scrolling (arrows, space, page up/down).
				stageFrame.focus();
				postToFrame({
					type: "pc:activate",
					viewport: viewport,
					scrollRatio: parseFloat(stage.dataset.scrollRatio) || 0,
				});
			} else if ("pc:exit" === msg.type && modeActive) {
				toggleMode();
			}
		});

		document.addEventListener("keydown", function (e) {
			if ("Escape" === e.key && modeActive) {
				toggleMode();
			}
		});
	}

	// ===== Init =====

	function init() {
		if (isFrame) {
			initFrame();
		} else {
			initHost();
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
