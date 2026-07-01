// ==UserScript==
// @name         Scroll and Save Virtualized Images - Parallel Singles (ASCII Clean)
// @namespace    sayid-tools
// @version      1.5.0
// @description  Auto-scroll, collect images+videos in virtualized pages, save page metadata as JSON, then download images in parallel with numbered prefix.
// @match        *://*/*
// @grant        GM_download
// ==/UserScript==

(function () {
  'use strict';

  /***********************
   * Config
   ***********************/
  const SCROLL_STEP_VH = 100;
  const WAIT_AFTER_SCROLL_MS = 400;
  const EXTRA_OBSERVER_MS = 400;
  const MAX_NO_NEW_ROUNDS = 8;     // a bit higher; virtualized lists can be slow
  const MAX_STUCK_ROUNDS = 4;      // rounds where scrollTop did NOT move => stop
  const MAX_SCROLL_ROUNDS = 2000;
  const INCLUDE_CSS_BACKGROUNDS = true;
  const DOWNLOAD_CONCURRENCY = 10;
  const DOWNLOAD_DELAY_MS = 60;
  const ALLOW_PROTOCOL = /^https?:\/\//i;
  const SKIP_VIDEO_EXT = /\.(m3u8)(\?|$)/i;
  const HOST_FILTER = null;
  const DEBUG = true;              // logs scroll progress each round to console

  // Keep only the largest size of each image. Sites like Mobbin/Vercel serve
  // the same image at several widths (?w=720, ?w=1440, ?w=3024 ...). When true,
  // duplicates that differ only by size are collapsed to the biggest one.
  const ONLY_LARGEST_SIZE = true;

  // If auto-detection picks the wrong element, hardcode the real scroll
  // container here, e.g. 'main', '.feed-scroll', '#content'. null = auto.
  const SCROLL_CONTAINER_SELECTOR = null;

  // Save a metadata.json (app name, tagline, platform, rating, category,
  // logo, flows tree) alongside the images, before downloading them.
  const SAVE_METADATA = true;
  // Expand all collapsed flow accordions before reading the flows tree.
  const EXPAND_FLOWS = true;

  const log = (...a) => { if (DEBUG) console.log('[capture]', ...a); };

  /***********************
   * UI
   ***********************/
  const ui = (() => {
    const wrap = document.createElement('div');
    Object.assign(wrap.style, {
      position: 'fixed', zIndex: 999999, right: '16px', bottom: '16px',
      display: 'flex', gap: '8px', alignItems: 'center',
      fontFamily: 'ui-sans-serif, system-ui'
    });

    const btn = document.createElement('button');
    btn.textContent = 'Start Capture';
    styleBtn(btn, '#111', '#fff');

    const stopBtn = document.createElement('button');
    stopBtn.textContent = 'Stop';
    styleBtn(stopBtn, '#666', '#fff');
    stopBtn.disabled = true;

    const pill = document.createElement('span');
    Object.assign(pill.style, {
      padding: '8px 10px', borderRadius: '999px',
      background: 'rgba(0,0,0,.6)', color: '#fff',
      fontSize: '12px', display: 'none'
    });
    pill.textContent = '...';

    wrap.append(btn, stopBtn, pill);
    document.body.appendChild(wrap);
    return { btn, stopBtn, pill };
  })();

  function styleBtn(el, bg, fg) {
    Object.assign(el.style, {
      padding: '10px 14px', borderRadius: '10px', border: 'none',
      boxShadow: '0 6px 16px rgba(0,0,0,.2)', cursor: 'pointer',
      background: bg, color: fg, fontWeight: 700
    });
  }

  /***********************
   * Helpers
   ***********************/
  const sleep = (ms) => new Promise(r => setTimeout(r, ms));

  const URL_PROPS = [
    'backgroundImage', 'listStyleImage', 'borderImageSource',
    'maskImage', 'WebkitMaskImage', 'content', 'cursor'
  ];
  const URL_RE = /url\((?:'|")?(.*?)(?:'|")?\)/gi;

  function extractUrlsFromCss(val) {
    if (!val || val === 'none' || val === 'auto' || val === 'inherit') return [];
    const out = [];
    const s = String(val);
    const sets = s.matchAll(/image-set\((.*?)\)/gi);
    for (const set of sets) {
      let inner = set[1];
      let m;
      while ((m = URL_RE.exec(inner))) out.push(m[1]);
    }
    let m2;
    while ((m2 = URL_RE.exec(s))) out.push(m2[1]);
    return out;
  }

  function norm(u) {
    try { return new URL(u, location.href).href; } catch { return u; }
  }

  function uniqBy(arr, keyFn) {
    const map = new Map();
    for (const x of arr) {
      const k = keyFn(x);
      if (!map.has(k)) map.set(k, x);
    }
    return [...map.values()];
  }

  // Key includes the query string. Many CDNs put the unique image id in the
  // query (e.g. /image?id=123), so stripping it collapses every image to one
  // key and almost everything looks like a duplicate. Keep the full URL.
  function makePathKey(url) {
    const u = new URL(url);
    return u.origin + u.pathname + u.search;
  }

  // Size-related query params used by image CDNs / Next.js image proxy.
  const SIZE_PARAMS = ['w', 'width', 'h', 'height', 'q', 'quality', 'dpr'];

  // Identity of an image ignoring its size. For the Vercel/Next proxy
  // (/_next/image?url=...&w=...) the inner `url` param survives and is the
  // real identity; only the size params are stripped.
  function logicalKey(url) {
    try {
      const u = new URL(url);
      const sp = new URLSearchParams(u.search);
      SIZE_PARAMS.forEach(k => sp.delete(k));
      const rest = [...sp.entries()].sort().map(([k, v]) => k + '=' + v).join('&');
      return u.origin + u.pathname + '?' + rest;
    } catch { return url; }
  }

  // Bigger = better. Reads pixel size from the query string.
  function urlSizeScore(url) {
    try {
      const u = new URL(url);
      const p = u.searchParams;
      const w = parseInt(p.get('w') || p.get('width') || '0', 10) || 0;
      const h = parseInt(p.get('h') || p.get('height') || '0', 10) || 0;
      return Math.max(w, h);
    } catch { return 0; }
  }

  // Collapse same-image-different-size entries down to the largest one.
  function keepLargestPerImage(urls) {
    const best = new Map();
    for (const u of urls) {
      const k = logicalKey(u);
      const score = urlSizeScore(u);
      const cur = best.get(k);
      if (!cur || score > cur.score) best.set(k, { url: u, score });
    }
    return [...best.values()].map(x => x.url);
  }

  /***********************
   * Metadata extraction (Mobbin app pages)
   * Selectors confirmed from the page's data-sentry-* hooks.
   ***********************/
  const FLOW_ROOT_SEL = 'ol[data-sentry-source-file="SearchableTree.tsx"]';

  const mtxt = (el) => (el && el.textContent ? el.textContent.trim().replace(/\s+/g, ' ') : '');
  const mtexts = (sel, root = document) =>
    [...root.querySelectorAll(sel)].map(mtxt).filter(Boolean);

  function slugify(s) {
    return (s || 'app').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'app';
  }

  function extractFlowNode(li) {
    const header = li.querySelector(':scope > div');
    const nameEl = header ? header.querySelector('span.truncate') : null;
    const name = nameEl ? mtxt(nameEl) : null;
    const childOl = li.querySelector(':scope > ol');
    const children = childOl
      ? [...childOl.querySelectorAll(':scope > li')].map(extractFlowNode)
      : [];
    const node = { name };
    if (children.length) node.children = children;
    return node;
  }

  function extractFlows() {
    const root = document.querySelector(FLOW_ROOT_SEL);
    if (!root) return [];
    return [...root.querySelectorAll(':scope > li')].map(extractFlowNode);
  }

  function countFlows(arr) {
    return arr.reduce((n, x) => n + 1 + (x.children ? countFlows(x.children) : 0), 0);
  }

  async function expandAllFlows() {
    const root = document.querySelector('[data-sentry-component="SearchableTree"]')
      || document.querySelector(FLOW_ROOT_SEL);
    if (!root) return;
    for (let i = 0; i < 8; i++) {
      // one click per collapsible (dedupe by aria-controls so we don't toggle twice)
      const seen = new Set();
      const closed = [...root.querySelectorAll('button[aria-expanded="false"][aria-controls]')];
      if (!closed.length) break;
      closed.forEach(b => {
        const k = b.getAttribute('aria-controls');
        if (!seen.has(k)) { seen.add(k); try { b.click(); } catch {} }
      });
      await sleep(180);
    }
  }

  function extractMetadata() {
    const meta = { url: location.href, capturedAt: new Date().toISOString() };

    // name + tagline are combined in the h1, separated by an em/en dash
    const h1 = document.querySelector('main [data-sentry-component="Layout"] section h1')
      || document.querySelector('section h1')
      || document.querySelector('h1');
    if (h1) {
      const full = mtxt(h1);
      const parts = full.split(/\s*[—–]\s*/);
      meta.name = parts[0] ? parts[0].trim() : full;
      meta.tagline = parts.length > 1 ? parts.slice(1).join(' — ').trim() : null;
    } else {
      meta.name = document.title || null;
      meta.tagline = null;
    }

    // platform: the toggle links (Web, Site, iOS ...)
    meta.platform = mtexts('[data-sentry-component="PlatformToggle"] a');

    // rating: "3.63 (8)"
    const ratingText = mtxt(document.querySelector('[data-sentry-component="ReviewDetailModalButton"]'));
    const rm = ratingText.match(/(\d(?:\.\d+)?)\s*\(\s*(\d+)\s*\)/);
    meta.rating = rm ? parseFloat(rm[1]) : null;
    meta.ratingCount = rm ? parseInt(rm[2], 10) : null;

    // category tags
    meta.category = mtexts('[data-sentry-component="Category"] a[data-sentry-component="FilterTagLink"]');

    // app logo
    const logoImg = document.querySelector('[data-sentry-component="AppLogo"] img');
    meta.logo = logoImg ? (logoImg.currentSrc || logoImg.src || null) : null;

    // "Showing N UI elements" (best effort; may be null on some tabs)
    let uiCount = null;
    for (const el of document.querySelectorAll('p, span, div, h2')) {
      const t = mtxt(el);
      if (t.length > 60) continue;
      const m = t.match(/([\d,]+)\s+UI elements/i);
      if (m) { uiCount = parseInt(m[1].replace(/,/g, ''), 10); break; }
    }
    meta.uiElementCount = uiCount;

    // flows tree
    const flows = extractFlows();
    meta.flowCount = countFlows(flows);
    meta.flows = flows;

    return meta;
  }

  function saveJson(obj, filename) {
    const blob = new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  }

  function padCounter(n, total) {
    const digits = String(total).length;
    return String(n).padStart(digits, '0');
  }

  function fileNameFromUrl(url, counter, total, contentType) {
    const prefix = padCounter(counter, total) + '_';
    try {
      const u = new URL(url);
      const base = u.pathname.split('/').filter(Boolean).pop() || ('file-' + counter);
      let name = (base.split('?')[0].split('#')[0]) || ('file-' + counter);
      if (!/\.[a-z0-9]{2,5}$/i.test(name)) {
        const ext = guessExtFromCT(contentType) || 'jpg';
        name = name + '.' + ext;
      }
      return prefix + name;
    } catch {
      const ext = guessExtFromCT(contentType) || 'bin';
      return prefix + 'file-' + counter + '.' + ext;
    }
  }

  function guessExtFromCT(ct) {
    if (!ct) return '';
    const s = ct.toLowerCase();
    if (s.includes('image/webp')) return 'webp';
    if (s.includes('image/png')) return 'png';
    if (s.includes('image/jpeg')) return 'jpg';
    if (s.includes('image/jpg')) return 'jpg';
    if (s.includes('image/avif')) return 'avif';
    if (s.includes('image/svg')) return 'svg';
    if (s.includes('image/gif')) return 'gif';
    if (s.includes('video/mp4')) return 'mp4';
    if (s.includes('video/webm')) return 'webm';
    return '';
  }

  /***********************
   * Scroll-container detection (the important fix)
   ***********************/
  function isScrollable(el) {
    if (!el || el.nodeType !== 1) return false;
    const cs = getComputedStyle(el);
    const oy = cs.overflowY;
    return (oy === 'auto' || oy === 'scroll') && el.scrollHeight > el.clientHeight + 50;
  }

  function findScroller() {
    // 1) manual override wins
    if (SCROLL_CONTAINER_SELECTOR) {
      const el = document.querySelector(SCROLL_CONTAINER_SELECTOR);
      if (el) return el;
    }

    // 2) collect every scrollable element, score by how many images it holds
    const candidates = [];
    document.querySelectorAll('*').forEach(el => {
      if (isScrollable(el)) {
        candidates.push({
          el,
          area: el.scrollHeight - el.clientHeight,
          imgCount: el.querySelectorAll('img, video, [style*="background"]').length
        });
      }
    });

    // 3) include the page-level scroller as a fallback candidate
    const docEl = document.scrollingElement || document.documentElement;
    if (docEl && docEl.scrollHeight > docEl.clientHeight + 50) {
      candidates.push({
        el: docEl,
        area: docEl.scrollHeight - docEl.clientHeight,
        imgCount: document.images.length
      });
    }

    if (!candidates.length) return docEl;

    // prefer the container that actually holds the gallery items;
    // tie-break by largest scrollable area
    candidates.sort((a, b) => (b.imgCount - a.imgCount) || (b.area - a.area));
    return candidates[0].el;
  }

  function viewportOf(scroller) {
    // page scroller -> window height; inner container -> its clientHeight
    const docEl = document.scrollingElement || document.documentElement;
    if (scroller === docEl || scroller === document.body) return window.innerHeight;
    return scroller.clientHeight || window.innerHeight;
  }

  function scrollByDelta(scroller, delta) {
    const before = scroller.scrollTop;
    scroller.scrollTop = before + delta;
    // NOTE: no scrollIntoView nudge here. In virtualized lists it teleports to
    // the end of the rendered window and the middle items never mount/scan.
    return before;
  }

  /***********************
   * Collectors
   ***********************/
  function collectFromRoot(root) {
    const urls = new Set();
    const all = root.querySelectorAll('*');

    all.forEach(el => {
      if (el.tagName === 'IMG') {
        if (el.currentSrc) urls.add(norm(el.currentSrc));
        if (el.src) urls.add(norm(el.src));
        if (el.srcset) {
          el.srcset.split(',').forEach(s => {
            const u = s.trim().split(' ')[0];
            if (u) urls.add(norm(u));
          });
        }
      } else if (el.tagName === 'SOURCE') {
        const src = el.getAttribute('src');
        if (src) urls.add(norm(src));
        const sset = el.getAttribute('srcset');
        if (sset) {
          sset.split(',').forEach(s => {
            const u = s.trim().split(' ')[0];
            if (u) urls.add(norm(u));
          });
        }
      } else if (el.tagName === 'VIDEO') {
        if (el.poster) urls.add(norm(el.poster));
        const src = el.getAttribute('src');
        if (src) urls.add(norm(src));
        if (el.src) urls.add(norm(el.src));
        if (el.currentSrc) urls.add(norm(el.currentSrc));
      }
    });

    if (INCLUDE_CSS_BACKGROUNDS) {
      all.forEach(el => {
        const cs = getComputedStyle(el);
        URL_PROPS.forEach(p => extractUrlsFromCss(cs[p]).forEach(u => urls.add(norm(u))));
        ['::before', '::after'].forEach(pseudo => {
          const cps = getComputedStyle(el, pseudo);
          URL_PROPS.forEach(p => extractUrlsFromCss(cps[p]).forEach(u => urls.add(norm(u))));
        });
      });
    }

    all.forEach(el => {
      if (el.shadowRoot) collectFromRoot(el.shadowRoot).forEach(u => urls.add(u));
    });

    return urls;
  }

  function collectAllNow() {
    const out = new Set([...collectFromRoot(document)]);
    document.querySelectorAll('iframe').forEach(ifr => {
      try {
        if (ifr.contentDocument) collectFromRoot(ifr.contentDocument).forEach(u => out.add(u));
      } catch {}
    });
    return [...out]
      .filter(u => ALLOW_PROTOCOL.test(u))
      .filter(u => !SKIP_VIDEO_EXT.test(u))
      .filter(u => {
        if (!HOST_FILTER) return true;
        try { return HOST_FILTER(new URL(u).hostname); } catch { return true; }
      });
  }

  /***********************
   * Downloading
   ***********************/
  function gmDownload(url, name) {
    return new Promise((resolve, reject) => {
      GM_download({
        url: url, name: name, saveAs: false,
        onload: resolve, onerror: reject
      });
    });
  }

  async function downloadOne(url, index, total) {
    const counter = index + 1;
    try {
      const name = fileNameFromUrl(url, counter, total, '');
      await gmDownload(url, name);
      return { ok: true, url, name };
    } catch (e) {
      console.warn('Download failed:', url, e);
      return { ok: false, url, error: String(e && e.message ? e.message : e) };
    }
  }

  function runWithConcurrency(items, limit, worker, stepDelay) {
    const results = new Array(items.length);
    let nextIndex = 0;
    let active = 0;
    return new Promise((resolve) => {
      const spawn = async () => {
        while (active < limit && nextIndex < items.length) {
          const i = nextIndex++;
          active++;
          if (stepDelay) await sleep(stepDelay);
          Promise.resolve(worker(items[i], i, items.length))
            .then(r => { results[i] = r; })
            .catch(e => { results[i] = { error: e }; })
            .finally(() => {
              active--;
              if (nextIndex < items.length) spawn();
              else if (active === 0) resolve(results);
            });
        }
      };
      spawn();
    });
  }

  /***********************
   * Main crawler
   ***********************/
  let running = false;

  ui.btn.addEventListener('click', async () => {
    if (running) return;
    running = true;
    ui.btn.disabled = true;
    ui.stopBtn.disabled = false;
    ui.pill.style.display = 'inline-block';

    let round = 0;
    let noNewStreak = 0;
    let stuckStreak = 0;
    const seenKey = new Set();
    const collected = [];

    let scroller = findScroller();
    log('scroller chosen:', scroller.tagName, '| class:', scroller.className,
        '| scrollHeight:', scroller.scrollHeight, '| clientHeight:', scroller.clientHeight);

    const observer = new MutationObserver(() => {});
    observer.observe(document, { attributes: true, childList: true, subtree: true });

    while (running && round < MAX_SCROLL_ROUNDS) {
      round++;
      ui.pill.textContent = 'Round ' + round + ' - scanning...';

      const current = collectAllNow();
      const uniqPath = uniqBy(current, (u) => {
        try { return makePathKey(u); } catch { return u; }
      });

      const fresh = [];
      for (const u of uniqPath) {
        let key;
        try { key = makePathKey(u); } catch { key = u; }
        if (!seenKey.has(key)) {
          seenKey.add(key);
          fresh.push(u);
        }
      }

      if (fresh.length) {
        collected.push(...fresh);
        noNewStreak = 0;
        ui.pill.textContent = 'Round ' + round + ' - new ' + fresh.length + ' (total ' + collected.length + ')';
      } else {
        noNewStreak++;
      }

      // perform the scroll and measure whether it actually moved
      const vh = viewportOf(scroller);
      const delta = Math.max(64, Math.floor(vh * (SCROLL_STEP_VH / 100)));
      const before = scrollByDelta(scroller, delta);

      await sleep(WAIT_AFTER_SCROLL_MS);
      await sleep(EXTRA_OBSERVER_MS);

      const after = scroller.scrollTop;
      const moved = after - before > 1;
      const atBottom = after + scroller.clientHeight >= scroller.scrollHeight - 2;

      log(`round ${round} | scrollTop ${before} -> ${after} (moved=${moved}, atBottom=${atBottom}) | fresh=${fresh.length} total=${collected.length}`);

      if (!moved) {
        // The current scroller didn't budge. Re-detect: the real one may have
        // changed, or auto-detection picked the wrong element.
        const re = findScroller();
        if (re !== scroller) {
          log('scroller did not move; switching to:', re.tagName, re.className);
          scroller = re;
          stuckStreak = 0;
        } else {
          stuckStreak++;
          log('scroller still stuck, streak =', stuckStreak);
        }
      } else {
        stuckStreak = 0;
      }

      if (atBottom) {
        ui.pill.textContent = 'At bottom - waiting for late loads...';
        await sleep(WAIT_AFTER_SCROLL_MS + EXTRA_OBSERVER_MS);
        const newCheck = collectAllNow().filter(u => {
          try { return !seenKey.has(makePathKey(u)); } catch { return true; }
        });
        if (!newCheck.length && !moved) {
          log('at bottom and no late loads -> done');
          break;
        }
      }

      if (stuckStreak >= MAX_STUCK_ROUNDS && !fresh.length) {
        ui.pill.textContent = 'Scroll stuck (' + stuckStreak + ') and no new items - stopping.';
        log('STOP: scroll stuck. The detected container may be wrong - set SCROLL_CONTAINER_SELECTOR.');
        break;
      }

      if (noNewStreak >= MAX_NO_NEW_ROUNDS) {
        ui.pill.textContent = 'No new items for ' + noNewStreak + ' rounds - stopping.';
        log('STOP: no new items for', noNewStreak, 'rounds.');
        break;
      }
    }

    observer.disconnect();
    ui.stopBtn.disabled = true;

    if (SAVE_METADATA) {
      try {
        ui.pill.textContent = 'Extracting page metadata...';
        if (EXPAND_FLOWS) await expandAllFlows();
        const meta = extractMetadata();
        const fname = slugify(meta.name) + '_metadata.json';
        saveJson(meta, fname);
        log('metadata saved:', fname, '| flows:', meta.flowCount, '| name:', meta.name);
      } catch (e) {
        log('metadata extraction failed:', e);
      }
    }

    if (!collected.length) {
      ui.pill.textContent = SAVE_METADATA ? 'Metadata saved. No images found.' : 'No files found.';
      ui.btn.disabled = false;
      running = false;
      return;
    }

    let toDownload = collected;
    if (ONLY_LARGEST_SIZE) {
      const before = toDownload.length;
      toDownload = keepLargestPerImage(toDownload);
      log('size-dedup: ' + before + ' -> ' + toDownload.length + ' (kept largest per image)');
    }

    ui.pill.textContent = 'Downloading ' + toDownload.length + ' files...';
    const results = await runWithConcurrency(
      toDownload,
      DOWNLOAD_CONCURRENCY,
      downloadOne,
      DOWNLOAD_DELAY_MS
    );

    const ok = results.filter(r => r && r.ok).length;
    const fail = results.length - ok;
    ui.pill.textContent = 'Done - saved ' + ok + ', failed ' + fail;
    log('done. saved', ok, 'failed', fail, 'total collected', collected.length);
    ui.btn.disabled = false;
    running = false;
  });

  ui.stopBtn.addEventListener('click', () => {
    ui.stopBtn.disabled = true;
    ui.btn.disabled = false;
    ui.pill.textContent = 'Stopped';
    running = false;
  });
})();