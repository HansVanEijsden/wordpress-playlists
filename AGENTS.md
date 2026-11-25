# AGENTS.md

WordPress Playlists — a production WordPress plugin that automatically builds daily radio playlists for the **Radio Station** plugin and presents them as a public airplay archive. One station per site, all configuration in the WordPress admin. Public repo: https://github.com/HansVanEijsden/wordpress-playlists — sites deploy by `git pull` into `wp-content/plugins/wordpress-radio-station-playlists`.

## Project language

Everything is in **English**: code, comments, docs, this file. Site-facing strings are English source strings translated through the `wordpress-playlists` text domain; Dutch ships in `languages/`. The only other localised site-facing values are the playlist comment column label (admin-configurable) and the playlist titles, which use `date_i18n()` and therefore follow the site locale (a Radio Station data contract).

No station name, logo or domain may appear anywhere in the repository: no header, class, option, handle, filename, CSS comment or text domain. The plugin must run identically on any station.

## Layout

| Path | Purpose |
|---|---|
| `wordpress-playlists.php` | Plugin bootstrap + header (author: Hans van Eijsden Consultancy). |
| `includes/class-settings.php` | Admin settings page (Settings → WordPress Playlists), display settings, sanitization and index status. |
| `includes/class-station.php` | Immutable value object for this site's single station (typed, sanitized). |
| `includes/class-rest-controller.php` | Registers `/wp-json/<namespace>/playlist/track` + auth. |
| `includes/class-metadata-client.php` | Fetches current show from the station's metadata API. |
| `includes/class-playlist-manager.php` | Finds/creates today's playlist, appends tracks (Radio Station contract), records the play in the artist index. |
| `includes/class-logger.php` | `error_log` helper, prefix `[WordPress Playlists][<station>]`. |
| `includes/class-gettext.php` | Renames Radio Station `Comments`/`Comment` → configured label (default `Start Time`). |
| `includes/class-taxonomy.php` | Registers the public `playlist_artist` taxonomy, editorial term meta and the clean-playlist-URL post type args. |
| `includes/class-artist-index.php` | Normalisation, artist term resolution, play counters, per-artist track summaries, playlist summaries, incremental sync, diff resync, rebuild and status. |
| `includes/class-context.php` | Settings/URL/route helpers and the cached month and artist-letter structures. |
| `includes/class-palette.php` | Pure colour maths: accent validation, AA-safe `accent-ink`, `on-accent`, tints. |
| `includes/class-renderer.php` | Pure formatting helpers: times, durations, hours, monograms, plurals, excerpts. |
| `includes/class-components.php` | Pure shared HTML fragments: breadcrumb, playlist row, day heading, section head, avatar, badge, empty state, icons, pagination, copy link. |
| `includes/class-playlist-model.php` | The small playlist card model used by hub and month pages. |
| `includes/class-cache.php` | Cache versions, snapshots and full-page cache purging. |
| `includes/class-playlist-view.php` | Replaces Radio Station's playlist article content with the airplay sheet. |
| `includes/class-hub-view.php` | The `[wordpress_playlists]` hub. |
| `includes/class-month-view.php` | Month archive pages. |
| `includes/class-artist-view.php` | Artist pages, A–Z index, letter pages, search results and the embeddable card. |
| `includes/class-search.php` | Artist-name and track-title search (term meta, never `WP_Query`'s `s`). |
| `includes/class-archive-client.php` | Matches a playlist to Broadcast Archive episodes for the shared player. |
| `includes/class-assets.php` | Enqueues the display stylesheet/script, inline accent variables and the term-field script. |
| `includes/class-routes.php` | Rewrite rules, query vars, templates, legacy redirects and the deferred rewrite flush. |
| `includes/class-seo.php` | Yoast/core titles, descriptions, canonicals, robots, breadcrumbs, JSON-LD and the month sitemap. |
| `includes/class-shortcodes.php` | Registers the hub and artist-card shortcodes and enqueues assets early. |
| `includes/class-term-fields.php` | Artist photo, biography, links and local flag on the taxonomy screens; admin columns. |
| `includes/class-cli.php` | `wp wppl rebuild`, `status` and `recount`. |
| `templates/` | Month, artist archive and artist-index templates (header + main + footer). |
| `assets/css/playlist-display.css` | The public presentation styles. |
| `assets/js/playlist-display.js` | Now marker, copy-link, Broadcast Archive hand-off. |
| `assets/js/settings.js`, `assets/js/term-fields.js` | Admin media pickers / copy button. |
| `languages/` | `.pot`, Dutch `.po`/`.mo`. |
| `tools/merge-duplicate-playlists.php`, `tools/move-tracks-between-playlists.php` | CLI-only maintenance tools (dry-run by default). |
| `tools/make-pot.py` | Regenerates the translation template. |
| `tests/` | Dependency-free renderer/logic tests; also a class-load smoke test. |
| `preview/` | Development-only preview harness with realistic fixtures and WordPress stubs. |
| `PRODUCT.md`, `DESIGN.md`, `.impeccable/` | Product truth, the design system and the surface brief. |
| `uninstall.php`, `readme.txt`, `README.md`, `LICENSE`, `.editorconfig`, `.github/workflows/ci.yml` | Housekeeping / distribution / CI. |

All classes live in the `WordPressPlaylists\` namespace.

## Model

One WordPress site = one radio station (that is how the Radio Station plugin works). The plugin stores a single station config in the `wordpress_playlists_settings` option; on multisite each subsite has its own per-site option and configures its own station and presentation. Do not reintroduce multi-station support.

### Source of truth and derived data

The `playlist` post meta written by the ingest is the **only** source of truth. Everything public is derived and must be rebuildable with `wp wppl rebuild`:

- **Artist identity** — the public `playlist_artist` taxonomy. Term meta: `_wppl_key` (normalised name), `_wppl_play_count`, `_wppl_tracks` (`track key => [title, plays]`), `_wppl_first_played`, `_wppl_last_played`, plus editorial `_wppl_image_id`, `_wppl_bio`, `_wppl_local`, `_wppl_website`, `_wppl_instagram`, `_wppl_spotify`, `_wppl_youtube`.
- **Normalisation** — `Artist_Index::normalize_key()` removes accents and punctuation, lowercases and collapses whitespace. Artist key = normalised name; track key = normalised title within the artist. Terms are resolved by the `_wppl_key` term meta, never by display name, so editorial renames are safe.
- **Per playlist** — `_wppl_summary` (count, first/last start time, three preview tracks, artist count) and `_wppl_index` (`term_id => [track key => plays]`) for exact incremental updates.
- **Counters** — `record_track()` runs after each successful append and before the post is touched; `save_post_playlist` does a full diff resync; `before_delete_post` subtracts a deleted playlist. `wp wppl rebuild` recomputes everything and is idempotent.
- **Cache** — `wordpress_playlists_cache_version` is bumped on every derived write; snapshots use it. `Context` additionally keeps the month list and artist-letter groups in explicitly invalidated transients.

### Public presentation invariants

- Playlist pages keep the host theme's shell (title, breadcrumb, sidebar, advertising). Only `the_content` is replaced, and only for the main singular playlist in the main loop.
- One canonical page per artist; tracks are sections on it, not separate thin pages. No numeric pagination anywhere: the hub → month → playlist hierarchy carries the archive.
- The presentation is station-agnostic: inherited typeface and text colour, neutral surfaces, one station accent. Accent companions are derived with `Palette` and must stay AA on white and on the 9 % tint.
- All public HTML is server-rendered. JavaScript may only enhance (now marker, copy link, shared player); the page must be complete without it.
- The `playlist_artist` taxonomy rewrite base is `artiest`, the index base `artiesten`, both setting-configurable. Month pages live under the hub page path.
- Legacy URLs are permanently redirected: Radio Station's playlist archive and `?offset=` pagination to the hub; with `clean_playlist_urls`, the site's front path is removed from playlist permalinks and old paths 301 to the new one.

### SEO

- Yoast filters are used when Yoast is active (`wpseo_title`, `wpseo_metadesc`, `wpseo_canonical`, `wpseo_robots`, `wpseo_opengraph_*`, `wpseo_breadcrumb_links`, `wpseo_schema_graph`, `wpseo_sitemap_index_links`); a plain-WordPress fallback prints the head tags and JSON-LD. Always respect a custom Yoast title/description.
- Structured data: `MusicPlaylist` on playlists, `MusicGroup` + `ItemList` on artists, `ItemList` on months. Do not emit a second WebPage or BreadcrumbList node.
- Search result pages and `?q=` are `noindex` and canonicalise to the artist index. Artist and month pages are indexable; do not add a thin-content noindex without data to support it.
- Month, letter and index URLs are added to a small month sitemap linked from Yoast's sitemap index.

## Security rules (non-negotiable)

- **No secrets in source, ever.** The API key lives only in the `wordpress_playlists_settings` option, entered on the settings page, never echoed, displayed, or logged.
- Auth (`Rest_Controller::check_permission`) accepts ONE of: `X-API-Key` header, `Authorization: Bearer <key>` (what the metadata hub sends), or `?secret=<key>` query param — compared with `hash_equals` (constant-time).
- A REST route is only registered when the station is enabled AND fully configured including a non-empty API key (`Station::is_ready()`).
- The `?secret=` path means the key can appear in URLs/access logs — keep the feature (some integrators rely on it) but be aware when reviewing logs.
- An empty API-key field on save keeps the existing key (`Settings::sanitize_api_key`), so saving other fields can never wipe a secret.
- Escape every public value (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`); JSON-LD is encoded with `wp_json_encode` and the HEX flags. Search input is sanitized; the track search SQL uses `$wpdb->prepare` + `esc_like`. Term fields use a nonce and `manage_categories`.

