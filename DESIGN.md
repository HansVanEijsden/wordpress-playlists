---
name: WordPress Playlists
description: A station-agnostic airplay ledger: hub, month archive, playlist sheets and canonical artist pages.
colors:
  ink: "#16181d"
  ink-soft: "#3f4652"
  ink-muted: "#626c7a"
  ink-faint: "#9aa2ad"
  line: "#e5e7eb"
  line-strong: "#cfd4dc"
  surface: "#ffffff"
  surface-2: "#f6f7f9"
  surface-3: "#eef0f3"
  dark: "#14161c"
  on-dark: "#f4f5f7"
  on-dark-soft: "rgba(244, 245, 247, 0.72)"
  accent: "#e50526"
  accent-ink: "#b3041e"
  accent-soft: "rgba(229, 5, 38, 0.05)"
  accent-soft-strong: "rgba(229, 5, 38, 0.09)"
typography:
  page-title:
    fontFamily: "inherit"
    fontSize: "clamp(26px, 3.2vw, 34px)"
    fontWeight: 700
    lineHeight: 1.14
    letterSpacing: "-0.02em"
  artist-name:
    fontFamily: "inherit"
    fontSize: "clamp(28px, 3.4vw, 36px)"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.025em"
  plate-title:
    fontFamily: "inherit"
    fontSize: "23px"
    fontWeight: 700
    lineHeight: 1.18
    letterSpacing: "-0.01em"
  section-title:
    fontFamily: "inherit"
    fontSize: "21px"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  row-title:
    fontFamily: "inherit"
    fontSize: "16px"
    fontWeight: 650
    lineHeight: 1.3
  track-artist:
    fontFamily: "inherit"
    fontSize: "14px"
    fontWeight: 650
    lineHeight: 1.4
  track-title:
    fontFamily: "inherit"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.4
  time:
    fontFamily: "inherit"
    fontSize: "13px"
    fontWeight: 700
    lineHeight: 1.3
  meta:
    fontFamily: "inherit"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: 1.4
  hour-number:
    fontFamily: "inherit"
    fontSize: "26px"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "-0.02em"
  scale-9:
    fontSize: "9px"
  scale-10:
    fontSize: "10px"
  scale-11:
    fontSize: "11px"
  scale-12:
    fontSize: "12px"
  scale-13:
    fontSize: "13px"
  scale-14:
    fontSize: "14px"
  scale-15:
    fontSize: "15px"
  scale-16:
    fontSize: "16px"
  scale-18:
    fontSize: "18px"
  scale-20:
    fontSize: "20px"
  scale-21:
    fontSize: "21px"
  scale-23:
    fontSize: "23px"
  scale-26:
    fontSize: "26px"
  scale-30:
    fontSize: "30px"
  scale-clamp-title:
    fontSize: "clamp(26px, 3.2vw, 34px)"
  scale-clamp-artist:
    fontSize: "clamp(28px, 3.4vw, 36px)"
rounded:
  xs: "8px"
  sm: "10px"
  md: "12px"
  lg: "14px"
  xl: "16px"
  pill: "999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  xxl: "38px"
components:
  plate:
    backgroundColor: "{colors.dark}"
    textColor: "{colors.on-dark}"
    rounded: "{rounded.xl}"
    padding: "20px"
  track-row:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    padding: "11px 8px"
  current-row:
    backgroundColor: "{colors.accent-soft}"
    textColor: "{colors.ink}"
  hour-chip:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "5px 8px"
  local-badge:
    backgroundColor: "{colors.accent-soft-strong}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.pill}"
    padding: "2px 7px"
  search-input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "0 14px"
    height: "44px"
  primary-button:
    backgroundColor: "{colors.dark}"
    textColor: "{colors.surface}"
    rounded: "{rounded.sm}"
    padding: "0 18px"
    height: "44px"
    typography: "{typography.meta}"
---

# Design — WordPress Playlists

## Overview

The public layer is an **airplay ledger**: the station's broadcast bookkeeping raised to an editorial surface. It replaces a bare list of links and a table with a hub, a month archive, playlist sheets and a canonical page per artist. It is not a landing page and not a card wall; it is a record that happens to be pleasant to read.

The one memorable object is the **broadcast plate**: the only near-black field on a playlist page, holding the programme artwork, the programme name, the broadcast date, the time span and the track count. Everything under it is calm: hairlines, tabular numerals and a single accent. On long Non Stop days the **hour spine** takes over — a wrapped row of hour jump links, then large hour numerals with a rule and the number of tracks in that hour.

