# Pinned Comments

Figma-style pinned comments for WordPress posts and pages.

## Description

Pinned Comments lets users enter a "Comment Mode" on any post or page, click anywhere on the content, and drop a pin with a comment thread — just like Figma's commenting system.

### Features

- **Comment Mode toggle** — Fixed button (bottom-right) to enter/exit comment mode on any singular post or page.
- **Pinned comments** — Click on the content to drop a pin at that exact position. Comments are stored with percentage-based coordinates so they stay in the right spot across screen sizes.
- **Threaded replies** — Any logged-in user can reply to an existing comment thread.
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
4. Type your comment and hit **Send** (or Ctrl/Cmd+Enter).
5. Click any existing pin to view the thread, reply, or edit/delete your own comments.
6. Press **ESC** to close a thread or exit comment mode.

## Settings

**Settings → Pinned Comments**

- View total comment count.
- Delete all pinned comments across all posts and pages.

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
