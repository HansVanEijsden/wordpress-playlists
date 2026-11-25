# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Existing WordPress plugin: plain PHP 7.4+ (no build step, no Composer, no npm), one public stylesheet and one vanilla JavaScript asset, WordPress i18n. The plugin already owned the ingest side (REST endpoint that writes Radio Station playlist meta); this release adds the public presentation and a derived artist index. Confirmed by the repository and its `AGENTS.md`.

## Users

- **Listeners**, usually on a phone, arriving from a menu ("Afgespeelde nummers"), a show page, or a search result. Their job: find what was played at a certain moment, or find the page of an artist they heard. They know roughly a day, a programme or a song title, rarely an exact URL.
- **Local artists** and their fans. They search their own name or song, or follow a link they were given, and want to see that the station actually played them, when, and in which broadcast. The artist page is their proof and their landing page.
- **Station editors**: flag local artists, add a photo, biography and links, and check that the playlists and artist pages reflect the broadcast. They are not developers; maintenance must be rare and safe.

## Product Purpose

Present the automatically collected airplay as a designed, readable public archive: a hub, month pages, playlist pages and one canonical page per artist. Success means a listener finds the right broadcast or artist in seconds, an artist can point to a stable page that keeps updating, and search engines consolidate the airplay of an artist on that one page instead of spreading it over hundreds of daily playlists.

The plugin remains the single writer of Radio Station playlist meta and never changes that data contract. Everything public is derived from it and can be rebuilt.

## Positioning

A station-agnostic airplay layer on top of the Radio Station plugin: it owns no audio, no third-party service and no station-specific code. It turns the station's own playlist meta into a canonical artist entity, month archive and playlist sheet, inherits the host theme's typography, and takes exactly one station colour. The same code runs on Salland1 and 1Zwolle.

## Operating Context

- **Salland1** (`www.salland1.nl`): single WordPress install, Newspaper theme, Verdana, red accent `#e50526`, hub at `/afspeellijsten/`, playlists at `/playlist/<slug>/`. About 2,047 playlists, 79,092 plays, 3,180 artists at the time of writing.
- **1Zwolle** (`1zwolle.nl`): WordPress multisite (blog 1), GeneratePress, Mulish, indigo accent `#283593`, hub at `/afspeellijsten/` after the URL cleanup, playlists at `/playlist/<slug>/` (previously `/nieuws/playlist/…`). About 1,079 playlists, 84,995 plays, 2,566 artists.
- Both run WordPress 7.1, PHP 8.5, Yoast SEO, an nginx full-page cache (`srcache`/Redis, no TTL) and a persistent SQLite object cache. Cross-plugin cache purging must be handled explicitly.
- Playlists are produced by the metadata hub each time a track starts; a Non Stop day can hold 385 tracks, a normal show 5–50.
- Broadcast Archive is active on Salland1 (hourly episodes for roughly the last three weeks); 1Zwolle has no archive yet.
- Track start times and durations are always present; album and label are empty in current data; 27 shows on Salland1, 13 on 1Zwolle; a handful of older playlists have no valid show reference.

## Capabilities and Constraints

- Public surfaces: `[wordpress_playlists]` hub, `[wordpress_playlists_artist]` embeddable card, month pages under the hub, redesigned single playlist content, artist taxonomy pages, an A–Z artist index with letter pages and search.
- The playlist page keeps the theme shell (title, breadcrumb, sidebar, advertising); only the article content is replaced. Artist, month and index pages use the plugin's own templates with the site header/footer.
- Artist identity is a public taxonomy (`playlist_artist`) with stable keys, play counters and a per-artist track summary in term meta, plus editorial photo, biography, website/Instagram/Spotify/YouTube and a local-artist flag. All derived data is rebuildable with `wp wppl rebuild`.
- Search covers artist names and track titles. It must not use `WP_Query`'s `s` parameter: a fulltext-search mu-plugin on these sites rewrites it.
- Titles, descriptions, canonicals, robots, breadcrumbs and JSON-LD integrate with Yoast when active and fall back to plain WordPress otherwise.
- Legacy URLs are redirected permanently: the Radio Station playlist archive to the hub, `?offset=` pagination to the hub, and on 1Zwolle the old `/nieuws/playlist/…` paths to `/playlist/…`.
- English source strings, Dutch interface through `languages/wordpress-playlists-nl_NL.po|mo`; no station name anywhere in the repository. No external fonts, JavaScript frameworks or icon CDNs. No build step: deploys by `git pull`.
- PHP 7.4 compatible, WordPress 5.8+; the public pages are fully server-rendered so they work in a cached page and for crawlers.
- Local artists are flagged editorially; locality is never inferred.

## Brand Commitments

- The plugin must not impose a station identity. It inherits the host theme's typeface, text colour and page width, uses neutral surfaces and takes exactly one station-controlled accent colour.
- The visual language belongs to the same family as the station's radio hub and programme guide: ink `#16181d`, hairline `#e5e7eb`, white/`#f6f7f9` surfaces, a near-black object for the one broadcast plate, rounded 8–16 px, no gradients, glass or decorative shadows.
- Salland1 red `#e50526` and 1Zwolle indigo `#283593` are the current station accents; the plugin derives darker text and tints so every pair meets WCAG AA.
- Copy is factual and calm: what was played, when, by whom. No marketing claims, no invented statistics.

## Evidence on Hand

- Live incumbents: <https://www.salland1.nl/afspeellijsten/>, <https://www.salland1.nl/playlist/25-november-2026-sallands-cafe/>, <https://1zwolle.nl/afspeellijsten-radio>.
- Real counts from the live databases (2026-11-25): Salland1 2,047 playlists / 79,092 played tracks / 3,180 distinct artists / 6,691 distinct artist–title pairs; 1Zwolle 1,079 / 84,995 / 2,566 / 5,528. Every track has a start time and a duration; album and label are empty.
- Broadcast Archive REST payload on Salland1: 336 hourly episodes, 2026-11-05 – 2026-11-25, with show, presenter, start/end, duration and audio URL.
- The preview harness in `preview/` renders the real views from realistic fixtures; `tests/` exercises the pure logic and renderers. There are no testimonials, ratings or commercial claims, and none may be invented.

## Product Principles

1. **Find first.** A visitor arrives with a time, a programme, an artist or a song. Search and the date hierarchy must answer that before any editorial decoration.
2. **One artist, one page.** The canonical artist page carries the airplay and links to every broadcast; daily playlists stay the record, not the landing page.
3. **The data is the design.** Times, artists, titles, counts and dates carry the visual interest; chrome stays quiet and the accent is spent sparingly.
4. **Derived, never authoritative.** Playlist meta stays the single source of truth; the index, counters, snapshots and cache versions can be rebuilt at any time.
5. **Native to the host.** The theme owns the page shell, type and colour; the plugin owns the airplay component and stays station-agnostic.

## Accessibility & Inclusion

WCAG 2.2 AA: semantic headings and a real table with caption and scopes on playlists, keyboard-operable controls with visible focus, labels and live regions for search, no information conveyed by colour alone, AA contrast on both station accents and their tints, usable at 320 px and at 200 % zoom, and motion that respects `prefers-reduced-motion`.
