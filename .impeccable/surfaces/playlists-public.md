---
version: 1
slug: "playlists-public"
primary_target: "includes/class-playlist-view.php"
related_targets:
  - "assets/css/playlist-display.css"
  - "includes/class-hub-view.php"
  - "includes/class-artist-view.php"
---

# Surface: public playlist presentation (hub, month, playlist, artist)

## Scope and mode

- **Mode:** Read. The visitor's job is comprehension and retrieval: what was played, when, in which broadcast, and who the artist is. The hub is also a light wayfinding surface (search, today, month archive).
- **Targets:** the single-playlist content replacement, the `[wordpress_playlists]` hub shortcode, the month template, the artist taxonomy template, the A–Z index, letter and search pages, the embeddable artist card, and the display stylesheet/script.
- Not in scope: the Radio Station show pages, the metadata hub, the playlist data contract, station branding.

## Audience, job, action

Listeners on a phone or desktop who remember a moment, a programme, an artist or a song. Local artists and their fans who want proof of airplay and a page to share. Editors who flag local artists and add a biography. The primary action is opening a broadcast or an artist page; the secondary action is jumping to an hour or a month. Search is the shortest path when a name is known.

## Content and constraints

- Real data only: playlist meta (artist, title, start time, duration), show relation and featured artwork, term play counters and track summaries, artist editorial fields. No invented tracks, counts or biographies.
- The playlist page keeps the host theme's shell, title, breadcrumb, sidebar and advertising; only the article body is replaced. Custom templates keep the site's header and footer.
- Inherits the host typeface and text colour; one station accent, with derived `accent-ink`, `on-accent` and tints verified for AA on white and on the strongest tint.
- Server-rendered for SEO and caching; JavaScript only marks the currently playing row, copies a link and hands an episode to the Broadcast Archive player.
- 385-track Non Stop days must remain usable: hour bands, hour jump links, compact rows. No numeric pagination anywhere; month pages carry the archive and the artist page steps newer/older with a range count.
- WCAG 2.2 AA, 320 px, 200 % zoom, reduced motion, no external fonts/scripts.

## Chosen direction and memorable moment

**The airplay ledger.** The station's own broadcast bookkeeping, raised to an editorial surface: hairline rules, tabular times, hour bands with oversized numerals and one dark broadcast plate. It refuses the category default of a card grid of cover art and the old wall of numeric pagination. The memorable object is the hour spine in a long broadcast: a large hour numeral, a rule, the number of tracks in that hour, with jump links above; the current track is marked with an accent-tinted row and a "Now" label.

## Direction contract

**THESIS:** An airplay archive that reads like a carefully kept broadcast ledger, not a widget: one canonical page per artist, a month archive instead of 171 pages of numbered links, and a playlist sheet whose hour spine makes a 385-track day navigable.

**OWN-WORLD:** Inherited host typography on a white ground; ink `#16181d` / soft `#3f4652` / muted `#626c7a`; hairlines `#e5e7eb` and `#cfd4dc`; surfaces `#fff` and `#f6f7f9`; one near-black plate `#14161c`; one station accent used for links, the local badge, the now marker, hour chips and current state; radii 16/10/8/pill; tabular numerals for every time and count; no gradients, glass, shadows or decorative corner cuts.

**STORY:** The visitor searches a name or browses today, a day and a month; opens a broadcast and reads the sheet; follows an artist to a canonical page with every track, play count, first/last date and every broadcast; shares the link. Editors can flag local talent and give it a face and a biography, which appears in the local section and on the artist page.

**FIRST VIEWPORT:** On a playlist page the theme's H1 comes first, then the dark plate with 96 px artwork, the programme name at 23 px, date · time span · track count, and a link to the programme; below it the caption and three-column sheet header (Time, Artist and track, Length) and compact rows at 44 px. On a 385-track day a wrapped hour-chip strip appears above the sheet. On artist pages the first viewport is a monogram/photo, the artist name at up to 36 px, play/track/broadcast counts and the last-played date, then the track list and the broadcasts with newer/older steps and a range count.

**FORM:** Public surface inside an established station world, code-led. The direction was approved by the user as part of the implementation plan ("Akkoord met het plan!") rather than by a concept-seed roll; it inherits the radio hub and programme guide family. seed: none (pinned) — the approved plan names "airplay-ledger: hairline rules, tabular numerals, hour bands, one dark broadcast plate" and the family tokens of the hubs and the guide.

FINISH: reviewed. The finish reviewer returned **ship** for the scored fix batch (no-JS copy control, kickers, step pager, now marker, dark-plate focus, legacy-hub redirects, date spacing, mobile separators, evidence coherence). The live WordPress deploy pass and the disclosed residuals (real artist photo, 200% zoom, a real 204-playlist month) remain open and are recorded in DESIGN.md.
