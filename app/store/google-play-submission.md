# Oroma TV – Google Play submission (release 2.0.0)

Checked against the code on 19 Nov 2026. Package `com.tvoroma.app.com` (an update to the existing listing); developer Kakebe Technologies Limited. **[CONFIRM]** = only the owner can confirm. **[UNVERIFIED]** = no Play Console access today, so form wording and limits are from Google's public help pages read on 19 Nov 2026 or from earlier notes.
Listing text is in `store-listing.md`; the "App access" text for reviewers is in `review-notes.md`; how comments are moderated (needed for the User Generated Content answers) is in `apple-submission.md` sections 7 and 8. Do `pre-submission-checklist.md` first: the live website and API must have the moderation code, privacy.php and terms.php.

URLs to paste (all must answer 200 on the live site before you press "Send for review")
- Privacy policy: https://oromatv.com/privacy.php · Terms of Use: https://oromatv.com/terms.php
- Data deletion: https://oromatv.com/privacy.php#rights (the anchor exists; email info@oromatv.com)
- Website: https://oromatv.com · Contact page: https://oromatv.com/contact.php · YouTube channel: https://www.youtube.com/@oromatv
- Support email: info@oromatv.com · Phone: +256 772 033 333

## 1. Build and upload (from Windows; EAS builds in the cloud)
Config as it is now (verified with `npx expo config --type introspect`): version 2.0.0, SDK 57 / React Native 0.86; permissions INTERNET, MODIFY_AUDIO_SETTINGS, FOREGROUND_SERVICE, FOREGROUND_SERVICE_MEDIA_PLAYBACK; blocked READ/WRITE_EXTERNAL_STORAGE, SYSTEM_ALERT_WINDOW, VIBRATE; no microphone permission; plain http is off in store builds. There is no `android.versionCode` (EAS keeps it, `appVersionSource: remote`) and `eas.json` has no Android `submit` block, so the first upload is by hand.

