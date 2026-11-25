# Preview harness

Development-only. The plugin never loads anything from this folder.

## What it is

A tiny WordPress stub plus realistic fixtures that render the **real** public
views (the same PHP renderer classes that run on a site) with the real
stylesheet and script. It exists so the design can be inspected at 1440 and
390 px, with both station accents, before deploying.

## Run

```sh
php -S 127.0.0.1:8099 -t .
```

Open <http://127.0.0.1:8099/preview/?view=playlist>. The `preview` bar switches
views, station accent and column width; add `chrome=0` for clean captures.

Views: `playlist`, `long`, `hub`, `month`, `artist`, `local`, `index`, `letter`,
`search`, `embed`, `empty`.

## Files

| File | Purpose |
|---|---|
| `bootstrap.php` | Minimal WordPress function stubs and the class includes. |
| `translate.php` | The Dutch interface strings used in the preview (the shipped MO lives in `languages/`). |
| `fixtures.php` | Realistic models: a normal show, a 385-track Non Stop day, artists, a local artist, hub, month, index, letter, search and embed states. |
| `index.php` | The preview shell and router. |

The fixtures deliberately use the two live stations and their programme
imagery for realism; this folder is not part of the plugin and is never loaded
by WordPress. The shipped plugin and all its classes remain station-agnostic.
