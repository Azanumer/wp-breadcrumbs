# Breadcrumbs

A dependency-free WordPress plugin: SEO breadcrumbs with JSON-LD structured data, for posts, pages, categories, tags, authors, search results and archives.

## Features

- Full hierarchy — parent categories/terms included, not just the immediate one
- BreadcrumbList JSON-LD emitted alongside the visible trail (Google-friendly)
- Auto-inserts at the top of single content, or place manually with `[breadcrumbs]`
- Settings → Breadcrumbs: separator, home label, home-crumb toggle, auto-insert toggle
- Filters: `wbcr_trail` (modify the trail), `wbcr_primary_category` (pick the category shown)

## Install

Upload the plugin folder to `wp-content/plugins/` and activate it.

## Uninstall

Removes the single `wbcr_settings` option. Nothing is written into posts.

## License

MIT
