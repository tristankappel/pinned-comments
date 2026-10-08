# Pinned Comments

Figma-style pinned comments for WordPress posts and pages.

## Description

Pinned Comments lets users enter a "Comment Mode" on any post or page, click anywhere on the content, and drop a pin with a comment thread — just like Figma's commenting system.

### Features

- **Comment Mode toggle** — Fixed button (bottom-right) to enter/exit comment mode on any singular post or page.
- **Pinned comments** — Click on the content to drop a pin at that exact position. Comments are stored with percentage-based coordinates so they stay in the right spot across screen sizes.
- **Fixed-width stage** — Comment Mode renders the page inside a same-origin frame whose viewport is locked to the configured wrapper width. The theme's media queries evaluate against that width, so the layout — and every pin position — is pixel-identical on any screen. Screens narrower than the wrapper scroll instead of shrinking it.
- **Desktop & mobile viewports** — A switch next to the Comment Mode button toggles between the desktop and the mobile variant. Switching resizes the frame viewport (real mobile layout) and loads that viewport's comments. Each viewport has its own wrapper width and its own set of comments; a comment always belongs to exactly one viewport.
- **Threaded replies** — Any logged-in user can reply to an existing comment thread.
- **Image attachments** — Up to 5 images (JPG, PNG, GIF, WebP, max 5 MB each) per comment or reply, with thumbnail previews before sending and a click-to-open grid in the thread. Images can be added and removed while editing a comment. Only users with the `upload_files` capability see the upload button. Files are stored in `wp-content/uploads/pinned-comments/<year>/<month>/` — deliberately outside the media library — and are deleted together with their comment.
- **Edit & delete own comments** — Users can edit or delete their own comments at any time. Deleting a top-level comment also removes all replies.
- **Settings page** — Under Settings → Pinned Comments. Shows total comment count and a "Delete All Comments" button.
- **No external dependencies** — Vanilla JS, system fonts (`-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif`). No jQuery, no CSS frameworks, no external libraries.

## Installation

1. Upload the `pinned-comments` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. The custom database table is created automatically on activation.
4. Visit any post or page — the comment mode toggle appears in the bottom-right corner.

## Usage

1. Navigate to any post or page.
2. Click the **Comment Mode** button (bottom-right).
3. Click anywhere on the content to drop a pin.
4. Type your comment, optionally attach up to 5 images, and hit **Send** (or Ctrl/Cmd+Enter).
5. Click any existing pin to view the thread, reply, or edit/delete your own comments.
6. Use the **Desktop / Mobile** switch to move between the two variants. Only the comments of the selected viewport are shown, and new pins are stored for that viewport. The choice is remembered per browser.
7. Press **ESC** to close a thread or exit comment mode.

## Settings

**Settings → Pinned Comments**

- Set the desktop wrapper width (default 800px) — this is the frame's viewport width.
- Set the mobile wrapper width (default 390px).
- Pick the accent color for pins, buttons, links and focus states (color picker, default `#1a73e8`). The hover shade is derived from it automatically.
- View total comment count, split by desktop and mobile.
- Delete all pinned comments across all posts and pages (including their uploaded images).

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Logged-in users only can create, edit, and delete comments.

## Uninstall

Deleting the plugin via the WordPress admin will drop the custom database table and remove all options.

## Author

**Tristan Kappel** — [https://tristankappel.com](https://tristankappel.com)

## License

GPL-2.0-or-later
