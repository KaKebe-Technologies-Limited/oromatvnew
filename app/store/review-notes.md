# Oroma TV – reviewer notes and the dry run (Apple and Google)

Checked against the code on 19 Nov 2026. The Apple "Notes" block is in `apple-submission.md` section 6. This file holds the Google Play "App access" text, how a reviewer reaches each screen, and the dry run the owner does on the real build before submitting. Do not paste either text until the website and API are deployed (`pre-submission-checklist.md`): the notes describe a filter, a Report endpoint and legal pages that must exist on https://oromatv.com.

## 1. How a reviewer reaches each screen (verified in the code)
Bottom tabs: Home, News, Live (the round middle button), Shows, More.
- **Comments:** Home or News > open any story > scroll to the end ("N Comments", then "Leave a comment"). Comments appear only under stories.
- **Report and "Hide <name>":** under every comment in that list.
- **Terms box:** in the "Leave a comment" form until the first successful post; the line "By posting you agree to our Terms of Use ..." always stays.
- **Hidden commenters:** More > Legal and safety > Hidden commenters (also a "Manage" link under the list when a hidden name has comments on that story).
- **Privacy Policy, Terms of Use, YouTube Terms of Service:** More > Legal and safety.
- **Q FM:** Live tab > "Listen to Q FM" under the title; the Q FM bar appears above the tabs with an X to stop.
- **Contact:** More > Contact us (form, email, phone, website contact page, address, office hours).
- **What the Live tab can show:** a live programme, a recorded programme shown at its scheduled time (it carries the line "Recorded programme, shown at its scheduled time. Everyone watches together."), or the off-air playlist. All three are normal.
- **What the app says when a comment is held:** "Thanks! Your comment needs a quick check by our team because it may break our community rules. It will appear once it is approved." (the constant `COMMENT_HELD_MESSAGE`).

## 2. Google Play > App content > App access
Choose "All functionality is available without special access", then paste:
```
No login, account or special access is needed. Everything works as soon as the app opens: Home, News, Live, Shows and More.
Live shows a live programme, or a recorded programme shown at its scheduled time so everyone watches together, or, when nothing is scheduled, a playlist of Oroma TV videos. The player is never empty. To test the foreground service, open Live, tap "Listen to Q FM", then lock the screen: the radio keeps playing; stop it with the X on the Q FM bar in the app.
To test comments (user-generated content): News > open any story > scroll to the end > tick the Terms of Use box > enter a name and a comment > Post. Each comment has a Report button (spam, abusive or hateful, misleading, other) and a "Hide <name>" button; hidden names are listed in More > Legal and safety > Hidden commenters. A comment containing a web link such as example.com is held for staff review and the app says so. We aim to review reports within 24 hours; staff can remove comments and block commenters. Terms: https://oromatv.com/terms.php. Privacy Policy: https://oromatv.com/privacy.php.
An internet connection is required; the app talks only to https://oromatv.com/api, YouTube and the Q FM stream. Contact: info@oromatv.com, +256 772 033 333.
```
If the Console box is shorter than that (the exact limit is **[UNVERIFIED]**), use this short version:
```
No login or account is needed. Live: a live programme, a recording shown at its set time, or an Oroma TV playlist. To test the radio service: Live > Listen to Q FM, then lock the screen. To test comments: News > open a story > scroll down > tick Terms > Post. Each comment has Report and Hide buttons; we aim to review reports within 24 hours. Terms: oromatv.com/terms.php. Privacy: oromatv.com/privacy.php. Contact: info@oromatv.com
```

## 3. Dry run on the real build (owner; TestFlight on an iPhone and the internal-test build on an Android phone)
Do every line before you submit; each is something a reviewer may do.
1. Post a clean comment: it appears at once.
2. Post "see example.com": the held message above appears and the comment is **not** in the list. In the admin it shows under Comments > Pending as "held: filter · link".
3. Untick the Terms box on a first post: refused with "Please tick the box to agree to the Terms of Use."
4. Report a comment (any reason): "Thank you / We have received your report. We aim to review reports within 24 hours.", and the comment disappears from your own list. In the admin it shows under Comments > Reported with the reason, and an email reaches info@oromatv.com.
5. Report while the server has no report route (an old server): the app must say "We could not send your report. Please try again." and must **not** show "Thank you". Only the reply `{"code":"comment_gone"}` counts as "already gone".
6. Hide a name: its comments vanish at once. More > Legal and safety > Hidden commenters > Show again brings them back.
7. Post 7 different comments within 10 minutes: the 7th makes the app ask you to wait (6 per 10 minutes per network; the same words twice from one device are refused as "You already posted that comment.").
8. Press Home during a video: the sound stops within a second (YouTube background rule); lock the phone: it stops. Start Q FM, lock the phone: the radio continues with lock-screen controls. iPhone: silent switch on, video and radio are still audible.
9. Android 13 and later: the radio's media notification appears without a permission prompt.
10. More: Privacy Policy and Terms of Use open (not 404); the Contact form sends an email to info@oromatv.com; "Website contact page" opens https://oromatv.com/contact.php.
11. Airplane mode: friendly offline messages, no blank screen or crash.
12. Fullscreen on an iPhone with a notch: the buttons are not under the notch or home bar (the fullscreen layout does not use safe-area insets yet).
13. Three reports from three different networks hide a comment for everyone: tested on the local server with simulated addresses; a single phone cannot show it. In the admin the comment then shows as "auto-hidden by reports".
Do not submit while step 4 is missing from the admin queue.
