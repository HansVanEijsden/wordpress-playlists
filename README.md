# WordPress Playlists

Automatic daily radio playlists for the [Radio Station](https://wordpress.org/plugins/radio-station/) WordPress plugin — and a designed public airplay archive on top of them.

A metadata hub posts every played track to the plugin's REST API endpoint. The plugin authenticates the request, looks up the currently broadcasting show, finds or creates today's playlist for that show, and appends the track. The same plugin turns that data into a public hub, month archive, playlist sheets and **one canonical page per artist**, so the airplay of a local artist is consolidated in search instead of being spread over hundreds of daily playlists.

- **One station per site** — each WordPress site (or subsite) has exactly one radio station.
- **No secrets in source** — the API key lives in the database and is never displayed.
- **Derived, rebuildable** — playlist meta stays the single source of truth; the artist index is derived and can be rebuilt.
- **Deploy by `git pull`** — no build step, no Composer, no npm.

## Features

### Ingest

- REST endpoint: `POST /wp-json/<namespace>/playlist/track`
- Auth via `X-API-Key` header, `Authorization: Bearer`, or `?secret=` (constant-time comparison)
- Track validation (artist + title required; optional duration)
- Automatic daily playlist creation per show, using Radio Station's exact meta structure
- Admin-configurable: station name, REST namespace, metadata endpoint, API key, fallback image, post author
- Configurable playlist comment column label (default `Start Time`)
- Safe uninstall (playlists are never deleted unless explicitly enabled)
- Multisite-ready (each subsite configures its own station and presentation)

### Public presentation

- **Hub** — `[wordpress_playlists]`: search, today, earlier this week, the most-played artists, local talent and a month archive. No numeric pagination.
- **Month pages** — every playlist of a month, grouped per day, under the hub (`/afspeellijsten/2026/11/` by default).
- **Playlist pages** — the theme keeps its title, breadcrumb and sidebar; the track table is replaced by an airplay sheet with a broadcast plate, tabular times, durations and an hour spine for long (Non Stop) broadcasts.
- **Artist pages** — one canonical page per artist with every played track, play counts, first/last dates, the broadcasts they appeared in, a biography, photo, links and a local-artist badge.
- **Artist index** — search on artist names and track titles, an A–Z overview and a page per letter.
- **Back to listening** — when Broadcast Archive is active, a playlist offers the matching broadcast(s) through its shared player.
- **Embeddable card** — `[wordpress_playlists_artist name="…"]` for editorial articles.
- **SEO** — titles, meta descriptions, canonicals, robots, `MusicPlaylist`/`MusicGroup` JSON-LD, Yoast breadcrumbs and a month sitemap; permanent redirects for the old archive and pagination URLs. Works with or without Yoast.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- [Radio Station](https://wordpress.org/plugins/radio-station/) plugin (active)
- Optional: [Broadcast Archive](https://github.com/HansVanEijsden/broadcast-archive) for the listen-back buttons
- Optional: Yoast SEO for the richest metadata integration (plain WordPress works too)

## Installation

Install into `wp-content/plugins/` so that updates are a simple `git pull`:

```sh
cd wp-content/plugins
git clone https://github.com/HansVanEijsden/wordpress-playlists.git wordpress-radio-station-playlists
```

To update an existing install:

```sh
cd wp-content/plugins/wordpress-radio-station-playlists
git pull
```

Then activate **WordPress Playlists** and configure it under **Settings → WordPress Playlists**.

On WordPress multisite, activate and configure the plugin on each subsite; settings and terms are stored per site.

## Configuration

### Station

| Setting | Description |
|---|---|
| Enable station | Registers the REST endpoint |
| Station name | Used in logs and playlist author context |
| REST namespace | The path segment of the track endpoint between `/wp-json/` and `/playlist/track` — set it to the namespace your metadata hub posts to |
| Metadata API endpoint | Returns the current show: `broadcast.current_show.show.{id,name,slug,avatar_id}` |
| API key | Secret; accepted via header, bearer, or query param; never displayed |
| Fallback image ID | Attachment used as the playlist featured image when the show has no avatar |
| Post author ID | User assigned to created playlist posts |

A station that is enabled but has no API key does **not** register a route, so the endpoint returns 404 until a key is saved.

### Public presentation

| Setting | Description |
|---|---|
| Designed playlists | Turns the public layer on or off. Off restores Radio Station's own playlist content and disables the artist/month pages and the hub shortcode; stored artist data is kept. |
| Accent colour | One station colour. The plugin derives an accessible text colour and tints at render time. |
| Hub page | The page containing `[wordpress_playlists]`. Month pages live under its path. Leave on automatic detection when there is exactly one such page. |
| Artist URLs | Rewrite bases for a single artist (`artiest`) and the artist index (`artiesten`). |
| Playlists per artist page | Pagination size for the chronological broadcast list on an artist page. |
| Local artists | Show the local-artists sections on the hub and artist index when artists are flagged. |
| Listen-back page | The Broadcast Archive page. Used only when the archive plugin and a matching episode exist. |
| Clean playlist URLs | Removes the site's front path from playlist permalinks and redirects the old URLs. Enable this on a site whose playlist URLs currently include a path such as `/nieuws/playlist/`. |
| Legacy hub paths | Comma-separated old hub paths that permanently redirect to the current hub, for example a previous page slug. |

### General

- **Playlist comment column label** — Radio Station's playlist entries have a "Comments" field, which this plugin uses to store each track's start time. Use this setting to rename that column's header (default `Start Time`); leave empty to keep Radio Station's wording.
- **Uninstall behavior** — by default, uninstalling only removes the plugin's own settings option. Deleting playlist posts on uninstall is disabled by default and must be enabled explicitly. Artist terms, biographies and photos are never deleted by uninstall.

## The public layer

### Shortcodes

| Shortcode | Purpose |
|---|---|
| `[wordpress_playlists]` | The hub: search, today, the past week, most played, local artists and months. Place it on a page; that page becomes the hub. |
| `[wordpress_playlists_artist name="Douwe Bob" tracks="3"]` | A compact artist card for articles. Accepts `name` or `id`; `tracks` limits the track rows (0–6). |

### URLs

| Surface | Default path |
|---|---|
| Hub | the page with `[wordpress_playlists]` |
| Month | `<hub>/2026/11/` |
| Playlist | `/playlist/<date>-<show>/` |
| Artist | `/artiest/<slug>/` |
| Artist index | `/artiesten/`, letters at `/artiesten/a/` |
| Search | `/artiesten/?q=…` (noindex) |

Permanent redirects: Radio Station's playlist archive (`/playlists/` or the site's front path + `/playlists/`) and every `?offset=` pagination URL go to the hub. With **Clean playlist URLs** enabled, the old `/nieuws/playlist/…` paths go to `/playlist/…`. A previous hub path (for example `/afspeellijsten-radio`) is redirected when it is listed under **Legacy hub paths**.

### Artist index

The public layer uses a `playlist_artist` taxonomy for artist identity: a stable key, an editable display name, play counters, a per-artist track summary and editorial fields (photo, biography, website, Instagram, Spotify, YouTube, "local artist"). Tracks are assigned to artists as the metadata hub reports them, and reconciled when an editor changes a playlist.

The index is derived from the `playlist` post meta. After installing or importing older playlists, build it once:

```sh
wp wppl rebuild --dry-run   # report differences only
wp wppl rebuild             # write the derived data
wp wppl status              # counts and the last rebuild
wp wppl recount douwe-bob   # recompute one artist from its playlists
```

On multisite, pass `--url=https://example.com` (or `--url` of the subsite). Run the rebuild in `tmux`/`screen`; a site with a few thousand playlists takes a few minutes.

### Caching

The pages are fully server-rendered and contain no nonce or per-visitor state. New tracks bump a cache version and purge the affected playlist, artist, hub and month URLs through the detected integrations (nginx-helper, WP Rocket, LiteSpeed) plus the `wordpress_playlists_purge_url` action. Verify the site's own full-page cache after deploying; if it uses a different purger, hook that action.

## REST API

### Track endpoint

`POST /wp-json/<namespace>/playlist/track`

**Authentication** — one of:

- `X-API-Key: <key>` header
- `Authorization: Bearer <key>` header (used by the metadata hub)
- `?secret=<key>` query parameter

**Request body** (JSON or form-encoded):

| Field | Required | Type | Notes |
|---|---|---|---|
| `artist` | yes | string | |
| `title` | yes | string | |
| `duration` | no | string | `MM:SS` or `MM:SS.mmm`; shorter than 2 minutes rejected |
| `album` | no | string | |
| `songID` | no | string | |

**Responses**

- `200` — track added
- `400` — missing artist/title
- `401` — unauthenticated
- `404` — no current show (or route not registered)
- `422` — duration too short
- `500` — server error / Radio Station inactive

## How it works

1. A metadata hub `POST`s a track to the endpoint.
2. The request is authenticated against the configured API key.
3. The current show is fetched from the metadata API.
4. Today's playlist for that show is found (or created).
5. The track is appended to the playlist's `playlist` post meta.
6. The artist index records the play, the playlist summary and the artist's track summary, and invalidates the display caches.
7. The public views read the playlist meta (single playlist) or the derived summaries (hub, month, artist pages).

## Development

```sh
# Lint all PHP files
for f in $(find . -name '*.php' -type f); do php -l "$f"; done

# Renderer and logic tests (no WordPress needed)
php tests/run.php

# Preview the public surfaces with realistic fixtures
php -S 127.0.0.1:8099 -t .
# open http://127.0.0.1:8099/preview/?view=playlist
```

The preview harness lives in `preview/` (development only, not part of the plugin folder shipped to production if you copy only the plugin files). It renders the real views and styles with the preview stubs in `preview/bootstrap.php`.

Translations: the source strings are English; Dutch ships in `languages/wordpress-playlists-nl_NL.po|mo`. Regenerate the template with:

```sh
python3 tools/make-pot.py
msgfmt -o languages/wordpress-playlists-nl_NL.mo languages/wordpress-playlists-nl_NL.po
```

A GitHub Actions workflow (`.github/workflows/ci.yml`) runs the lint and the tests on PHP 7.4 and 8.2 for every push and pull request.

## License

[GPL-2.0-or-later](LICENSE)
