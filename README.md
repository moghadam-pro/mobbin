# Mobbin Mirror 🎨

A lightweight, self-hosted mirror of UI screenshot galleries inspired by [Mobbin](https://mobbin.com).  
Built for quick offline reference and personal design research — no subscription required.

## What is this?

Mobbin is one of the best UI/UX reference libraries out there, but sometimes you just want a
faster, distraction-free way to browse screenshots you care about.  
This project is a curated, personal subset of that — organized the way _I_ think about UI patterns.

## Features

- 📱 Browse UI screenshots organized by app / category / pattern
- ⚡ Fast and lightweight — pure PHP, HTML, CSS, JS
- 🗂 Simple folder-based structure, easy to extend
- 🔍 Basic search and filter
- 🌐 Self-hostable on any PHP server

## Tech Stack

- PHP (backend / file serving)
- HTML / CSS / Vanilla JS (frontend)

## Disclaimer

This project is for **personal and educational use only**.  
All UI screenshots are the property of their respective owners.  
This is not affiliated with or endorsed by Mobbin.

## Status

🚧 Work in progress — adding more screens and improving filters over time.

# Scroll and Save Virtualized Images

A Tampermonkey userscript that auto-scrolls a virtualized image/video gallery
(the kind that only renders items currently in the viewport, like Mobbin's
app pages), collects every image and video it can find as it scrolls, and
downloads them locally as numbered files. Optionally, it also saves a
`metadata.json` describing the page (app name, tagline, platform, rating,
category, logo, and the full "Flows" sidebar tree).

Built for logged-in-only, member-gated gallery pages where images load
progressively as you scroll.

## Features

- **Auto-scroll with stall detection** - scrolls the correct container (auto
  detected, or hardcoded via `SCROLL_CONTAINER_SELECTOR`), re-detects the
  scroll container if it changes, and stops when nothing new loads for a
  while or the scroll position gets stuck.
- **Virtualized-list aware** - re-scans the DOM after every scroll step, so
  images that only exist in the DOM while they're near the viewport are
  captured before they get unmounted.
- **Largest-size-only downloads** - when a site serves multiple resolutions
  of the same image (`srcset`, `<picture>`, CSS `image-set()`), only the
  largest one is downloaded. This is done using the standard `NNNw` / `NNNx`
  size descriptors defined by `srcset`/`image-set`, so it works the same way
  on any site - it does not depend on a particular CDN's query-parameter
  naming convention.
- **Images, videos, and CSS backgrounds** - covers `<img>`, `<picture>`/
  `<source>`, `<video>` (including `poster`), and CSS background/mask
  images (including `::before`/`::after` pseudo-elements), plus same-origin
  `<iframe>` documents and open shadow roots.
- **Parallel downloads** - downloads with a configurable concurrency limit
  via `GM_download`, with a small stagger delay to avoid hammering the host.
- **Numbered, extension-safe filenames** - `0001_photo.jpg`, `0002_shot.png`,
  etc., with a best-effort extension guess when the URL has none.
- **Page metadata + Flows tree (optional, Mobbin-oriented)** - as the very
  last step of a run, the script opens the page's "Flows" tab (a link whose
  `href` ends in `/flows`), waits for the sidebar tree to render, expands
  every collapsed node, and saves a `metadata.json` with the app's name,
  tagline, platform tags, rating, categories, logo URL, and the full nested
  flows tree.

## Installation

1. Install [Tampermonkey](https://www.tampermonkey.net/) (Chrome, Firefox,
   Edge, Safari all supported).
2. Open Tampermonkey's dashboard -> **Create a new script**.
3. Delete the placeholder content and paste in the contents of
   [`scroll-and-save-images.user.js`](./scroll-and-save-images.user.js).
4. Save (`Ctrl+S` / `Cmd+S`).
5. Make sure downloads for the target site aren't blocked by your browser's
   "ask where to save each file" setting, or you'll get a save dialog per
   file. In Chrome: `chrome://settings/downloads` -> turn off "Ask where to
   save each file before downloading".

