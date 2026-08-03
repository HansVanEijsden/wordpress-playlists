# AGENTS.md

WordPress Playlists — a production WordPress plugin that automatically builds daily radio playlists for the **Radio Station** plugin. One plugin, multiple stations, all configuration in the WordPress admin. Public repo: https://github.com/HansVanEijsden/wordpress-playlists — sites deploy by `git pull` into `wp-content/plugins/wordpress-playlists`.

## Project language

Everything is in **English**: code, comments, docs, this file. The only site-facing strings that may be localized per site are the playlist comment column label (admin-configurable, see General settings) and the playlist titles, which use `date_i18n()` and therefore follow the site locale (a Radio Station data contract).

## Layout

| Path | Purpose |
|---|---|
| `wordpress-playlists.php` | Plugin bootstrap + header (author: Hans van Eijsden). |
| `includes/class-settings.php` | Admin settings page (Settings → WordPress Playlists) + sanitization. |
| `includes/class-station.php` | Immutable per-station value object (typed, sanitized). |
| `includes/class-rest-controller.php` | Registers `/wp-json/<namespace>/playlist/track` per station + auth. |
| `includes/class-metadata-client.php` | Fetches current show from the station's metadata API. |
| `includes/class-playlist-manager.php` | Finds/creates today's playlist, appends tracks (Radio Station contract). |
| `includes/class-logger.php` | `error_log` helper, prefix `[WordPress Playlists][<station>]`. |
| `includes/class-gettext.php` | Renames Radio Station `Comments`/`Comment` → configured label (default `Start Time`). |
| `uninstall.php`, `readme.txt`, `README.md`, `LICENSE`, `.editorconfig`, `.github/workflows/ci.yml` | Housekeeping / distribution / CI. |

All classes live in the `WordPressPlaylists\` namespace.

## Security rules (non-negotiable)

- **No secrets in source, ever.** API keys live only in the `wordpress_playlists_settings` option, entered on the settings page, never echoed, displayed, or logged.
- Auth (`Rest_Controller::check_permission`) accepts ONE of: `X-API-Key` header, `Authorization: Bearer <key>`, or `?secret=<key>` query param — compared with `hash_equals` (constant-time).
- A REST route is only registered for stations that are enabled AND have a non-empty API key (`Station::is_ready()`).
- The `?secret=` path means the key can appear in URLs/access logs — keep the feature (some integrators rely on it) but be aware when reviewing logs.
- An empty API-key field on save keeps the existing key (`Settings::sanitize_api_key`), so saving other fields can never wipe a secret.

## How it works

1. A metadata hub `POST`s a track to `/wp-json/<namespace>/playlist/track` (namespace per station, e.g. `mysite/v1`).
2. Auth against the station's key; track validated (artist + title required).
3. Duration: optional; `< 120s` rejected (HTTP 422); empty or unparseable ACCEPTED with a warning log — don't change that into a hard error without asking.
4. Current show fetched from the station's metadata API — shape `body.broadcast.current_show.show.{id,name,slug,avatar_id}`.
5. Finds or creates today's playlist post, then appends the track to its `playlist` post meta.

### Radio Station data contract (do not change)

- Post type: `playlist`; linked to show via meta `playlist_show_id` (NUMERIC).
- Playlist title: `date_i18n('j F Y') - <show name>` (site-locale date, e.g. `3 augustus 2026 - Morning Show`). Today's-playlist lookup (`Playlist_Manager::get_todays_playlist`) depends on this exact format via fuzzy title search (`'s' => $today_date`) + `stripos` — a deliberate, relatively expensive choice; don't replace it with a meta-only query without checking the user.
- Tracks in meta `playlist`, 1-based keyed array; each entry: `playlist_entry_artist`, `playlist_entry_song`, `playlist_entry_album`, `playlist_entry_label`, `playlist_entry_minutes`, `playlist_entry_seconds`, `playlist_entry_comments` (= start time `H:i`), `playlist_entry_status` (`played`).
- Duration accepted as `MM:SS` or `MM:SS.mmm`; stored as separate minutes/seconds fields.
- Featured image: use the API `avatar_id` only if it exists as an attachment, else the station's configured fallback ID. **Never download images** — `set_post_thumbnail` with existing attachment IDs only.

## Conventions

- WordPress Coding Standards style: spaces, long `array()` syntax, single quotes, `function ()`.
- All plugin strings use the `wordpress-playlists` text domain and `__()`/`_e()`/`esc_html__()`.
- PHP 7.4+ (typed properties OK, no PHP 8-only syntax). No composer/build step — plain `require_once` bootstrap; deploys by `git pull`.
- The comment column label is admin-configurable (default `Start Time`); the `gettext` filter must respect an empty label (no rename).

## Validation

- CI (`.github/workflows/ci.yml`) runs `php -l` over every PHP file on PHP 7.4 and 8.2 for every push/PR.
- Locally: `for f in $(find . -name '*.php'); do php -l "$f"; done` (PHP available on this Mac).
- Runtime behavior is only verifiable on live sites after `git pull` + configuring stations/API keys in admin.

## Pitfalls

- `get_todays_playlist()` resets postdata ONCE after the loop (don't reintroduce a reset inside the loop).
- A station that is enabled but keyless has NO registered route → the hub gets a 404 until a key is saved. That is intended behavior.
- `date_i18n('j F Y')` respects WP locale/timezone — the title format must stay in sync with what the live sites produce.
- Radio Station must be active (`post_type_exists('playlist')` guard in the REST handler).
- Uninstall is deliberately conservative: it only removes the plugin option; playlist deletion happens only when `delete_playlists_on_uninstall` is explicitly enabled in settings (default off). Keep it that way — playlists are valuable content.

