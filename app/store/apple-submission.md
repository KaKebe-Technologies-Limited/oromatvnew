# Oroma TV – Apple App Store submission (iOS 2.0.0)

Checked against the code on 19 Nov 2026 (app, API, website and admin as they are in the working tree). **[CONFIRM]** = only the owner can confirm. **[UNVERIFIED]** = could not be checked from the code or a public page today; App Store Connect itself is the authority when a field rejects a value.
Text to paste for the product page is in `store-listing.md`. Do the steps in `pre-submission-checklist.md` first: the live website and API must already contain the moderation code, privacy.php and terms.php, or the app's Report, Terms and Privacy links fail at review.

## 1. What is already set up in the project (verified with `npx expo config --type introspect`, EXPO_PUBLIC_APP_ENV=production)

| Item | Value |
|---|---|
| Bundle ID / version | `tech.kakebe.oromatv` (cannot change once the app record exists) / `2.0.0`; iPhone only (`supportsTablet` false, so no iPad screenshots) |
| Encryption | `ITSAppUsesNonExemptEncryption` = false (only HTTPS), so the export-compliance question is answered automatically |
| Network | App Transport Security allows no plain http (`NSAllowsArbitraryLoads` false); the store build talks only to `https://oromatv.com/api` |
| Background | `UIBackgroundModes: audio` (Q FM radio). No location, camera, microphone, contacts, photos or push; no usage-description keys; no ATT prompt, no IDFA |
| Privacy manifest | `NSPrivacyTracking` false, four accessed-API reasons, and seven `NSPrivacyCollectedDataTypes` that match section 5 |
| Icon | `assets/icon.png` is 1024x1024 RGB with no alpha |
| EAS | `eas.json` production profile: `autoIncrement`, remote version source, `submit.production.ios` has `appName`, `sku` (`oromatv-ios`), `language` (`en-GB`), `companyName` |
| Not set yet | `ascAppId` (add after the app record exists) and a pinned build image (below) |

Build image: with no `image` set EAS uses `auto`, which it chooses from the SDK and React Native version; its docs map the `sdk-57` alias to Xcode 26.6 and warn that Xcode 27 images ("latest") need the UIKit scene life cycle. Safest: add `"ios": { "image": "sdk-57" }` under `build.production` in `app/eas.json`, and read "Spin up build environment" in the first build log to see which Xcode ran.

## 2. Click path: from nothing to "Submit for Review", from Windows (no Mac needed)

EAS Build and EAS Submit run in the cloud and work from Windows. You type the Apple ID password and the 2FA code yourself; never put them in a file or a chat.

1. [CONFIRM] An Apple Developer Program membership exists for the organisation (Kakebe Technologies Limited needs a D-U-N-S number to enrol as an organisation) and you can sign in to App Store Connect as Account Holder or Admin. Accept any pending agreements (the Free Apps agreement) in the Agreements area of App Store Connect.
2. In a terminal: `cd C:\xampp\htdocs\oromatvnew\app`, then `npx expo-doctor` (fix anything red), then `npx eas-cli login`.
3. Build the iPhone app (the IPA): `npx eas-cli build --platform ios --profile production`. The first run asks you to sign in with your Apple ID, then creates the distribution certificate and provisioning profile and registers the bundle ID by itself. The build number goes up by itself on every build. When it finishes you get a build page with the `.ipa`.
4. Create the app record, once: App Store Connect > Apps > **+** > New App: Platforms iOS; Name `Oroma TV` (if taken: `Oroma TV - TV, Radio & News`, 27 characters); Primary language English (U.K.); Bundle ID `tech.kakebe.oromatv`; SKU `oromatv-ios`; User access Full Access. Open the app > App Information and copy the numeric **Apple ID**, then add it to `app/eas.json`: `"submit": { "production": { "ios": { "ascAppId": "<that number>", ...the existing keys } } }`.
   (Alternative: leave `ascAppId` out and let `eas submit` create the record from `appName`, `sku`, `language` and `companyName`; EAS skips that step whenever `ascAppId` is set.)