## How it works

1. A metadata hub `POST`s a track to `/wp-json/<namespace>/playlist/track`. The namespace is the admin-configurable path segment between `/wp-json/` and `/playlist/track` (e.g. `mysite/v1`) and must match what the hub posts to.
2. Auth against the configured key; track validated (artist + title required).
3. Duration: optional; `< 120s` rejected (HTTP 422); empty or unparseable ACCEPTED with a warning log — don't change that into a hard error without asking.
4. Current show fetched from the metadata API — shape `body.broadcast.current_show.show.{id,name,slug,avatar_id}`. The endpoint auto-detects to `<site>/wp-json/metadata/v1/current` when the settings field is left blank (enter a custom URL to override).
5. Finds or creates today's playlist post, then appends the track to its `playlist` post meta.
6. Records the play in the artist index (term, counters, track summary, playlist summary) and purges the affected public pages.
7. Public views read the playlist meta for a single playlist and the derived summaries for the hub, month and artist pages. Broadcast Archive episodes are fetched server-side and matched by date and programme.

### Radio Station data contract (do not change)

- Post type: `playlist`; linked to show via meta `playlist_show_id` (NUMERIC).
- Playlist title: `date_i18n('j F Y') - <show name>` (site-locale date, e.g. `3 augustus 2026 - Morning Show`). Today's-playlist lookup (`Playlist_Manager::get_todays_playlist`) matches playlists whose title starts with today's date via a direct `post_title LIKE '<date>%'` query, then picks the newest whose title also contains the current show name (`stripos`). If the API returns a show name but no today's playlist title contains it (a brand-new show), a NEW playlist is created — tracks are never appended to a different show's playlist (the pre-1.0.2 fallback appended to the newest same-day playlist, misrouting e.g. "Avond aan de IJssel" on Salland1 on 2026-08-03 23:00). This deliberately bypasses `WP_Query`'s fuzzy `'s'` search because search-rewriting plugins (e.g. a fulltext-search mu-plugin) broke it on 2026-08-03 (one playlist per track on 1Zwolle and Salland1). It also matches the show by name in the title rather than `playlist_show_id` meta, since a metadata hub's show ID may not equal the stored Radio Station show ID. Keep the title format in sync with live data.
- Tracks in meta `playlist`, 1-based keyed array; each entry: `playlist_entry_artist`, `playlist_entry_song`, `playlist_entry_album`, `playlist_entry_label`, `playlist_entry_minutes`, `playlist_entry_seconds`, `playlist_entry_comments` (= start time `H:i`), `playlist_entry_status` (`played`).
- Duration accepted as `MM:SS` or `MM:SS.mmm`; stored as separate minutes/seconds fields.
- Featured image: use the API `avatar_id` only if it exists as an attachment, else the configured fallback ID. **Never download images** — `set_post_thumbnail` with existing attachment IDs only.
- The public layer may read this meta; it must never write a different structure back.

