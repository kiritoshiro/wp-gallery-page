# Page Gallery Grid

WordPress plugin for a gallery of selected pages: one big featured page with a side panel (YouTube playlist on top, latest posts below), then the other pages in a grid. Put the shortcode `[page_gallery]` on any page.

Requires WordPress 6.4+ and PHP 7.4+. Lithuanian translation included (WordPress 6.5+).

## Settings

**Page Gallery** has its own item in the wp-admin menu (below Pages). The screen has three parts and a save bar that stays at the bottom of the window:

1. **Pages in the gallery.** Two boxes that scroll on their own, so a long page list never pushes the other settings down.
   - *All pages* (newest first, with search): tick a page to add it.
   - *In the gallery*: drag to reorder, click the star to choose the big featured page (no star = the newest page), pick a custom image or reset to the page's Featured Image, or remove the page. Drafts stay in the list with a badge and appear once published.
2. **Side panel.** YouTube playlist (an ID like `PL…` or a pasted link containing `list=`) and its heading; latest posts on/off, heading, category and number.
3. **Layout.** Grid heading, columns, gap, gallery width, featured image width and image shape (width × height).

An empty heading field hides that heading.

## Shortcode options

Each overrides the saved setting for one gallery:

| Attribute | Example |
| --- | --- |
| `columns`, `gap` | `columns="3" gap="20"` |
| `max_width`, `big_max` | `max_width="1100" big_max="560"` (px) |
| `ratio_w`, `ratio_h` | `ratio_w="3" ratio_h="4"` |
| `ids` | `ids="12,34,56"` (other pages, in this order) |
| `big_id` | `big_id="123"` (other featured page) |
| `category` | `category="7"` (posts category ID) |
| `playlist`, `show_yt` | `playlist="PL…"`, `show_yt="0"` hides the video |

Image for each card: the custom image, else the page's Featured Image, else a grey "No image" box.

## Upgrading from the 2.x single file (`page-gallery.php`)

The settings are stored in the same option (`pg_gallery_settings`), so nothing has to be entered again. Install this plugin, activate it, then deactivate and delete the old one; a notice reminds you while both are active. Settings moved from Settings → Page Gallery to the **Page Gallery** menu item.

Changes in behaviour since 2.4.0:

- The video panel is above the latest posts, and loads from `youtube-nocookie.com`.
- Only published pages are shown; a draft chosen as the featured page no longer appears publicly.
- Sticky posts no longer jump to the top of the latest posts.
- The stylesheet is a cached file instead of inline CSS repeated in every page.

## Updates

The plugin updates from this repository's GitHub releases (Dashboard → Updates). It accepts only the release ZIP whose SHA-256 matches GitHub's digest.

## Development

See [docs/ci-checks.md](docs/ci-checks.md) for the checks and how to run them locally. Releases: tag `vX.Y.Z` on `main` matching the plugin header; the release workflow runs every check, builds `wp-gallery-page-X.Y.Z.zip` and publishes it.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