5. Upload it: `npx eas-cli submit --platform ios --profile production --latest`. (`--latest` is the newest build; use `--id <build id>` for a specific one, or `--path file.ipa` for an IPA on disk; `production` is the default profile.) It signs in with your Apple ID (2FA) or an App Store Connect API key (`ascApiKeyPath`, `ascApiKeyIssuerId`, `ascApiKeyId`; keep the key file out of git). The build appears in TestFlight after Apple's processing, usually 10 to 15 minutes.
6. Test the build on a real iPhone: App Store Connect > TestFlight > add yourself as an internal tester, install the TestFlight app, install Oroma TV, and run the dry run in `review-notes.md` section 3. Do not submit before every step passes.
7. App Store Connect > the app > the version page (the App Store or Distribution tab, depending on the App Store Connect version) > iOS App > 2.0.0 Prepare for Submission (labels move around between Apple releases; follow what the page shows):
   - App Information: Name, Subtitle, Category News (secondary Entertainment), Content Rights (section 3), **Age Rating** (section 4). Keep Apple's standard licence agreement; do not paste terms.php into the License Agreement field **[UNVERIFIED: Apple's rule for custom terms]**. The Terms link is in the description and in the app.
   - Pricing and Availability: Free; all countries and regions including Uganda **[CONFIRM]**. EU Digital Services Act: declare **trader** status; the address, phone and email you give are shown on the EU product page.
   - **App Privacy**: answers in section 5; Privacy Policy URL `https://oromatv.com/privacy.php`; then Publish.
   - Version page: promotional text, description, keywords, support URL `https://oromatv.com/contact.php`, marketing URL, copyright, What's New (all in `store-listing.md`); screenshots (section 9); under Build, pick the processed build.
   - App Review Information: section 6. Version Release: **Manually release this version**, so the website, API and app go live together.
8. Click **Add for Review**, check the summary, then **Submit for Review** (the button wording may be "Submit to App Review"). Status goes to Waiting for Review, then In Review. Answer questions in App Store Connect > Resolution Center. Release only after the live site and the admin queue are working (`pre-submission-checklist.md`).

