# Oroma TV – before you submit (website and API first, then both stores)

Checked against the working tree on 19 Nov 2026. "Owner" = something only you can do (accounts, live server, store consoles). Nothing here has been done on the live site: until section A is done, the app's Privacy, Terms and Report features and both listings point at pages that do not exist there. **[CONFIRM]** = only the owner can confirm.

## A. Deploy the website and API (Owner) - before building or submitting anything
0. [ ] Back up the live database (hPanel > Backups, or phpMyAdmin > Export). The first moderation request alters the `comments` table.
1. [ ] Upload the NEW files: `includes/comment-filter.php`, `includes/comment-moderation.php`, `privacy.php`, `terms.php`, `assets/css/legal.css`, `database/.htaccess`, `backups/.htaccess`.
2. [ ] Upload the CHANGED files: `api/bootstrap.php`, `api/index.php`, `api/routes.php`, `comment-submit.php`, `article.php`, `contact.php`, `config.php` (it holds both the local and the live settings, chosen by host name; upload it as it is), `includes/footer.php`, `assets/css/style.css`, `admin/comments.php`, `admin/dashboard.php`, `admin/includes/admin-header.php`.
3. [ ] Do NOT upload: `database/*.sql`, `backups/*.sql`, `app/`, `.git`, `node_modules`. The dumps hold personal data (and one looks like another project's). The two `.htaccess` files above deny web access to those folders; test B3 proves it.
4. [ ] Recommended before release: set the secret key for the one-way codes. On the live server create an untracked `config.local.php` next to `config.php` with `define('COMMENT_HASH_PEPPER', '<at least 16 random characters>');` (or set the `COMMENT_HASH_PEPPER` environment variable). Do it **before** release: changing it later makes existing blocks and report de-duplication stop matching. Without it the key is a salt stored in the settings table.
5. [ ] Log in to https://oromatv.com/admin/comments.php once. It creates the moderation columns and tables by itself (`comments.device_hash`, `comments.hold_reason`, tables `comment_reports` and `comment_bans`). If it says "Comment moderation tables are missing", the database user cannot ALTER/CREATE: run `database/comment-moderation.sql` in phpMyAdmin > SQL (upload it by hand, then delete it from the server).
6. [ ] In phpMyAdmin check the two new columns and two new tables exist and `SELECT COUNT(*) FROM comments` is unchanged.

## B. Test on the live site (Owner; expected result in brackets)
1. [ ] https://oromatv.com/privacy.php (200, contains "YouTube API Services"), https://oromatv.com/terms.php (200, contains "no tolerance for objectionable content"), https://oromatv.com/contact.php (200), https://oromatv.com/privacy.php#rights (scrolls to "Your choices and rights"), https://oromatv.com/api/ping (200), https://oromatv.com/api/v1/home (200 JSON).
2. [ ] Visitor address: Admin > Comments > Rules > "Visitor address check", opened on your phone with mobile data. `REMOTE_ADDR` should be your own public address. If it is a CDN address, or the same for different devices, tell the developer: the rate limits, the "three different networks" report rule and device blocks would treat many readers as one.
3. [ ] These must NOT download (403 or 404): `https://oromatv.com/database/u850523537_OromaTVDB.sql`, `https://oromatv.com/database/comment-moderation.sql`, `https://oromatv.com/backups/oroma_tv_backup_20260731_191933.sql`. If any returns the file: delete it from the server at once and change every admin password.
4. [ ] An article page: the comment form has the Terms tick box; every comment has a Report button; submitting without the tick shows "Please tick the box to agree to the Terms of Use."
5. [ ] API write test (creates rows on the live site; use the name `ZZ TEST` and delete them afterwards). In cmd.exe, with a real article slug:
```
curl -s -i -X POST https://oromatv.com/api/v1/articles/<SLUG>/comments -H "Content-Type: application/json" -d "{\"name\":\"ZZ TEST\",\"comment\":\"a polite test comment\",\"accepted_terms\":true,\"viewer_id\":\"zz-test-viewer-0001\"}"
   -> HTTP 201, "Thanks! Your comment is now live."
curl -s -i -X POST https://oromatv.com/api/v1/articles/<SLUG>/comments -H "Content-Type: application/json" -d "{\"name\":\"ZZ TEST\",\"comment\":\"see example.com\",\"accepted_terms\":true}"
   -> HTTP 202 {"held":true,...}; not on the article page; shown under admin > Comments > Pending
curl -s -i -X POST https://oromatv.com/api/v1/comments/<ID>/report -H "Content-Type: application/json" -d "{\"reason\":\"spam\",\"viewer_id\":\"zz-test-viewer-0002\"}"
   -> HTTP 201 with "reported":true; an email reaches info@oromatv.com; admin > Comments > Reported shows it
curl -s -i -X POST https://oromatv.com/api/v1/comments/99999999/report -H "Content-Type: application/json" -d "{\"reason\":\"spam\"}"
   -> HTTP 404 {"message":"That comment is no longer available.","code":"comment_gone","data":null}
```
   If the report call returns `{"message":"Not found."}` (no `code`), the new routes are not deployed (`api/index.php`); the app then correctly says it could not send the report.
6. [ ] Delete every `ZZ TEST` comment in the admin (the Reported count in the admin menu must go back to what it was).

## C. Operations (Owner)
1. [ ] Admin > Comments > Rules: add abusive words in Acholi, Lango, Luganda and Swahili (one per line; `*` at the end also matches longer words; the built-in list is English plus short Swahili and Luganda starter lists, nothing for Luo), and the websites commenters may link to.
2. [ ] **[CONFIRM]** Decide who reviews the Reported and Pending tabs every day, including weekends, and make sure info@oromatv.com reaches a phone. The public sentence is "We aim to review reports within 24 hours." The Contact screen publishes office hours Monday to Friday.
3. [ ] **[CONFIRM]** PHP `mail()` works from the live host (test B5: the report email arrives). If not, rely on the admin badge and the dashboard "Reported Comments" card and check them daily.
4. [ ] **[CONFIRM]** Who answers personal-data requests within 30 days (privacy.php says so), and where that mailbox is.
5. [ ] **[CONFIRM]** Q FM is yours to stream, or you hold the owner's letter, and the live radio setting plays on a real phone (Admin > Streams; a Radio Garden link would stream through radio.garden's unofficial endpoint). The local setting points at `host.atenimedia.com`, whose secure connection failed from the development machine on 18 and 19 Nov 2026. Same for every movie and programme on the schedule.
6. [ ] **[CONFIRM]** Whether any published story is AI-written or imported from other sites (the Play News declaration asks).