## Conventions

- WordPress Coding Standards style: spaces, long `array()` syntax, single quotes, `function ()`.
- All plugin strings use the `wordpress-playlists` text domain and `__()`/`_e()`/`esc_html__()`/`_n()`. Plural forms go through `_n()` with raw pattern strings (see `Renderer::count_label`); never pre-translate both forms and pass them to `_n()`.
- PHP 7.4+ (typed properties OK, no PHP 8-only syntax). No composer/build step — plain `require_once` bootstrap; deploys by `git pull`.
- Public CSS is scoped to `.wppl` and uses the shared family tokens (ink/line/surface/accent) documented in `DESIGN.md`. No external fonts or scripts.
- The comment column label is admin-configurable (default `Start Time`); the `gettext` filter must respect an empty label (no rename).

## Validation

- CI (`.github/workflows/ci.yml`) runs `php -l` over every PHP file and `php tests/run.php` on PHP 7.4 and 8.2 on every push to `main` and every pull request.
- Locally: `for f in $(find . -name '*.php'); do php -l "$f"; done` and `php tests/run.php` (PHP available on this Mac).
- Visual/UI work: render with the preview harness (`php -S 127.0.0.1:8099 -t .`, open `/preview/?view=…`) and inspect at 1440 and 390 px for both accents. For frontend changes, run Impeccable's `detect --json --changed` once before handoff.
- Runtime behavior is only verifiable on live sites after `git pull`, the rebuild, and a cache purge. Check at least: a normal playlist, a 385-track Non Stop day, the hub, a month page, an artist page with many plays, a local artist, a search, the old archive redirect and the `?offset=` redirect.