1. `cd C:\xampp\htdocs\oromatvnew\app`, `npx expo-doctor`, `npx eas-cli login`.
2. **Signing key:** `npx eas-cli credentials -p android` and make sure the **existing upload keystore** of `com.tvoroma.app.com` is the one EAS uses. Play rejects an upload signed with a different key **[CONFIRM you have it]**. If it is lost, ask Play for an upload-key reset (Play Console > App integrity) **[UNVERIFIED]**.
3. **Version code:** `npx eas-cli build:version:get -p android`. If it is not higher than the highest version code ever uploaded to Play, run `npx eas-cli build:version:set -p android` and enter that number (the next build adds 1).
4. `npx eas-cli build --platform android --profile production` produces the `.aab`. Download it.
5. Check the bundle before uploading: `bundletool dump config --bundle=app.aab` should show 16 KB page alignment (`PAGE_ALIGNMENT_16K`); the merged manifest should have INTERNET, MODIFY_AUDIO_SETTINGS, FOREGROUND_SERVICE and FOREGROUND_SERVICE_MEDIA_PLAYBACK (a library may add ACCESS_NETWORK_STATE or WAKE_LOCK) and **no** AD_ID, SYSTEM_ALERT_WINDOW, storage or RECORD_AUDIO. Target API and 16 KB support are SDK 57 defaults **[UNVERIFIED until you read Play Console's messages after the upload]**.
6. Play Console > Testing > Internal testing > Create new release > upload the `.aab` > release name `2.0.0` > paste the release notes (section 8) > Review release > Start rollout. Install it from the Play link on a real phone and run the dry run in `review-notes.md` section 3. Fix any blocking message about target API or 16 KB pages.
7. Complete every App content page (sections 2 to 7) and the Main store listing (text from `store-listing.md`; graphics in `app/store/google-play/`: `app-icon-512x512.png`, `feature-graphic-1024x500.png`, phone screenshots `01` to `09` in `screenshots-phone-1080x2160/`; Play allows 2 to 8, so leave out `07-form.png`, which still shows older wording).
8. Production > Create new release > Add from library (the same bundle) > release notes > roll out to about 20% (staged) > Send for review. Watch crashes and the admin **Reported** tab for a few days before 100%.
9. **After the rollout is nearly complete,** make `accepted_terms === true` mandatory in `api/routes.php` `route_comment_create`. Today the API accepts a comment with no `accepted_terms` field because the app already on Google Play has no Terms box, no Report and no Hide. Until then old installs can post without ticking the Terms.

## 2. App content > Data safety
Top level: collects or shares user data = **Yes**; all data encrypted in transit = **Yes** (HTTPS to oromatv.com; cleartext is off in store builds, `app.config.js`); account creation = **my app does not allow users to create an account**; users can request deletion = **Yes**, `https://oromatv.com/privacy.php#rights`; Families Policy: No; independent security review: No.

| Category > data type | Collected | Shared | Ephemeral | Required / optional | Purposes |
|---|---|---|---|---|---|
| Personal info > Name | Yes: comment name (public), contact-form name | No | No | Optional | App functionality |
| Personal info > Email address | Yes: optional on comments (never shown), contact form | No | No | Optional | App functionality |
| Messages > Other in-app messages | Yes: comment text, contact-form message | No | No | Optional | App functionality |
| App activity > App interactions | Yes: which programme, main channel or off-air playlist a viewer ID watched, first and last seen | No | No | Required (the Live tab sends it; cannot be switched off) | Analytics, App functionality |
| App activity > In-app search history | Yes: news search words go to the API as `?q=` and the host keeps the request address | No | No | Optional | App functionality |
| Device or other IDs | Yes: random viewer ID; a one-way device code stored with comments and reports | **Yes: Google (the YouTube player runs inside a WebView the app controls and receives IP and device data)** | No | Required for the Live tab | App functionality, Analytics, Fraud prevention, security and compliance |

Notes for the form:
- Google counts data passed through a WebView your app controls as sharing, which is why Device or other IDs is "shared". Answering No would need a defence that YouTube acts as your service provider **[UNVERIFIED]**; keep Yes.
- Comments are public because the user pressed Post; that is user-initiated.
- Not collected: location, contacts, photos, videos, audio, files, calendar, health, financial, web browsing history, crash logs or diagnostics (no analytics or crash SDK in `app/package.json`). The IP address is not a Play data type; privacy.php says the host keeps it in server records and that a code made from it limits spam.
- What is stored and for how long is in `apple-submission.md` section 5 (hashes, block list 90 and 30 days, viewer IDs about a week). Form, privacy.php and the app must say the same; reopen the form after any change.

## 3. App content > Foreground service permissions
- Permission and type: `android.permission.FOREGROUND_SERVICE_MEDIA_PLAYBACK`, type **Media playback**. The service comes from the expo-audio plugin (`AudioControlsService`, an androidx.media3 `MediaSessionService`, declared with `foregroundServiceType="mediaPlayback"`, checked in `node_modules/expo-audio`). Do not declare any other type (the microphone service is switched off in `app.json`).
- Use case: "Media playback - continuing audio playback from the background, including streaming".
- Description to paste:
```
Oroma TV is a TV, radio and news app. On the Live screen the user can tap "Listen to Q FM" to stream the Q FM live radio station. The audio plays through Android's media playback service (androidx.media3 MediaSessionService, via the expo-audio library) so that the radio keeps playing when the user locks the screen or opens another app, which is the normal way people listen to radio. The service starts only after the user taps "Listen to Q FM" and ends when the user stops the radio in the app. While it runs, a media notification shows the station name and the song on air with a play/pause control. The app never starts audio automatically and uses the service for nothing else.
```
- User impact if deferred or interrupted:
```
A live radio stream has to start the moment the user presses play. If Android delayed the service, the radio would not start or would start late. If Android stopped the service while the screen is locked or another app is open, the radio would cut out and the user would have to keep the screen on and stay in the app to listen. After an interruption such as a phone call the user presses Play to rejoin the live stream.
```
- Video link (required): about 45 seconds on a real Android phone, uploaded to YouTube as Unlisted. Storyboard: open the app > Live tab > tap "Listen to Q FM" (audio starts, the Q FM bar appears) > press Home or the power button (audio continues, the media notification shows) > return to the app and tap the X on the Q FM bar (audio stops). The user must start playback in the video.
- **[VERIFY ON DEVICE]** the media notification and lock-screen controls show on Android 13 and later; the app does not ask for the notification permission.

## 4. App content > Content rating (IARC questionnaire; retake it for 2.0.0 because comments add user interaction)
Contact email info@oromatv.com. Category: Reference, News or Educational (fallback Entertainment). Answer to the meaning; the wording changes. Expect a Teen / 12+ style result; do not under-answer.

| Topic | Answer |
|---|---|
| Violence | No violence made for entertainment. Real events in the news can mention crime or accidents: answer Yes, news reporting, non-graphic, if asked about real-world violence **[CONFIRM editorial practice]** |
| Sexual content or nudity | No |
| Profanity or crude humour | No in our own content; user comments are covered by the interaction answer |
| Controlled substances | No (news mentions only) |
| Gambling | No |
| Fear or horror | No |
| Discrimination or hate | No in our content; comments are filtered and moderated |
| Users interact or exchange content | **Yes**: text comments under stories; filtered, Report and Hide buttons, staff block list; we aim to review reports within 24 hours |
| Shares user location with others | No |
| Digital purchases | No |
| Unrestricted internet access | **No**, the same answer as on the Apple form: there is no address bar; story links, social profiles and legal pages open in the in-app browser. **[CONFIRM / RISK]** If you choose Yes, answer Yes on Apple too (Apple then rates 16+) |
| Ads | see section 6 |

## 5. App content > News apps declaration (the app is in the News & Magazines category)
| Question | Answer |
|---|---|
| News or magazine app? | Yes |
| Legal entity / parent entity | Oroma TV, Oyam Road, Lira City, Uganda, if the account is Kakebe Technologies Limited **[CONFIRM the registered name and entity type]** |
| Aggregator? | No, if the stories, programmes and radio are Oroma TV's own. The admin has an import-from-a-link tool; if most published stories are imported, say so and show sources **[CONFIRM]** |
| Content generated by AI? | Answer honestly. The admin has an AI rewrite tool (`admin/ai-generate.php`); if any published story is AI-written, say Yes and label it **[CONFIRM]** |
| Contact information URL | https://oromatv.com/contact.php (the app's Contact screen shows this address as a tappable row "Website contact page") |
| Main content type | News and current affairs; Entertainment |
| Related YouTube channel | https://www.youtube.com/@oromatv |
| Editorial guidelines URL, awards | none yet (optional) **[CONFIRM]** |
Keep stories dated and attributed, and keep some current-events stories under 30 days old at review time.

## 6. Other App content answers
| Section | Answer |
|---|---|
| Privacy policy | https://oromatv.com/privacy.php (also in the app: More > Legal and safety) |
| Ads | **Contains ads: Yes is the cautious answer**: the app adds no ads of its own, but YouTube can show ads inside its player, and Google counts ads served in your app. No is defensible if you read it as "my own placements" **[CONFIRM your choice]**. Never write "no ads" in the listing |
| Advertising ID | No (no ad or analytics SDK); check the merged manifest has no `AD_ID` (step 1.5) |
| App access | "All functionality is available without special access", then paste `review-notes.md` section 2 |
| Target audience | 16-17 and 18+; no group under 13 (privacy.php and terms.php say the service is not for under-13s) **[CONFIRM]** |
| Government, financial, health, COVID | none |
| User-generated content (Google's UGC policy) | Terms accepted before posting: yes. Defined objectionable content and no tolerance: terms.php section 2. Moderation: filter, reports, admin queue (`apple-submission.md` sections 7 and 8). In-app report: yes. Blocking: the reader hides a commenter's name on their phone, and staff block commenters on the server (there are no accounts) |

## 7. Policy and rollout checks
- Play Console > Policy status shows no issue; the App bundle explorer shows no target-API or 16 KB message.
- Retake the content rating and the Data safety form whenever the app starts collecting something new.

## 8. Release notes 2.0.0 (max 500 characters; 374 used)
```
Oroma TV 2.0 is a brand-new app. Watch live programmes and premieres in step with everyone else, see what is on next in the programme guide, and listen to Q FM with the screen off. Browse previous shows and movies, read news with photos, comment on stories (report or hide comments you do not want to see), share, and contact us from the app. New design, faster, many fixes.
```