## D. Open items found while writing these documents (Dev / Owner)
1. [ ] Retake `07-form.png` (iPhone and Android folders): it shows "we review reports within 24 hours". Until then leave it out of both stores.
2. [x] Done 19 Nov 2026: the reporter's confirmation text from the server (`api/routes.php`) and the website fallback (`article.php`) now say "We aim to review reports within 24 hours." The website report button now treats a 404 as "gone" only when the answer carries `"code":"comment_gone"`, the same rule as the app.
3. [ ] `app/eas.json`: add `ascAppId` after the app record exists, and pin `"ios": { "image": "sdk-57" }` under `build.production` (`apple-submission.md` section 1).
4. [ ] YouTube player: the app covers the picture with its own screen for a few seconds after play, skip and resume (`components/YouTubePlayer.jsx`). YouTube's rule for embedded players is against overlays that obscure the video; decide whether to shorten or drop the cover.
5. [ ] Fullscreen buttons ignore the notch and home bar (no safe-area insets in `YouTubePlayer.jsx`).
6. [ ] `app/about.jsx` still says "Live TV" and "streaming free, anywhere in the world"; accurate while a broadcast is on, but "TV" is safer.
7. [ ] After the Android 2.0.0 rollout is nearly complete, require `accepted_terms === true` in `route_comment_create` (`google-play-submission.md` section 1, step 9).

## E. Build and store forms (Owner)
- iPhone: `apple-submission.md` sections 2 to 6. Android: `google-play-submission.md` sections 1 to 8. Test the build with `review-notes.md` section 3 before pressing Submit.
- Release the iPhone app manually only when the website, the API and the admin queue are live and stable.

## F. After release (Owner)
1. [ ] Review the Reported and Pending tabs every day; the promise is in the app, the Terms, the Privacy Policy, the website and both listings.
2. [ ] Answer data requests within 30 days; remove a comment someone asks you to remove sooner.
3. [ ] Reopen Data safety and App Privacy whenever the app starts collecting something new.
4. [ ] Keep some stories newer than 30 days (Play News declaration).
5. [ ] If B3 ever returned a dump, remove it from the server and from git history.
