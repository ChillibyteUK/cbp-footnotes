# CB Footnotes

A WordPress plugin that turns `[Footnote]...[/Footnote]` tags in your content into numbered, linked footnotes with a backlink, plus an auto-generated footnote list.

Ported from the footnotes functionality built into the `cb-arcus2025` theme, generalised into a standalone plugin so any site can use it. Simplified to use a single running counter for the whole page, rather than the theme version's per-section counters.

---

## Usage

Wrap any text in a post/page (or ACF field processed via the helper below) with the tag:

```
Some claim that needs a source.[Footnote]This is the footnote text, shown in the list at the bottom of the page.[/Footnote]
```

This is replaced with a numbered bracketed link, e.g. `Some claim that needs a source.[1]`, and the footnote text is collected for display in a list.

### Displaying the list

- By default, the footnote list is appended directly to the end of the content it was collected from (i.e. the end of the main post/page content), with minimal default styling.
- To place the list somewhere else within that same content instead (e.g. above a closing CTA block), add the `[cbp_footnotes]` shortcode wherever you want the `<ol>` of footnotes rendered. When present, the automatic end-of-content output is skipped in favour of the shortcode's position.

### Processing content outside `the_content`

The plugin hooks `the_content` automatically. For other content sources (ACF fields, custom blocks, etc.), call the helper function directly:

```php
$content = cbp_footnotes_process( get_field( 'my_field' ) );
```

---

## Styling

The footnote list is wrapped in `<div class="cbp-footnotes-box">`, styled by `assets/cbp-footnotes.css` as a simple bordered box (light border, rounded corners, padding). The wrapper carries no width-constraint class — it inherits its width from the surrounding content column. There's no settings page for this — override it per site instead:

- **Swap the wrapper class** — return your own class name(s) from the `cbp_footnotes_wrapper_class` filter (e.g. if a theme's width-constraint class isn't called `container`), then style that class in your theme:
  ```php
  add_filter( 'cbp_footnotes_wrapper_class', fn() => 'my-theme-footnotes' );
  ```
- **Override the CSS directly** — target `.cbp-footnotes-box` (and `.cbp-footnotes-list`, `.cbp-footnote-backlink`) in the theme's own stylesheet; it loads after the plugin's.
- **Drop the default styling entirely** — return `false` from `cbp_footnotes_enqueue_styles` to skip enqueuing `cbp-footnotes.css` altogether:
  ```php
  add_filter( 'cbp_footnotes_enqueue_styles', '__return_false' );
  ```

---

## Features

- `[Footnote]...[/Footnote]` tags converted to bracketed links + backlinked list entries
- Single running counter per page request — no section/scope configuration needed
- Only main-loop content feeds the counter: `the_content` runs outside the main loop (SEO/schema/TOC pre-passes, related-post loops) are left untouched, so a discarded pre-pass can neither consume numbers nor trigger the list early. Symptom this prevents: visible footnotes starting at [4] with no list in the DOM.
- Anchor targets carry `scroll-margin-top` derived from `--nav-height` (or `--h-top`), so jumps land below fixed headers
- Footnote list auto-appended to the end of the main content by default, wrapped in a bordered box
- `[cbp_footnotes]` shortcode for placing the list elsewhere within that same content
- `cbp_footnotes_process()` helper for processing content outside the main loop
- Styling overridable via filters (`cbp_footnotes_wrapper_class`, `cbp_footnotes_enqueue_styles`) — no settings page or database options

---

## Installation

1. Upload or clone the `cbp-footnotes` folder into `wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. Add `[Footnote]...[/Footnote]` tags to your content

---

## File Structure

```
cbp-footnotes/
├── cbp-footnotes.php         # Main plugin file — class, hooks, shortcode, helper
├── assets/
│   └── cbp-footnotes.css     # Default bordered-box styling for the footnote list
└── README.md
```

---

## Requirements

- WordPress 6.0+
- PHP 7.4+

---

## Development

Follows the same conventions as other `cbp-*` plugins in this project:

- Single class, instantiated once via `$GLOBALS`
- No admin UI or options — intentionally minimal (MVP)