The design belongs to the same family as the station's radio hub and programme guide: identical ink, line and surface values, 16/10/8 px radii, tabular numerals and one station accent. It never ships a display face or a second colour.

## Colors

Neutral surfaces carry the page: `surface`, `surface-2` for quiet panels and `line`/`line-strong` for hairlines. Text has four ranks: ink for names and primary content, ink-soft for track titles and body, ink-muted for metadata and ink-faint for separators and passive arrows. The only dark field is the broadcast plate (`#14161c`), which marks a broadcast, not the brand.

The station supplies **one accent**. At render time the plugin derives three accessible companions: `accent-ink` (the accent darkened until it clears 4.55:1 on white **and** on the 9 % accent tint), `on-accent` (white or near-black, whichever is legible on the filled accent) and two tints (5 % and 9 %). On Salland1 the accent is `#e50526` and `accent-ink` derives to approximately `#b3041e`; on 1Zwolle `#283593` derives to a deeper indigo. The accent appears only on artist links (underlined), the local badge, the now marker, hour chips, the current state of navigation and primary links — never as a large surface.

The current row is marked with a 5 % accent tint, a full 1 px accent outline and a small "Now" pill; the signal is also a word, never colour alone.

Section headings stand alone. The design deliberately has no eyebrow/kicker layer above headings; a heading carries its own context ("Today, 25 November 2026", "Recent broadcasts", "Browse by month"). Uppercase is reserved for state labels (Local, Now) and table column headers, not for decoration.

## Typography

Type is inherited from the host theme — Verdana on Salland1, Mulish on 1Zwolle — and contributes no webfont. Hierarchy is weight, size and tracking: page and artist titles 26–36 px at 700 with tight tracking; 16–21 px section titles; 16 px/650 row titles; 14 px artist names and track titles; 12–13 px metadata; 9–11 px uppercase state labels and column headers at 0.08–0.09 em. Every time, date, count and duration uses tabular numerals.

Uppercase is reserved for functional labels: the table column headers, the local badge and the "Now" marker. Titles are never uppercased, and no heading carries an eyebrow above it.

## Layout

The playlist component is designed for the host article column (roughly 640–760 px) and uses container queries, so it adapts to the column rather than the viewport. The sheet is a real table with three columns: a fixed time column (62 px, 52 px on narrow), a fluid artist/track column and a fixed duration column (58 px, 44 px). Rows are 44 px on desktop.

Month pages are constrained to 900 px and artist/index pages to 980–1120 px. The artist page is a two-column layout above 900 px: the track list and broadcast list on the left in a white card, and a right rail that starts with the “On this station” facts card, then the artist biography/links when present, then the theme's active sidebar widgets. The rail always exists, so the page never leaves an empty right column; it stacks below the main card on narrow screens. The hub fills the theme's content column and is built from sections with a title and a hairline: search, today, recent broadcasts, most played, local artists, months.

Spacing is generous between groups (24–38 px) and tight inside them (1–4 px). Nothing is centred except empty-state text.

## Elevation & Depth

There is no depth. Hairlines separate rows, sections and the plate; the plate is flat near-black; buttons are flat ink. The only "raised" surface is the selected page in the pager and the current letter, both filled with ink. No shadows, gradients, glass or blur.

## Shapes

Rounded rectangles, softly: 16 px for the plate and quiet panels, 12–14 px for adjacent navigation and local cards, 10 px for inputs, 8 px for pager and letter tiles, pills for hour chips, the local badge, the copy button and the "Now" marker. Borders are full or absent; no one-sided accent bars and no decorative corner cuts.

## Components

**Broadcast plate.** Dark grid: 96 px artwork (or a monogram), show name at 23 px, date · time span · track count, and a link to all playlists of the programme. It is the only dark object on the page. Focus inside the plate uses a light ring so it stays visible on the dark ground.

**Track sheet.** A real table with a caption and column scopes. The time is bold and tabular; the artist is the link destination, in ink with an accent underline that becomes solid and accent-coloured on hover; the track title follows in ink-soft after an em dash; the duration is right-aligned and muted. On long days, hour rows interrupt the list with a 26 px numeral, the word "hour" and the count.

**Hour navigation.** A wrapped strip of pills, each showing the hour and its track count, with an accessible label ("Hour 19, 17 tracks"). It anchors to the hour rows.