## Deployment (both sites)

1. `git pull` in the plugin directory as the site owner.
2. Restart PHP-FPM / the PHP container after the pull when opcache is enabled; otherwise the old classes can keep serving. Run `wp wppl rebuild --dry-run`, then `wp wppl rebuild` once (in `tmux`; add `--url` on multisite). Check `wp wppl status`.
3. Confirm the display settings (accent, hub page, artist bases) and, on a site whose playlist URLs contained a front path, enable **Clean playlist URLs**. Add any previous hub path (for example `/afspeellijsten-radio`) under **Legacy hub paths**. Set **Listen-back page** explicitly when the archive page uses a legacy shortcode instead of `[broadcast_archive]`.
4. Replace the hub page's Radio Station archive — either the `[playlists-archive]` shortcode or the Gutenberg `radio-station/archive` block — with a shortcode block containing `[wordpress_playlists]` (or `[wordpress_playlists week="3"]` for a compact two-day recent list). Keep a backup of the page content and the editorial intro.
5. Flush the full-page cache (nginx-helper / Redis purger on these hosts) and clear the short-lived nginx sitemap cache if the sitemap changed; verify the public pages, the redirects and the sitemap in a browser.

## Pitfalls

- `get_todays_playlist()` uses a direct `$wpdb` title query (no `WP_Query`, no postdata loop). Do NOT reintroduce a `WP_Query` `'s'` search — search-altering plugins (fulltext mu-plugin) break it and it caused the 2026-08-03 one-playlist-per-track incident. For the same reason, public search uses a prepared term-meta `LIKE`, not `WP_Query`'s `s`.
- A station that is enabled but keyless has NO registered route → the hub gets a 404 until a key is saved. That is intended behavior.
- `date_i18n('j F Y')` respects WP locale/timezone — the title format must stay in sync with what the live sites produce.
- Radio Station must be active (`post_type_exists('playlist')` guard in the REST handler).
- Uninstall is deliberately conservative: it only removes the plugin option; playlist deletion happens only when `delete_playlists_on_uninstall` is explicitly enabled in settings (default off). Artist terms, bios and photos are never deleted. Keep it that way — playlists are valuable content.
- The derived index is not the source of truth. If counters look wrong after manual database work, run `wp wppl rebuild` instead of patching meta by hand.
- Do not add a `WP_Query`-based archive for playlists; the month hierarchy and the redirects are deliberate. A numeric `?offset=` pagination must not come back, and the artist page paginates with newer/older steps and a count, never a wall of page numbers.
- Page caches on these sites may have no TTL. New tracks purge the playlist, artist, hub and month URLs through the detected integrations, but a cache plugin that is not detected needs the `wordpress_playlists_purge_url` action.
- Playlists created before the public layer have no `_wppl_summary`/`_wppl_index` until the first rebuild; the views fall back to the full meta but the rebuild is required for artist pages and counters.
- Some older playlists (about 180 on Salland1) have no valid `playlist_show_id`; the show name falls back to the title suffix and the link is omitted. Never make that fatal.
- A Non Stop day can hold 385 tracks. Keep the hour bands, hour chips and compact rows; do not replace the table with a card grid or load tracks client-side.
