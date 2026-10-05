"""Real-browser check of the uploaded lecture video: what a student actually sees, not just whether bytes arrive.

Opens VIEWERS headless Google Chrome windows (one synthetic student each), plays the lecture in Moodle's own
player for SECONDS and reports per viewer: page load, time from pressing play to the first picture, freezes
(the player's "waiting" events: buffering spinner) and their total length, decoded resolution, dropped frames,
any media error, and frame timing from requestVideoFrameCallback (fires for every frame actually painted):
time between painted frames (p50/p95/p99/max; 40 ms is perfect at 25 fps), late frames (> 80 ms, a visible
stutter; headless Chrome paints at ~30 Hz, so 33/67 ms gaps are normal), frames skipped, how far playback fell behind the clock, and the network
time to first byte of the video requests (Resource Timing). Run it alone for a baseline, then again while a Locust video stage holds the load.

  .venv/bin/python browser_video_check.py --viewers 5 --seconds 120
  .venv/bin/python browser_video_check.py --viewers 3 --seconds 90 --seek --first-user 991 --headed

Needs `pip install playwright` and Google Chrome (Playwright's own Chromium has no H.264/AAC, so it cannot
play MP4 lectures). Results: results/<timestamp>-browser/browser_video.json + screenshot of viewer 1.
"""
import argparse
import asyncio
import json
import os
import time

from playwright.async_api import async_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
VIDEO = json.load(open(os.path.join(HERE, "setup", "video.json")))

# Listens on the player's <video> element (Video.js keeps the original element as .vjs-tech) and starts it.
INSTRUMENT = """async () => {
  const v = document.querySelector('video.vjs-tech') || document.querySelector('video');
  const s = window.__vs = {t0: performance.now(), first: null, waits: 0, waitMs: 0, _w: null, seekMs: null,
                           _seek: null, events: []};
  const on = (e, f) => v.addEventListener(e, () => { const t = performance.now(); s.events.push([e, Math.round(t - s.t0)]); f && f(t); });
  on('playing', t => { if (s.first === null) s.first = t - s.t0;
                       if (s._w !== null) { s.waitMs += t - s._w; s._w = null; } });
  on('waiting', t => { if (s.first !== null && s._w === null && s._seek === null) { s.waits++; s._w = t; } });
  on('seeking', t => { s._seek = t; });
  on('seeked', t => { if (s._seek !== null) { s.seekMs = t - s._seek; s._seek = null; } });
  on('stalled'); on('error');
  s.frames = [];  // [wall ms, media s, presentedFrames] per painted frame
  if (v.requestVideoFrameCallback) {
    const cb = (now, md) => { s.frames.push([now, md.mediaTime, md.presentedFrames]);
                              v.requestVideoFrameCallback(cb); };
    v.requestVideoFrameCallback(cb);
  }
  v.muted = true;  // browsers only autoplay muted media
  try { await v.play(); return 'ok'; } catch (e) { return String(e); }
}"""

STATS = """() => {
  const pct = (a, p) => { if (!a.length) return null; const b = [...a].sort((x, y) => x - y);
                          return +b[Math.min(b.length - 1, Math.round(p / 100 * (b.length - 1)))].toFixed(1); };
  const frameStats = f => {
    const gaps = [];
    let skipped = 0, wall = 0, media = 0;
    for (let i = 1; i < f.length; i++) {
      const dw = f[i][0] - f[i - 1][0], dm = f[i][1] - f[i - 1][1];
      if (dm < 0 || dm > 2) continue;  // a seek, not playback
      gaps.push(dw);
      skipped += Math.max(0, f[i][2] - f[i - 1][2] - 1);
      wall += dw; media += dm * 1000;
    }
    return {frames_painted: f.length, frame_gap_p50_ms: pct(gaps, 50), frame_gap_p95_ms: pct(gaps, 95),
            frame_gap_p99_ms: pct(gaps, 99), frame_gap_max_ms: gaps.length ? Math.round(Math.max(...gaps)) : null,
            late_frames: gaps.filter(g => g > 80).length, frames_skipped: skipped,
            playback_lag_ms: Math.round(Math.max(0, wall - media))};
  };
  const netStats = () => {
    const r = performance.getEntriesByType('resource').filter(e => e.name.includes('/pluginfile.php/') && e.name.match(/\\.(mp4|webm|m4v)/i));
    const ttfb = r.filter(e => e.responseStart > 0).map(e => e.responseStart - e.startTime);
    return {video_requests: r.length, video_ttfb_p50_ms: pct(ttfb, 50), video_ttfb_max_ms: ttfb.length ? Math.round(Math.max(...ttfb)) : null};
  };
  const v = document.querySelector('video.vjs-tech') || document.querySelector('video'), s = window.__vs;
  const q = v.getVideoPlaybackQuality ? v.getVideoPlaybackQuality() : {};
  return {first_frame_ms: s.first === null ? null : Math.round(s.first), freezes: s.waits,
          freeze_ms: Math.round(s.waitMs + (s._w !== null ? performance.now() - s._w : 0)),
          seek_ms: s.seekMs === null ? null : Math.round(s.seekMs), played_s: +v.currentTime.toFixed(1),
          width: v.videoWidth, height: v.videoHeight, dropped: q.droppedVideoFrames, frames: q.totalVideoFrames,
          error: v.error ? v.error.code + ' ' + (v.error.message || '') : null, events: s.events.slice(0, 40),
          ...frameStats(s.frames), ...netStats()};
}"""


