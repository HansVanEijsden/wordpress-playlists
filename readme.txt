=== WordPress Playlists ===
Contributors: hansvaneijsden
Tags: radio, playlist, radio-station, rest-api, broadcast
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic daily radio playlists for the Radio Station plugin. A metadata hub posts each played track to the plugin's REST endpoint, which appends it to the correct daily playlist for the current show.

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
