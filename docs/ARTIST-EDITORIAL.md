# Editing artist information

The public artist page is the canonical landing page for an artist. Play counts,
tracks and broadcasts are derived automatically from playlist meta; the photo,
biography, links and local flag are **editorial term meta** and are never
overwritten by `wp wppl rebuild`.

## Where to edit

Open the artist taxonomy screen in WordPress admin:

```
/wp-admin/edit-tags.php?taxonomy=playlist_artist&post_type=playlist
```

The Settings → WordPress Playlists screen links to the same artist screen. The
taxonomy is intentionally not a separate top-level menu item.

Search for the artist by name, open the term, fill the fields and click
**Update**. The left list shows the **Plays** count and **Local** state;
clicking the play count opens the public artist page.

## Fields

| Field | Shown on | Notes |
|---|---|---|
| Photo | Artist hero and local cards | Media Library image, ideally square and at least 300×300 px. Use an existing image; do not hotlink or download third-party images. |
| Biography | Right rail “About …” | HTML is allowed through the normal editor; keep it factual and reasonably short. |
| Website / Instagram / Spotify / YouTube | Right rail “Listen and follow” | Full `https://` URLs. Empty fields are omitted. |
| Local artist | “Local” badge, hub local section, artist index | Only for artists with a genuine regional connection. This is an editorial claim, not automatic. |

The artist **name** is the canonical identity. Renaming is possible but changes
the displayed name and may change the slug; prefer correcting a typo only.

## Order of work

1. Start with the artists the station actually wants to promote: local acts,
   programme makers, and frequently played regional artists.
2. Mark **Local artist** first; that immediately populates the local sections.
3. Add the photo, a short biography and the artist's own links.
4. Use the public artist page search and the hub to verify the result.
5. Never invent a biography, award or relationship. If information is not known,
   leave the field empty; the page remains complete without it.

## Caching and refresh

Saving an artist term now bumps the derived cache version and purges:

- the artist page itself;
- the artist index and the matching letter page;
- the hub (local section and counts).

External full-page caches still need their normal purge adapter. If a page cache
plugin is not detected, trigger the documented `wordpress_playlists_purge_url`
action or purge the URL manually. No rebuild is required for editorial fields.

## What not to change

- Do not edit the derived term meta (`_wppl_key`, `_wppl_play_count`,
  `_wppl_tracks`, `_wppl_first_played`, `_wppl_last_played`) by hand.
- Do not delete artist terms to “refresh” them; playlists and bios would lose
  their archive and editorial data.
- Do not change the `playlist_artist` taxonomy slug or rewrite bases without a
  redirect plan; URLs are public and indexed.

## Useful checks

```sh
# Count artists and inspect one term
wp term list playlist_artist --fields=term_id,name,count --number=20
wp term meta list <term_id>

# Verify the public page and the index after editing
curl -I https://example.com/artiest/<slug>/
curl -I https://example.com/artiesten/
```

The live sites use one station per site; the same editorial workflow applies on
both, with their own artist screens and media libraries.
