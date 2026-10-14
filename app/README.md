# Oroma TV — mobile app

Expo SDK 57 (React Native, JavaScript) app for **iOS and Android**. Built mainly for **watching the live TV stream and
radio**, plus previous shows and the news. It reads from the PHP API in [`../api`](../api) (`/api/v1`), which uses the same
database as the website — so anything you publish in the admin appears in the app.

Same stack and conventions as the FaithFellows app: Expo Router, Axios, `useFetch`/`usePaged`, `StateView`, theme constants.

## What is in the app

| Tab | What it does |
|---|---|
| **Home** | "Oroma TV is live" card, featured-stories carousel, category chips, latest news, previous shows, trending |
| **News** | Search + category filter, endless list of stories |
| **Live** (middle button) | Whatever is on air, decided in **Admin → Streams & Live**: a scheduled programme or movie premiere (it takes over by itself at its start time — and **everyone is at the same second**, like TV), or — when nothing is live — the **off-air playlist** on a loop. Shows how many people are watching, a **Share** button, a small **Listen to Q FM** button, a "Next live" countdown and the programme guide ("Later on"); radio and extra video channels sit below. YouTube plays in our own player with **Back 10s · Stop · Forward 10s · Fullscreen** (fullscreen turns the phone sideways) and a **LIVE** chip / **Jump to live** button. Keeps the screen awake while you watch |
| **Shows** | The library made to browse: a featured show on top, an **on air / next premiere** banner, a **Premieres** rail, a **Movie night** rail, filter chips (All / Shows / Movies) and the grid; tap to play inside the app |
| **More** | Contact, About, social profiles, website, server-connection test |

Pushed screens: **Story** (article, tags, share, comments, related stories), **Show** (YouTube player, share), **Premiere** (countdown to a programme or movie, Add to calendar, Share — opens the Live tab by itself when it starts), **Contact**, **About**.

**Q FM** — the small *Listen to Q FM* button (Live tab) reads the station's live stream and what is playing from the server and plays it; a small player bar sits above the tabs while it plays, it keeps playing with the screen locked, and the video is muted (the picture keeps running, so you do not fall out of sync) until you stop the radio.

## Run it

You need Node 20+, XAMPP running (Apache + MySQL, with the `oroma_tv` database imported) and **Expo Go** on your phone.

```bash
cd app
npm install
cp .env.example .env      # optional — "auto" already works
npx expo start            # scan the QR code with Expo Go
```

- The app finds the API by itself: `http://<this computer's address>/oromatvnew/api`. Your phone and computer must be on the **same Wi-Fi**.
- Check the connection from the app: **More → Server connection**. Or open `http://<computer-ip>/oromatvnew/api/ping` in the phone's browser.
- If the phone cannot connect, allow **Apache (port 80)** through Windows Firewall for private networks.
- Desktop preview (no phone): `npx expo start --web`. It shows the same screens in a browser; the players are plain iframes there.

### Pointing at a different server

Set `EXPO_PUBLIC_API_URL` in `app/.env` (no trailing slash), e.g. `https://oromatv.com/api`. Otherwise the per-environment
addresses in `app.json → expo.extra.apiUrl` are used.

## API (`../api`)

JSON, read-only except comments and the contact form. Lists are `{ data, meta: { current_page, last_page, per_page, total } }`;
errors are `{ message, errors: { field: [..] } }`.

