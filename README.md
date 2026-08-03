# WordPress Playlists

Automatic daily radio playlists for the [Radio Station](https://wordpress.org/plugins/radio-station/) WordPress plugin.

A metadata hub posts every played track to a station's REST API endpoint. The plugin authenticates the request, looks up the currently broadcasting show, finds or creates today's playlist for that show, and appends the track — no manual work required.

- **One plugin, many stations** — each station is configured independently in the WordPress admin.
- **No secrets in source** — API keys live in the database and are never displayed.
- **Deploy by `git pull`** — the repo is structured so the plugin folder is a git checkout.

## Features

- Per-station REST endpoint: `POST /wp-json/<namespace>/playlist/track`
- Auth via `X-API-Key` header, `Authorization: Bearer`, or `?secret=` (constant-time comparison)
- Track validation (artist + title required; optional duration)
- Automatic daily playlist creation per show, using Radio Station's exact meta structure
- Admin-configurable: station name, REST namespace, metadata endpoint, API key, fallback image, post author
- Configurable playlist comment column label (default `Start Time`)
- Safe uninstall (playlists are never deleted unless explicitly enabled)
- Multisite-aware

## Requirements

- WordPress 5.8+
- PHP 7.4+
- [Radio Station](https://wordpress.org/plugins/radio-station/) plugin (active)

## Installation

Install into `wp-content/plugins/` so that updates are a simple `git pull`:

```sh
cd wp-content/plugins
git clone https://github.com/HansVanEijsden/wordpress-playlists.git
```

To update an existing install:

```sh
cd wp-content/plugins/wordpress-playlists
git pull
```

Then activate **WordPress Playlists** and configure it under **Settings → WordPress Playlists**.

## Configuration

### Stations

Each station needs:

| Setting | Description |
|---|---|
| Enable station | Registers the station's REST endpoint |
| Station name | Used in logs and playlist author context |
| REST namespace | URL segment of the track endpoint: `/wp-json/<namespace>/playlist/track` |
| Metadata API endpoint | Returns the current show: `broadcast.current_show.show.{id,name,slug,avatar_id}` |
| API key | Secret; accepted via header, bearer, or query param; never displayed |
| Fallback image ID | Attachment used as the playlist featured image when the show has no avatar |
| Post author ID | User assigned to created playlist posts |

Stations can be added and removed from the settings page. A station that is enabled but has no API key does **not** register a route, so its endpoint returns 404 until a key is saved.

### General

- **Playlist comment column label** — Radio Station calls the track start-time column "Comments". You can rename it (default `Start Time`); leave empty to keep Radio Station's wording.
- **Uninstall behavior** — by default, uninstalling only removes the plugin's own settings option. Deleting playlist posts on uninstall is disabled by default and must be enabled explicitly.

## REST API

### Track endpoint

`POST /wp-json/<namespace>/playlist/track`

**Authentication** — one of:

- `X-API-Key: <key>` header
- `Authorization: Bearer <key>` header
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

1. A metadata hub `POST`s a track to the station's endpoint.
2. The request is authenticated against the station's configured API key.
3. The current show is fetched from the station's metadata API.
4. Today's playlist for that show is found (or created).
5. The track is appended to the playlist's `playlist` post meta.

## Development

```sh
# Lint all PHP files
for f in $(find . -name '*.php' -type f); do php -l "$f"; done
```

A GitHub Actions workflow (`.github/workflows/ci.yml`) runs this lint on PHP 7.4 and 8.2 for every push and pull request.

## License

[GPL-2.0-or-later](LICENSE)