## 3. Other App Store Connect answers
- Sign-in required: **No** (no accounts anywhere in the app). Sign in with Apple (4.8) and account deletion (5.1.1(v)): not applicable.
- Content rights: "contains third-party content" = **Yes** (YouTube videos on the station's own channel, the Q FM radio stream, news photos). Have the rights letter for Q FM ready **[CONFIRM who owns and hosts the stream]**.
- Made for Kids: No. Do not use "kids" wording anywhere. Government, medical or financial app: No.
- Export compliance: nothing to answer (section 1). If App Store Connect still asks: the app uses only HTTPS provided by the operating system, which is exempt.

## 4. Age rating questionnaire (current scheme)
Apple's help table maps these answers to ratings (None, Infrequent, Frequent) **[UNVERIFIED: the exact question wording in App Store Connect]**. Expected result **13+**, driven by Infrequent realistic violence and Infrequent alcohol/tobacco/drug references (a news service in northern Uganda reports crime and arrests). Accept what App Store Connect computes and do not under-answer (guideline 2.3.6).

| Question | Answer | Why |
|---|---|---|
| Parental controls / Age assurance | No / No | |
| Unrestricted web access | **No** | There is no address bar or URL entry. Story links, the Follow buttons, Privacy/Terms and Add to calendar open an in-app Safari view; the editor controls those links. **[CONFIRM / RISK]** If you decide that counts, answer Yes (rating becomes 16+) and use the same answer on Google Play |
| User-generated content | **Yes** | public comments |
| Messaging and chat / Social media | No / No | comments are replies under a story; no user-to-user messages; no feed |
| Advertising | **Yes is the cautious answer** | the app has no ad placements of its own, but YouTube can show ads inside its player; this answer does not raise the age rating **[CONFIRM your choice]**. Never write "no ads" in any text |
| Profanity or crude humour | Infrequent | possible in comments (filtered) and some movies |
| Horror or fear themes | None | |
| Alcohol, tobacco or drug references | Infrequent | news, movies **[CONFIRM]** |
| Medical or treatment information | None | |
| Health or wellness topics | Infrequent | there is a Health news category |
| Mature or suggestive themes | None | **[CONFIRM no film on the schedule has them]** |
| Sexual content or nudity / graphic | None / None | **[CONFIRM]** |
| Cartoon or fantasy violence | None | |
| Realistic violence | Infrequent | news about clashes, arrests; movies |
| Prolonged graphic or sadistic violence | None | |
| Guns or other weapons | Infrequent | news, movies |
| Contests, loot boxes, simulated gambling, gambling | None | sports news only; the comment filter holds betting and scam tips |

## 5. App Privacy ("nutrition label")
"Do you or your third-party partners collect data from this app?" = **Yes**. "Used to track you" = **No** for every row. These seven rows are also written into `app.json` (`NSPrivacyCollectedDataTypes`); keep the label, app.json and privacy.php identical.

| Apple data type | What and where | Linked to identity | Tracking | Purposes |
|---|---|---|---|---|
| Contact Info > Name | name typed on the comment form (public) and on the contact form | Yes | No | App Functionality |
| Contact Info > Email Address | optional on comments (never shown); required on the contact form | Yes | No | App Functionality |
| User Content > Other User Content | comment text | Yes | No | App Functionality |
| User Content > Customer Support | contact form subject and message (emailed to info@oromatv.com, not stored in the database) | Yes | No | App Functionality |
| Identifiers > Device ID | random viewer ID (`app-` + 20 characters, kept in the iOS Keychain, sent every 30 s while the Live tab is open); the one-way device code stored with comments and reports | Yes | No | Analytics, App Functionality |
| Usage Data > Product Interaction | which programme, main channel or off-air playlist that viewer ID was watching, first and last seen | Yes | No | Analytics, App Functionality |
| Usage Data > Search History | news search words, sent in the request address (`?q=`) and so also in the host's server records | No | No | App Functionality |

Facts these answers rest on (all re-read in the code on 19 Nov 2026):
- No analytics, advertising or crash SDK in `app/package.json`; no location, camera, microphone, contacts or photos.
- Comment records hold the name, optional email, text, time, a 32-character one-way **device code** (HMAC-SHA256: `v` + a hash of the viewer ID in the app, `w` + a hash of network address and browser type on the website) and why it was held. No raw IP address or user agent is stored there.
- Report records hold the comment, reason, app or website, time, the device code and a second one-way **network code** (HMAC of the IP; IPv6 as its /64). Used to count different networks and ignore repeats.
- Block-list rows hold: kind (name, email or device), a normalised value, a note, the admin, dates and an end date (name and email blocks 90 days, device blocks 30 days, unless an admin makes it permanent). Nothing deletes a row automatically.
- The secret key behind the codes is an optional server-side pepper (`COMMENT_HASH_PEPPER`, kept outside the database) or else a salt in the settings table; privacy.php says only "a secret key that stays on our server" and does not claim the codes cannot be recovered.
- Viewer IDs are deleted when last seen over 7 days ago; the clean-up runs on about 1 in 100 check-ins ("about a week").
- Rate-limit counters are small files in the server's temp folder, named with a keyed hash of the address, holding only times. They are not in the app privacy label.
- On the phone only (iOS Keychain, `WHEN_UNLOCKED_THIS_DEVICE_ONLY`, so not copied to a new phone by a backup restore): viewer ID, hidden commenter names (up to 40), reported comment numbers (up to 100) and a "terms agreed" flag (`2026-11`). The hidden list never leaves the phone. iOS may keep Keychain items after the app is deleted; privacy.php says so.
- YouTube's player runs inside the app and receives IP and device data; Device ID and Product Interaction already cover those categories. **[UNVERIFIED how Apple wants an embedded player declared]**; keep Tracking = No. Optional extra margin: Diagnostics > Other Diagnostic Data (IP address in host logs), not linked, App Functionality.
- **[CONFIRM]** the live host's real client address is what the server sees (`pre-submission-checklist.md` step B3). If it is a CDN address, the report rule and rate limits treat many people as one.

## 6. App Review Information
| Field | Value |
|---|---|
| Sign-in required | **No** (unchecked; no demo account) |
| Contact first / last name | **[CONFIRM the person who will answer Apple]** |
| Contact phone | +256 772 033 333 |
| Contact email | info@oromatv.com |
| Attachment | **[CONFIRM] a short screen recording** of: Report (reasons, "Thank you"), Hide and Hidden commenters, the Terms box, a held comment, and the admin Comments > Reported queue. Plus a letter if the developer account name (Kakebe Technologies Limited) differs from the brand (Oroma TV) |

**Notes (paste the block below; first settle the two [CONFIRM] sentences in it: who owns the Q FM stream, and the screen recording). It is 3.7 KB with them, under the 4,000-byte limit.**
```
No sign-in is needed. Every feature works on first launch. The app has no accounts, so there is no demo login.

WHAT THE APP IS
Oroma TV is the app of Oroma TV, a community television, news and radio service in Lira City, Uganda. It shows the station's scheduled programmes, the Q FM radio stream, previous shows and stories from our own newsroom. It has no hidden or remotely switched-on features. Tabs: Home, News, Live (round middle button), Shows, More.

LIVE TAB
- Video plays in the YouTube embedded player. The Back, Stop, Forward and Fullscreen buttons are ours.
- It is a scheduled channel, so depending on the time you may see: (a) a live programme; (b) a recorded programme shown at its scheduled time so everyone watches the same moment (the screen says "Recorded programme, shown at its scheduled time. Everyone watches together." and it may start part-way through); or (c) when nothing is scheduled, our off-air playlist of past shows. All three are expected, not errors.
- Video stops when the app goes to the background.

Q FM RADIO
Live tab > "Listen to Q FM" plays an audio stream. It keeps playing with the screen locked or the app in the background (UIBackgroundModes: audio), with lock-screen controls. Video is muted meanwhile. Tap the X on the Q FM bar to stop.

COMMENTS (user-generated content, guideline 1.2)
Where: News or Home > open any story > scroll to the end ("Comments"). Text only: name, optional email, comment. No photos, video or messaging.
- Terms: before the first post the user must tick "I agree to the Terms of Use and understand there is no tolerance for objectionable content or abusive users" (https://oromatv.com/terms.php).
- Filter: our server checks every new comment and holds it for staff review instead of publishing it if it has profanity, threats, sexual terms, scam phrases, links to other websites, or a phone number or email address. The app then says "Thanks! Your comment needs a quick check by our team because it may break our community rules. It will appear once it is approved." To see it, post a comment containing "example.com".
- Report: every comment has a Report button (spam, abusive or hateful, misleading, other). The comment disappears from the reporter's view at once and the report goes to our moderation queue and by email to the newsroom. A comment reported from three different networks is hidden for everyone until staff review it. We aim to review reports within 24 hours. Staff keep, hide or delete the comment and can block the commenter.
- Hide: every comment has "Hide <name>", which removes that name's comments from the user's view at once; undo in More > Legal and safety > Hidden commenters. There are no accounts, so a commenter is identified by the name typed. Staff also block a commenter by name, email and device: later comments from them are held for review.
- Contact: More > Contact us (form, info@oromatv.com, +256 772 033 333). Many test comments in a row: the app asks you to wait (6 per 10 minutes).

OTHER
- Links open in an in-app Safari view. Privacy Policy and Terms of Use: More > Legal and safety.
- No purchases, location, camera, microphone or contacts, no advertising or analytics SDKs. We add no ads of our own; YouTube may show its own inside its player. While the Live tab is open the app sends a random viewer ID every 30 seconds, only to count viewers.
- Programmes and shows come from the station's YouTube channel. [CONFIRM: who owns and hosts the Q FM stream. If it is not Oroma TV, add: "Q FM is used with the owner's permission; letter available."]
- An internet connection is required (backend: https://oromatv.com/api). [CONFIRM: attach the screen recording of Report, Hide and the staff moderation queue.]
```

## 7. User-generated content safeguards (guideline 1.2): what exists and where
Apple asks for: a filter, a way to report with a timely response, a way to block abusive users, and published contact details. Oroma TV has no accounts, so "block" is two things: the reader hides a name on their own phone, and staff block a commenter on the server.

| Requirement | What the app and server do | Where a reviewer finds it | Code |
|---|---|---|---|
| Terms before posting | Tick box "I agree to the Terms of Use and understand there is no tolerance for objectionable content or abusive users" until the first successful post; the line "By posting you agree to our Terms of Use ... We aim to review reports within 24 hours." always shows. The API refuses `accepted_terms: false` (422); a missing field is accepted so the old Google Play app keeps working | News > a story > end of the page > Leave a comment | `components/CommentSection.jsx`, `lib/moderation.js`, `api/routes.php` `route_comment_create` |
| Filter | Every new comment is checked; a hit (profanity, slurs, sexual terms, threats, scam or betting phrases, extra words set by the admin, links to non-allowed sites, phone numbers, emails) or a blocked commenter means status "pending": not shown, an admin decides. Viewer message is the fixed `COMMENT_HELD_MESSAGE`. Nothing is deleted automatically | post a comment containing `example.com` | `includes/comment-filter.php`, `includes/comment-moderation.php` |
| Report | Report under every comment: 4 reasons, one report per device per comment; the comment leaves the reporter's view at once; the newsroom gets an email (at most 4 "first report" and 6 "hidden" emails an hour); 3 different networks hide it for everyone; a network that reports more than 5 different comments a day stops counting toward that. The app shows "Thank you" only when the server confirmed; a 404 counts as "already gone" only when the reply carries `code: comment_gone`, any other failure says "We could not send your report. Please try again." and records nothing | Report button under any comment | `components/CommentSheet.jsx`, `route_comment_report`, `comment_report_add` |
| Block abusive users | Reader: **Hide <name>** (this phone only; undo in More > Hidden commenters). Staff: ban from the admin by name, email and device; future comments are held; the same app install, email or name has its other comments hidden; name and email bans last 90 days (admins can make one permanent), device bans 30 days | Hide button; More > Legal and safety > Hidden commenters | `lib/moderation.js`, `app/hidden-commenters.jsx`, `comment_ban_from_comment`, `admin/comments.php` |
| Timely response | We aim to review reports within 24 hours (section 8) | admin queue | `admin/comments.php` |
| Contact details | email, phone, address, office hours (Monday to Friday, 9:00 to 17:00 EAT), contact form, and the website contact page as a tappable link; also in the Report sheet ("Need to reach us directly?") | More > Contact us | `app/contact.jsx`, `lib/links.js` |
| Rate limits | 6 comments per 10 minutes per network (app and website share one counter), 30 reports per hour, 5 contact messages per hour, 90 viewer check-ins per minute; a duplicate comment from the same device within 10 minutes is refused | | `ugc_rate_hit`, `api/routes.php` |

**What the reviewer should tap:** News tab > any story > scroll to the end. (1) Untick the Terms box and press Post: refused with "Please tick the box to agree to the Terms of Use." (2) Tick it, post "see example.com": held message, comment not in the list. (3) Tap **Report** under a comment > a reason: "Thank you", the comment disappears. (4) Tap **Hide <name>** > Hide, then More > Legal and safety > Hidden commenters > Show again. (5) More > Privacy Policy and Terms of Use. (6) More > Contact us.

## 8. How reports are handled (the 24-hour process) and where the admin queue is
- Every report is stored at once and shown in the admin. Email to info@oromatv.com goes out for the first report of a comment and when reports hid it (throttled). PHP `mail()` delivery on the live host **[CONFIRM by test]**; the admin never depends on the email.
- **Queue:** `https://oromatv.com/admin/comments.php` (staff login). It opens on **Reported** when there are open reports. The admin menu badge counts held plus reported comments; the dashboard card "Reported Comments (review within 24 h)" shows the count. Reported is sorted with reports open more than 24 hours first (red "over 24 h"), then most different networks, then oldest.
- **Decide:** keep (closes the reports; the comment is marked as approved by a moderator and is not auto-hidden again), hide, ban the commenter (editors and admins: 90 days; admins: permanent), or delete. **Pending** tab: comments held by the filter, a block or automatic hiding; approve or hide. **Rules** tab (admins): extra blocked words (the built-in list is English plus short Swahili and Luganda starter lists; **add Acholi, Lango, Luganda and Swahili words**), allowed link domains, active blocks, and a "Visitor address check" to run once on the live site.
- Wording everywhere: "We aim to review reports within 24 hours." (website forms, Terms, Privacy Policy, contact page, the app, admin, these store texts). The Contact screen publishes office hours Monday to Friday, so decide who checks the queue on weekends **[CONFIRM staffing and who watches the info@ mailbox]**.
- Requests to delete personal data: privacy.php promises an answer within 30 days and sooner removal of a comment.

## 9. Screenshots
Files and slots are in `README-screenshots.md`: 9 iPhone 6.9-inch PNGs (1320x2868, no alpha) in `app-store/screenshots-iphone-6.9in-1320x2868/`; the app icon comes from the build. They use made-up commenters (Grace A., Moses O., Brenda L.). Retake `07-form.png` first: it still shows "we review reports within 24 hours". Upload 01 to 06, 08, 09 meanwhile. Screens 06, 07 and 08 matter for guideline 1.2.

## 10. Things that would make Apple reject, and the state today
| Risk | State |
|---|---|
| Privacy, Terms or Report endpoints 404 on the live site | **Blocker until deployed** (`pre-submission-checklist.md`) |
| Q FM listed but the stream does not play | **[CONFIRM]** on a phone, live setting |
| "no ads" or a claim the app does not keep | removed everywhere; YouTube ads are disclosed |
| Reviewer cannot find Report, Hide or Terms | section 7 route is in the notes |
| 24-hour promise not staffed | worded "aim to"; **[CONFIRM]** staffing |
| YouTube overlay rule: the app covers the picture with its own screen for a few seconds after play, skip and resume, and draws its own chip (`components/YouTubePlayer.jsx`) | open, owner decision; this is YouTube's policy (Required Minimum Functionality), not Apple's, but a reviewer may notice it |
| The Live badge says LIVE for a recording | accepted: the caption "Recorded programme, shown at its scheduled time. Everyone watches together." is shown under the title |