The script runs on every page (`@match *://*/*`) and only shows its small
floating control panel (**Start Capture** / **Stop**) - it does nothing
until you click **Start Capture**.

## Usage

1. Log in and open the gallery page you want to capture.
2. Click **Start Capture** (bottom-right floating button).
3. The script scrolls down automatically, collecting new images/videos each
   round. Progress ("Round N - new X (total Y)") is shown in the pill next
   to the buttons; detailed per-round logs are in the browser console
   (`[capture] ...`).
4. When scrolling stops (reached the bottom with no more late loads, or no
   new items for several rounds, or the scroll position is stuck), the
   script:
   1. Deduplicates to the largest version of each image and downloads
      everything in parallel.
   2. Opens the Flows tab (if present), waits for the sidebar tree, expands
      it, and saves `<app-name>_metadata.json`.
5. Click **Stop** at any time to cancel a run early (already-collected items
   up to that point are still processed).

## Configuration

All options are constants near the top of the script:

| Constant                    | Default                                            | Purpose                                                                                                         |
| --------------------------- | -------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `SCROLL_STEP_VH`            | `100`                                              | How far to scroll per round, as % of the container's viewport height.                                           |
| `WAIT_AFTER_SCROLL_MS`      | `400`                                              | Pause after each scroll before scanning the DOM.                                                                |
| `EXTRA_OBSERVER_MS`         | `400`                                              | Extra pause to let lazy-loaded content settle.                                                                  |
| `MAX_NO_NEW_ROUNDS`         | `8`                                                | Stop after this many consecutive rounds with zero new items.                                                    |
| `MAX_STUCK_ROUNDS`          | `4`                                                | Stop after this many consecutive rounds where the scroll position didn't move.                                  |
| `MAX_SCROLL_ROUNDS`         | `2000`                                             | Hard cap on total rounds, as a safety net.                                                                      |
| `INCLUDE_CSS_BACKGROUNDS`   | `true`                                             | Also scan CSS `background-image`/`mask-image`/etc.                                                              |
| `DOWNLOAD_CONCURRENCY`      | `10`                                               | Parallel downloads at once.                                                                                     |
| `DOWNLOAD_DELAY_MS`         | `60`                                               | Stagger delay between starting downloads.                                                                       |
| `SKIP_VIDEO_EXT`            | `/\.(m3u8)(\?\|$)/i`                               | URL patterns to always ignore (e.g. HLS manifests).                                                             |
| `HOST_FILTER`               | `null`                                             | Optional `(hostname) => boolean` to restrict which hosts to download from.                                      |
| `ONLY_LARGEST_SIZE`         | `true`                                             | Enable the "keep only the biggest version" safety-net pass (see below).                                         |
| `SIZE_PARAMS`               | see script                                         | Fallback list of query-param names treated as size/quality knobs when comparing two URLs without `srcset` info. |
| `SCROLL_CONTAINER_SELECTOR` | `null`                                             | Force a specific scroll container instead of auto-detecting.                                                    |
| `SAVE_METADATA`             | `true`                                             | Enable the Flows-tab + `metadata.json` step.                                                                    |
| `EXPAND_FLOWS`              | `true`                                             | Auto-expand every collapsed node in the flows tree before reading it.                                           |
| `FLOW_ROOT_SEL`             | `ol[data-sentry-source-file="SearchableTree.tsx"]` | Selector for the flows sidebar tree root.                                                                       |
| `FLOWS_WAIT_TIMEOUT_MS`     | `8000`                                             | How long to wait for the flows tree to render after opening the tab.                                            |
| `DEBUG`                     | `true`                                             | Verbose `[capture]` logs in the console.                                                                        |

## How "largest size only" works

Sites that serve responsive images give the browser several URLs to choose
from via `srcset` (e.g. `photo-400.jpg 400w, photo-1200.jpg 1200w`) or CSS
`image-set()` (e.g. `image-set(url(a.png) 1x, url(b.png) 2x)`). The browser
then picks whichever candidate best fits the current viewport/DPR - which is
often a **smaller** one, to save bandwidth.