**Artist page.** Monogram/photo, name, a row of counts (plays, tracks, broadcasts, last played), the local badge, a copy-link button. The track list is the discography by airplay; the broadcast list is chronological with newer/older steps and a range ("Broadcasts 13–24 of 232"), each row linking to the playlist. The right rail always starts with “On this station” (first/last played, total plays, all playlists); the biography and external links follow when they exist, and then the theme's active sidebar renders so banners and widgets keep their usual place.

**Hub rows and day headings.** A playlist is one row: start time, programme name, track count and up to three track teasers, with an arrow. Day headings pair the weekday with a bold date, with the number of broadcasts on the right; section headings stand alone without an eyebrow.

**Month navigation.** Previous month, a disclosure with all months (counts included), next month. No numeric pagination; an artist page uses the same newer/older step pattern with a count.

**Search.** A real form with a visible label for screen readers, a 44 px input and a dark 44 px button. Results group artists with the matched track titles; search result pages are `noindex`.

**States.** Empty playlist: a quiet `surface-2` panel explaining that tracks will appear. Empty search: a calm message and a link to the artist index. Missing artwork: a monogram. Missing show reference: the programme name from the title, without a link. Missing duration: the cell stays empty. Out-of-range months and artists with no plays: 404.

## Do's and Don'ts

- Do let the host theme own the typeface, text colour and page width.
- Do derive every accent companion and verify AA on white and on the tint; never assume white text on an arbitrary station colour.
- Do keep real tables, captions, scopes and `<time>` elements.
- Do keep the month archive and the hour anchors; they replace pagination and make long days navigable. Keep the artist list's newer/older steps, never a numeric pager.
- Do keep the plate as the only dark object and the accent for state and links, not surfaces.
- Don't reintroduce numeric pagination, `?offset=` links or a card grid of playlists.
- Don't add an eyebrow or kicker above a heading; fold the context into the heading.
- Don't add a second brand colour, a logo, a webfont or a station name in the source.
- Don't hide the programme name or the broadcast date behind an interaction.
- Don't convey the current track, the local flag or the selected state with colour alone.
- Don't load a JavaScript framework; the page must be complete before the script runs.

## Responsive & accessibility

Container queries at 760, 560 and 420 px: the plate loses its side badge and shrinks its artwork, the artist layout stacks, the hour chips wrap, the search form stacks, the hub rows hide the end time and show only the start time, the pager hides its prev/next words but keeps accessible names. All text pairs meet WCAG AA; focus is a 2 px accent-ink ring with 2 px offset; `prefers-reduced-motion` removes transitions and smooth scrolling; the page is usable at 320 px and 200 % zoom.

## Performance

One stylesheet (about 35 kB raw, 5.7 kB gzip) and one script (about 5.2 kB raw, 1.9 kB gzip). No external requests, no fonts, no framework. A 385-track playlist is about 164 kB of HTML (9.9 kB gzip); ordinary pages are 13–19 kB. Full-page cache safe: the HTML contains no nonce or per-visitor state.

## Review

The mechanical detector returned **0 findings** over the changed web files (`impeccable detect --json --changed`), and the dependency-free suite passes 26 tests on PHP 8.6 (CI runs the same suite on 7.4 and 8.2).

The finish review used the substituted degraded reviewer, because the named Impeccable finish-reviewer agent is not installed in this harness. Its first pass returned **fix** with material findings; the batch removed the eyebrow/kicker layer, replaced the artist numeric pager with newer/older steps and a broadcast range, bounded the now marker by the track duration, added the dark-plate focus ring, made the copy control appear only when JavaScript enhances it, added configurable legacy-hub 301s, widened the artist date column, moved mobile separators to the preceding item, and corrected and labelled the preview evidence. A second pass resolved the no-JS control and the heading copy but caught a remaining fixture inconsistency; a final fixture pass aligned the artist, local-artist and archive rows with the same broadcast, and the reviewer returned **ship** for the scored fix batch.

Open, not design blockers for that verdict: the live WordPress deploy pass on both stations (theme shell/sidebar, Yoast titles/canonicals/JSON-LD/sitemap, old-hub/playlist/`?offset=` 301s, cache purge, Broadcast Archive matching), a real artist photo (evidence is monogram-only), 200 % zoom, and a real 204-playlist month (the 36-row fixture is a disclosed approximation; the page stays long by design and is grouped per day).
