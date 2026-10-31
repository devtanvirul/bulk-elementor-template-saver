# Bulk Elementor Template Saver

A WordPress plugin that lists every page, post, header, and footer built with Elementor and saves the ones you tick as templates in one click — no more opening each one and clicking "Save as Template" by hand.

## Why

Elementor's "Save as Template" has to be done one page at a time, from inside the editor, for every single design — pages, headers, footers, popups. For a site with a lot of Elementor-built content, that's a lot of repetitive clicking just to get everything into the Template Library.

This plugin does the same thing Elementor's own "Save as Template" does — duplicate the design's `_elementor_data` into a new `elementor_library` entry — but for as many items as you tick, in one pass.

## What it does

- Lists every page and post built with Elementor on your site in one screen, with the amount of Elementor data each one holds
- Lets you tick what to save and choose a type for each (Page, Section, Header, Footer, Popup, Single Post, Archive) — guesses the type from the title where it can (anything with "header," "footer," or "popup" in the name)
- Copies the Elementor data into a new (or existing) Template Library entry and verifies, character for character, that the copy matches the source exactly
- Saving the same row again updates the template it made before instead of creating a duplicate

## Requirements

- WordPress
- [Elementor](https://wordpress.org/plugins/elementor/)

## Installation

1. Download this repository as a ZIP (`Code` → `Download ZIP`), or clone it.
2. In WordPress: **Plugins → Add New → Upload Plugin**, and upload the ZIP. (If you cloned the repo, zip the folder first, or copy it directly into `wp-content/plugins/`.)
3. Activate **Bulk Elementor Template Saver**.
4. Find it under **Templates → Bulk Template Saver** (or **Tools → Bulk Template Saver** if your site doesn't expose the Elementor Library menu).

## Usage

1. Tick the pages/posts you want saved as templates.
2. Pick a type for each from the dropdown (Page, Header, Footer, Section, Popup, Single Post, Archive).
3. Click **Save selected as Templates**.
4. Check the **Template status** column — "exact match" means the copy is verified byte-for-byte against the source.

## A note on the byte-for-byte check

`update_post_meta()` runs `wp_unslash()` on the value it's given, which silently strips a layer of backslashes out of Elementor's JSON data — shortening URLs and, worse, corrupting any text that contains a quote mark. Elementor's own code works around this with `wp_slash()` before saving. This plugin does the same, and then re-reads what it wrote to confirm the save landed intact rather than assuming it did.

## Related

[Bulk Template Kit Importer](https://github.com/devtanvirul/bulk-template-kit-importer) — the companion plugin that copies these saved templates into Envato's Template Kit Export library, for ThemeForest authors packaging a kit.

## License

GPL-2.0-or-later

## Author

Built by [devtanvirul](https://devtanvirul.com).
