=== WordPress Playlists ===
Contributors: hansvaneijsden
Tags: radio, playlist, radio-station, rest-api, broadcast
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic daily radio playlists for the Radio Station plugin, plus a designed public airplay archive: a hub, month pages, playlist pages and one canonical page per artist, with SEO structured data and optional listen-back through Broadcast Archive.

== Description ==

WordPress Playlists automatically builds daily radio playlists for the [Radio Station](https://wordpress.org/plugins/radio-station/) plugin.

A metadata hub POSTs every played track to the plugin's REST endpoint. The plugin:

* Authenticates the request against an API key configured in the WordPress admin (no secrets in source code).
* Validates the track (artist + title required; optional duration, only tracks shorter than 2 minutes are rejected).
* Fetches the currently broadcasting show from the metadata API.
* Finds or creates today's playlist post for that show.
* Appends the track to the playlist with the exact meta structure Radio Station expects.

= One station per site =

Each WordPress site (or subsite) has one radio station, configured under **Settings → WordPress Playlists**:

* **Enable station** – registers the REST endpoint.
* **Station name** – used in logs and as the playlist author context.
* **REST namespace** – the path segment of the track endpoint between `/wp-json/` and `/playlist/track`; set it to the namespace your metadata hub posts to.
* **Metadata API endpoint** – returns the current show (`broadcast.current_show.show.{id,name,slug,avatar_id}`).
* **API key** – accepted via `X-API-Key` header, `Authorization: Bearer` or `?secret=` query parameter (the metadata hub uses a bearer token). Stored in the options table, never displayed.
* **Fallback image ID** – attachment used for the playlist featured image when the show has no avatar.
* **Post author ID** – user assigned to created playlist posts.

= Public presentation =

Turning **Designed playlists** on replaces Radio Station's bare playlist content with an airplay sheet and adds the public archive. All values are settings, and the plugin stays station-agnostic:

* **Accent colour** - one station colour; an accessible darker text colour and tints are derived at render time.
* **Hub page** - the page containing `[wordpress_playlists]`; month pages live under its path.
* **Artist URLs** - rewrite bases for `/artiest/<slug>/` and the artist index `/artiesten/`.
* **Local artists** - editorial flag, photo, biography and links on the artist screen; flagged artists can appear in the local sections.
* **Listen-back page** - when Broadcast Archive is active, a playlist offers the matching broadcast in its shared player.
* **Clean playlist URLs** - removes a site's front path from playlist permalinks (for example `/nieuws/playlist/`) and permanently redirects the old URLs.

Shortcodes: `[wordpress_playlists]` (hub) and `[wordpress_playlists_artist name="Artist" tracks="3"]` (artist card for articles).

The derived artist index is rebuilt with WP-CLI: `wp wppl rebuild --dry-run`, then `wp wppl rebuild`; `wp wppl status` shows the counts. On multisite add `--url`.

= General settings =

* **Playlist comment column label** – Radio Station's playlist entries have a "Comments" field, which this plugin uses to store each track's start time. Rename that column's header here (default "Start Time"); leave empty to keep Radio Station's wording.
* **Uninstall behavior** – by default, uninstalling only removes this plugin's own settings. Deleting playlists on uninstall is disabled by default and must be enabled explicitly.

= Dependencies =

* Requires the [Radio Station](https://wordpress.org/plugins/radio-station/) plugin to be active.
* Requires PHP 7.4 or newer.

== Installation ==

The recommended way to install is via `git clone` or `git pull` into the WordPress plugins directory, so updates are a simple `git pull`:

1. On the server, inside `wp-content/plugins/`:
   `git clone https://github.com/HansVanEijsden/wordpress-playlists.git`
   (or `git pull` inside that folder to update an existing install).
2. Activate **WordPress Playlists** in the WordPress admin.
3. Go to **Settings → WordPress Playlists** and configure the station, then save.
4. Point the metadata hub at `/wp-json/<namespace>/playlist/track` (shown on the settings page).

On WordPress multisite, activate and configure the plugin on each subsite; settings are stored per site, so every subsite can have its own station.

== Frequently Asked Questions ==

= Where is the API key stored? =

In the `wordpress_playlists_settings` option in the database. It is entered on the settings page, never echoed back, and an empty key field on save keeps the existing key. The key never appears in source code, the repo, or the plugin logs.

= Which auth methods does the endpoint accept? =

The `X-API-Key` header, an `Authorization: Bearer <key>` header, or a `?secret=<key>` query parameter. All comparisons are constant-time (`hash_equals`).

= What happens to short or unparseable durations? =

Durations shorter than 2 minutes are rejected (HTTP 422). A missing or unparseable duration is accepted, with a warning written to the error log.

= Does the plugin download images? =

No. Featured images are set with existing attachment IDs only (the show avatar if it exists, otherwise the configured fallback image ID).

= Does uninstalling delete my playlists? =

No. By default uninstalling only removes the plugin's own settings option. Playlists are only deleted if you explicitly enable "Delete all playlist posts when the plugin is uninstalled" on the settings page - and even then only playlist posts are targeted, nothing else.

== Changelog ==

= 1.2.0 =
* Archiefkoppeling: de playlists verrijken de Broadcast Archive-payload met artiest-URL's en de local-flag, zodat elke artiestnaam in een archiefspeellijst naar de artiestpagina linkt.
* Artiestpagina: een "Terugluisteren in Uitzending Gemist"-knop opent het archief met `?q=Artiest`.
* SEO: de Open Graph-beschrijving gebruikt nu de juiste Yoast-filter (`wpseo_opengraph_desc`); artiestpagina's krijgen hun eigen afbeelding als social image.
* Nieuwe filter `wordpress_playlists_extra_purge_urls`, zodat een radiohub die lokale artiesten toont zijn pagina kan laten purgen.
* `Context::local_artists()` levert nu ook `image_id` en `last`.

= 1.1.1 =
* The local-artist sections now show the full list: the hub defaults to 24 artists and the artist index shows up to 24 instead of a fixed 6.
* Aligned the editorial artist rail with GeneratePress's widget padding, so "On this station", the biography and the links line up with the widgets below.

= 1.1.0 =
* Added the public airplay archive: hub shortcode, month pages, redesigned playlist pages and a canonical page per artist.
* Added the `playlist_artist` taxonomy with play counters, per-artist track summaries and editorial photo, biography, links and local flag.
* Added public search over artist names and track titles, an A-Z artist index with letter pages, an artist sitemap and structured data (MusicPlaylist, MusicGroup, ItemList).
* Added titles, meta descriptions, canonicals and robots integration with Yoast SEO, with a plain-WordPress fallback.
* Added permanent redirects for the Radio Station playlist archive, `?offset=` pagination and, optionally, playlist URLs with a front path; the old URLs consolidate on the hub and the cleaned playlist URLs.
* Added optional listen-back through Broadcast Archive and an embeddable artist card for editorial articles.
* Added the `wp wppl rebuild`, `wp wppl status` and `wp wppl recount` commands, a preview harness and a dependency-free test suite.
* Added Dutch translations.

= 1.0.2 =
* Fixed today's-playlist lookup for brand-new shows: when a show name is provided but no today's playlist title contains it, a new playlist is now created. Previously the track was silently appended to the newest same-day playlist (the previous show's), so a new show (e.g. "Avond aan de IJssel" on Salland1) got no playlist of its own.

= 1.0.1 =
* Fixed today's-playlist lookup: it now queries the post title directly instead of a fuzzy WP_Query search, so search-rewriting plugins (e.g. a fulltext-search mu-plugin) can no longer cause one playlist per track.
* Metadata API endpoint is now auto-detected (blank field falls back to /wp-json/metadata/v1/current); an explicit URL still overrides it.
* Settings page: fallback image is now chosen via the WordPress media library, and the playlist post author via a user dropdown.
* Settings page now shows a ready-to-paste metadata hub output block (URL, method, payload mapping). The API key is never displayed.

= 1.0.0 =
* Initial public release.
* Single-station playlist management for the Radio Station plugin.
* All station configuration (metadata endpoint, REST namespace, API key, fallback image, post author) is admin-configurable; no secrets in source.
* Configurable playlist comment column label (default "Start Time").
* Safe uninstall: playlists are never deleted unless explicitly enabled.