The script reads the full candidate list itself and always keeps the
candidate with the highest declared width (`NNNw`) or density (`NNNx`)
descriptor, before that URL is ever added to the collected list. Since this
relies on the `srcset`/`image-set` syntax itself (a web standard) rather than
a specific CDN's query-parameter naming, it works the same way regardless of
how a particular site's image service is configured.

As a secondary safety net, a final pass (`ONLY_LARGEST_SIZE`) also collapses
any remaining same-image duplicates that had no `srcset` at all (e.g. the
same photo embedded twice via plain `<img src>` at two different explicit
sizes), using recorded width hints plus the `SIZE_PARAMS` name list. If a
particular site still produces extra sizes after this, add that site's size
query-param name to `SIZE_PARAMS`.

## How the Flows metadata step works

Some app pages don't have their sidebar "Flows" tree in the DOM until you're
actually on the Flows tab. As the last step of a run, the script:

1. Checks whether the flows tree (`FLOW_ROOT_SEL`) is already present.
2. If not, looks for an `<a href="...flows">` link that lives inside (or
   near) an element whose text contains "Flows", and clicks it.
3. Waits (up to `FLOWS_WAIT_TIMEOUT_MS`) for the tree to appear.
4. Expands every collapsed node.
5. Extracts app name, tagline, platform tags, rating, categories, logo URL,
   and the full nested flows tree, and saves it as
   `<slugified-app-name>_metadata.json`.

If no Flows tab is found, the script logs it and still saves whatever
metadata is available on the current page rather than failing the run.

## Output

- Images/videos: `0001_<original-filename>.<ext>`, `0002_...`, etc., zero
  padded to match the total count, saved via your browser's normal download
  location.
- Metadata: `<app-name-slug>_metadata.json`, for example:

```json
{
  "url": "https://mobbin.com/apps/example-app/...",
  "capturedAt": "2026-07-01T12:00:00.000Z",
  "name": "Example App",
  "tagline": "A short description",
  "platform": ["iOS", "Web"],
  "rating": 4.5,
  "ratingCount": 120,
  "category": ["Productivity", "Utilities"],
  "logo": "https://.../logo.png",
  "uiElementCount": 342,
  "flowCount": 18,
  "flows": [
    {
      "name": "Onboarding",
      "children": [{ "name": "Welcome" }, { "name": "Sign up" }]
    },
    { "name": "Home" }
  ]
}
```

## Known limitations

- Metadata extraction (`extractMetadata`) targets Mobbin's current DOM
  structure (`data-sentry-*` hooks). If Mobbin changes its markup, those
  selectors may need updating. Image/video collection and the size dedup
  logic are fully generic and not tied to any specific site.
- `GM_download` downloads go through your browser's normal download
  pipeline; very large batches may be rate-limited or throttled by the
  browser itself, independent of `DOWNLOAD_CONCURRENCY`.
- The script assumes same-origin `<iframe>`s for the iframe-scanning step;
  cross-origin iframes are silently skipped (browsers block reading them).

## Version history

- **1.6.0**
  - Largest-size-only dedup now works generically on any site: `srcset`
    (`NNNw`/`NNNx`) and CSS `image-set()` descriptors are parsed at
    collection time and only the best candidate is kept, instead of relying
    on a fixed set of known CDN query-param names.
  - The metadata step is now the last step of a run: it actively opens the
    page's Flows tab (`a[href$="/flows"]` inside/near an element whose text
    says "Flows"), waits for the sidebar tree to render, then extracts and
    saves `metadata.json`.
- **1.5.0** - Structured metadata (app name, tagline, platform, rating,
  category, logo, flows tree) alongside images; companion analyzer script
  for DOM selector discovery.
- **1.2.0** - Added video URL collection (`<video src>`/`<source>`) and
  sequential zero-padded filename prefixing.

## License

Personal tool, provided as-is - use at your own discretion and in
accordance with the terms of service of any site you run it on.
