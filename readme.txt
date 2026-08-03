=== WordPress Playlists ===
Contributors: hansvaneijsden
Tags: radio, playlist, radio-station, rest-api, broadcast
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic daily radio playlists for the Radio Station plugin. Each station receives track data over its own REST API endpoint and appends the track to the correct daily playlist for the current show.

== Description ==

WordPress Playlists automatically builds daily radio playlists for the [Radio Station](https://wordpress.org/plugins/radio-station/) plugin.

A metadata hub POSTs every played track to the station's REST endpoint. The plugin:

* Authenticates the request against a per-station API key configured in the WordPress admin (no secrets in source code).
* Validates the track (artist + title required; optional duration, only tracks shorter than 2 minutes are rejected).
* Fetches the currently broadcasting show from the station's metadata API.
* Finds or creates today's playlist post for that show.
* Appends the track to the playlist with the exact meta structure Radio Station expects.

= Multiple stations =

Each station is configured independently under **Settings → WordPress Playlists**:

* **Enable station** – registers its REST endpoint.
* **Station name** – used in logs and as the playlist author context.
* **REST namespace** – the URL segment of the track endpoint (`/wp-json/<namespace>/playlist/track`).
* **Metadata API endpoint** – returns the current show (`broadcast.current_show.show.{id,name,slug,avatar_id}`).
* **API key** – accepted via `X-API-Key` header, `Authorization: Bearer` or `?secret=` query parameter. Stored in the options table, never displayed.
* **Fallback image ID** – attachment used for the playlist featured image when the show has no avatar.
* **Post author ID** – user assigned to created playlist posts.

Stations can be added and removed from the same page.

= Dependencies =

* Requires the [Radio Station](https://wordpress.org/plugins/radio-station/) plugin to be active.
* Requires PHP 7.4 or newer.

== Installation ==

The recommended way to install is via `git clone` or `git pull` into the WordPress plugins directory, so updates are a simple `git pull`:

1. On the server, inside `wp-content/plugins/`:
   `git clone https://github.com/HansVanEijsden/wordpress-playlists.git`
   (or `git pull` inside that folder to update an existing install).
2. Activate **WordPress Playlists** in the WordPress admin.
3. Go to **Settings → WordPress Playlists** and enter an API key for each station, then save.
4. Point each station's metadata hub at `/wp-json/<namespace>/playlist/track` (shown on the settings page).

= Replacing the legacy single-file plugins =

If the older per-station plugins (`afspeellijsten-1zwolle.php`, `afspeellijsten-salland1.php`) are still active on a site:

1. Deactivate and delete those legacy plugins.
2. Activate **WordPress Playlists**.
3. Configure the station API keys on the settings page (the legacy key can be re-used, or rotate it and update the hub).

Deleting the legacy plugins is important: two plugins writing to the same playlist would duplicate tracks and register conflicting REST routes.

== Frequently Asked Questions ==

= Where are the API keys stored? =

In the `wordpress_playlists_settings` option in the database. Keys are entered on the settings page, are never echoed back, and an empty key field on save keeps the existing key. Keys never appear in source code, the repo, or the plugin logs.

= Which auth methods does the endpoint accept? =

Three, mirroring the legacy plugins: the `X-API-Key` header, an `Authorization: Bearer <key>` header, or a `?secret=<key>` query parameter. All comparisons are constant-time (`hash_equals`).

= What happens to short or unparseable durations? =

Durations shorter than 2 minutes are rejected (HTTP 422). A missing or unparseable duration is accepted, with a warning written to the error log, matching the legacy behavior.

= Does the plugin download images? =

No. Featured images are set with existing attachment IDs only (the show avatar if it exists, otherwise the configured fallback image ID).

= Why is the playlist comment column labelled "Starttijd"? =

The Radio Station plugin calls the column "Comments"; the stations display it as "Starttijd" (Dutch for "start time") because it holds the track start time. This is a site-facing label on the Dutch stations and stays in Dutch.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Merges the legacy 1Zwolle and Salland1 single-file plugins into one multi-station plugin.
* All station configuration (API endpoint, REST namespace, API key, fallback image, post author) is admin-configurable; no secrets in source.