| Endpoint | Purpose |
|---|---|
| `GET /api/ping` | Health check |
| `GET /api/v1/home` | Everything for the home screen in one request |
| `GET /api/v1/live` | What is on air (`stage`, with `offset_seconds` = how far into a recorded programme "now" is), what is next (`next`), the 72-hour `schedule`, the off-air `playlist`, `watching` (people watching now), `radio`, the server clock, and the admin's active radio / video `channels` |
| `POST /api/v1/live/ping` | `{ viewer_id, platform: app\|web }` — a device checks in every 30 s while watching; returns `watching`. Anonymous; rate-limited |
| `GET /api/v1/radio` | "Listen to Q FM": the station's `stream_url`, `now_playing`, `listeners` — read fresh from the Radio Garden / station link (cached 30 s) |
| `GET /api/v1/schedule` | `?hours &limit` — what is on air and what is coming up |
| `GET /api/v1/programmes/{id}` | One programme (for the premiere page): `state` upcoming \| live \| ended, `on_air`, `starts_at`, `share_url` |
| `GET /api/v1/categories` | Active categories (with the website's colours) |
| `GET /api/v1/articles` | `?page &per_page &category &q &tag` |
| `GET /api/v1/articles/{slug}` | Story with HTML body, tags, comments and related stories (counts a view, like the website) |
| `POST /api/v1/articles/{slug}/comments` | `{ name, email?, comment }` — goes live straight away, like the website; rate-limited |
| `GET /api/v1/shows`, `/shows/{id}` | Previous shows and movies (`?kind=movie\|show`; the same video added twice is listed once) |
| `POST /api/v1/contact` | `{ name, email, subject, message }` — emailed to `info@oromatv.com`; rate-limited |

The API deliberately exposes only what the public website already shows. It never reads the `settings` table wholesale
(that table holds the AI API key) — only the stream on/off switch.

The live player's address lives in one place, `OROMA_LIVE_EMBED_URL` in `../config.php`, used by both `watch.php` and the API.

## Running the live schedule (Admin → Streams & Live)

Three tabs, no code changes needed:

1. **Live Control** — the banner shows what is on air right now, with **how many people are watching** (app and website, refreshed every few seconds) and the peak. A **48-hour timeline** shows every programme and highlights **gaps** (nothing scheduled — viewers would see the off-air playlist) with a *Fill this gap* button. *Schedule a programme or premiere*: what it is (programme / **movie premiere** / special event), a title, the stream link (a YouTube link, an `https://` player link, or a pasted `<iframe>` code), how it plays (**In sync** for a recording — everyone watches the same second, even someone who opens the app late — or **Real-time** for a live stream), an optional poster, a start time, how long it runs, and **Repeat** (daily / weekdays / weekly) which creates the whole series at once. At the start time it goes on air by itself — in the app and on the website, with no reload. **Go live now**, **End now**, edit, delete (or delete the whole series) and **Share** (WhatsApp, Facebook, X, Telegram, copy link) are one click. Finished YouTube programmes are saved into **Previous Shows** automatically. *Automation* has switches to pause the schedule, turn the off-air playlist on or off, shuffle it, save finished programmes, and a field for the **main channel** link.
2. **Channels** — the radio stations and extra videos viewers can pick on the Live tab: reorder, hide, set a default, preview, edit. The **Q FM — listen live** card is where you paste the station's **Radio Garden link** (open Radio Garden → Q FM → Share → copy the link), an AzuraCast link, or a direct stream address; **Check now** shows what the app will play and the song on air.
3. **Off-air Playlist** — videos that play one after another, on a loop, whenever nothing is live. Paste a YouTube link (the
   title is read for you) or add from Previous Shows with one click.

What is on air, in order: a **scheduled programme that is running** → the **main channel**, when its manual switch is on →
otherwise the **off-air playlist** (and if that is empty, your previous shows — the screen is never empty). The *Main channel on / Switch off* button is that manual switch (it was the old "Live Status"
switch): **leave it off** so the playlist can play between programmes. Times are Africa/Nairobi. A programme with no end time is
cut off after 12 hours. The tables (`live_programs`, `offair_items`, `live_viewers`, and a `kind` column on `previous_shows`) create themselves the first time the admin or the API
runs; if your database user is not allowed to, import `database/live-schedule.sql`.

## Build and publish

```bash
npm i -g eas-cli && eas login
eas init                       # once: links the project and writes its id into app.json
eas build -p android --profile preview      # test APK pointing at https://oromatv.com/api (deploy the API first, below)
eas build -p android --profile local-wifi   # test APK pointing at your PC over Wi-Fi (allows http)
eas build -p android --profile production   # Play Store bundle
```

Before the first store build, check `ios.bundleIdentifier` / `android.package` (`tech.kakebe.oromatv`) in `app.json` —
they cannot be changed after publishing.

**The API must be on the live server first:** upload the `api/`, `includes/`, `admin/` and `assets/` folders and the top-level pages (`config.php`, `index.php`, `watch.php`, `show.php`, `previous-shows.php`), then open `https://oromatv.com/api/ping` — it should answer `{"status":"ok"…}`.

## Notes and limits

- **LIVE badge** means a scheduled programme is running, or the main-channel switch is on. It cannot tell whether the video itself is a real live
  broadcast — a YouTube replay or an offline Dacast player simply plays (or says "Content is offline") while the badge says live.
- Video players are web embeds (YouTube, Dacast), so **video stops when you leave the Live tab or lock the phone**. The **Q FM** radio is real audio (`expo-audio`) and keeps playing in the background. Radio Garden blocks automated lookups from some networks; if the server cannot read the station, paste the AzuraCast or direct stream address instead (Channels → Q FM).
- **Synchronized playback** uses the server's clock (`stage.starts_at` + the clock difference the app measures), so a wrong time on the phone changes nothing. A viewer who presses Back or Stop leaves the shared moment on purpose; **Jump to live** returns. Verified in the browser preview, not yet on a real phone.
- YouTube videos (live stream, extra channels, previous shows) play in the app's own player (`components/YouTubePlayer.jsx`): YouTube's controls are switched off with its official `controls=0` setting and driven through its IFrame API. The app covers the picture with its own Play screen when stopped. YouTube's terms ask that embedded players keep their branding, so check them before a Play Store release; a Dacast/own HLS stream has no such rule.
- No push notifications yet ("we're live now" alerts are the natural next step).
- Dark mode is off (the website is light-only too).