async def viewer(browser, n, args, out):
    username = f"{VIDEO['prefix']}{n:04d}"
    ctx = await browser.new_context(ignore_https_errors=True, viewport={"width": 1280, "height": 800})
    page = await ctx.new_page()
    res = {"user": username}
    try:
        await page.goto(f"{args.host}/login/index.php")
        await page.fill("#username", username)
        await page.fill("#password", VIDEO["password"])
        await page.click("#loginbtn")
        await page.wait_for_url("**/my/**")
        t = time.time()
        await page.goto(f"{args.host}/mod/resource/view.php?id={VIDEO['video']}")
        await page.wait_for_selector("video", state="attached", timeout=20000)
        await page.wait_for_timeout(1000)  # let Video.js finish setting up the player
        res["page_load_ms"] = round((time.time() - t) * 1000)
        res["play"] = await page.evaluate(INSTRUMENT)
        if args.seek:
            await page.wait_for_timeout(args.seconds * 500)
            await page.evaluate("() => { const v = document.querySelector('video'); v.currentTime = v.duration * 0.7; }")
            await page.wait_for_timeout(args.seconds * 500)
        else:
            await page.wait_for_timeout(args.seconds * 1000)
        res.update(await page.evaluate(STATS))
        if n == args.first_user:
            await page.screenshot(path=os.path.join(out, "viewer1.png"))
    except Exception as e:  # noqa: BLE001
        res["error"] = f"{type(e).__name__}: {e}"[:200]
    await ctx.close()
    return res


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--viewers", type=int, default=5)
    ap.add_argument("--seconds", type=int, default=120)
    ap.add_argument("--first-user", type=int, default=991, help="lt26_<n> accounts used (keep apart from Locust's)")
    ap.add_argument("--seek", action="store_true", help="seek to 70%% half-way through")
    ap.add_argument("--headed", action="store_true")
    ap.add_argument("--channel", default="chrome")
    ap.add_argument("--out", help="results directory (default results/<timestamp>-browser)")
    ap.add_argument("--host", default=os.environ.get("HOST", "https://192.168.30.239"))
    args = ap.parse_args()
    out = args.out or os.path.join(HERE, "results", time.strftime("%Y%m%d-%H%M") + "-browser")
    os.makedirs(out, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch(channel=args.channel, headless=not args.headed,
                                          args=["--autoplay-policy=no-user-gesture-required"])
        results = await asyncio.gather(*(viewer(browser, args.first_user + i, args, out) for i in range(args.viewers)))
        await browser.close()
    json.dump(results, open(os.path.join(out, "browser_video.json"), "w"), indent=2)

    print(f"{args.viewers} viewers x {args.seconds}s -> {out}\n")
    print("| user | page load ms | press play → picture ms | freezes | frozen s | seek ms | played s | resolution "
          "| dropped frames | frame gap p50/p95/p99/max ms | late frames | skipped | behind clock ms "
          "| video TTFB p50/max ms | error |")
    print("|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|")
    for r in results:
        print(f"| {r['user']} | {r.get('page_load_ms', '–')} | {r.get('first_frame_ms', '–')} | {r.get('freezes', '–')} | "
              f"{(r.get('freeze_ms') or 0) / 1000:.1f} | {r.get('seek_ms') if r.get('seek_ms') is not None else '–'} | "
              f"{r.get('played_s', '–')} | {r.get('width', '?')}x{r.get('height', '?')} | "
              f"{r.get('dropped', '–')}/{r.get('frames', '–')} | {r.get('frame_gap_p50_ms')}/{r.get('frame_gap_p95_ms')}/"
              f"{r.get('frame_gap_p99_ms')}/{r.get('frame_gap_max_ms')} | {r.get('late_frames', '–')} | "
              f"{r.get('frames_skipped', '–')} | {r.get('playback_lag_ms', '–')} | "
              f"{r.get('video_ttfb_p50_ms')}/{r.get('video_ttfb_max_ms')} | {r.get('error') or ('' if r.get('play') == 'ok' else r.get('play', ''))} |")


if __name__ == "__main__":
    asyncio.run(main())
