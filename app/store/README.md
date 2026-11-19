# Oroma TV – store submission folder (release 2.0.0)

Everything needed to put the app on the App Store and Google Play. Facts were re-checked against the code on 19 Nov 2026; if the app, API, privacy.php or terms.php change, re-check the files marked below.
**[CONFIRM]** in any file = a fact only the owner can confirm. **[UNVERIFIED]** = could not be checked from the code or a public page.

| File | What it is |
|---|---|
| `pre-submission-checklist.md` | Deploy the website and API, live tests, operations, and the open items. **Start here.** |
| `store-listing.md` | Text to paste into both stores (names, descriptions, keywords, release notes) with the character limits |
| `apple-submission.md` | App Store: click path from the IPA to Submit for Review with EAS from Windows, age rating, App Privacy label, review notes to paste, comment-moderation safeguards for guideline 1.2, the 24-hour process and the admin queue |
| `google-play-submission.md` | Google Play: build and upload, Data safety, foreground-service text, content rating, News declaration, other App content answers, release notes |
| `review-notes.md` | Google "App access" text, how a reviewer reaches each screen, and the dry run to do on the real build |
| `README-screenshots.md` | The image files in `app-store/` and `google-play/` and which slot each goes in |

## Order to use them
1. `pre-submission-checklist.md` sections A to C: put the website and API live, test them, settle staffing for report handling.
2. Build the apps: `apple-submission.md` section 2 (iPhone) and `google-play-submission.md` section 1 (Android).
3. Test the builds on real phones: `review-notes.md` section 3.
4. Fill in the stores: text from `store-listing.md`; forms and answers from `apple-submission.md` and `google-play-submission.md`; images per `README-screenshots.md` (retake `07-form.png` first).
5. Submit. Release the iPhone app manually once the live site and admin queue are stable.

## Rules every text follows
- One sentence about reports: **We aim to review reports within 24 hours.** (office hours are Monday to Friday; never promise more). Never "no ads" (YouTube can show its own ads in its player), never "24/7 live", movies only "when available".
- The message a commenter sees when a comment is held is fixed in `includes/comment-moderation.php` (`COMMENT_HELD_MESSAGE`); the notes quote it exactly.
- The text that is public and must match these files: `privacy.php`, `terms.php`, the comment form and Report sheet in the app, `contact.php`, the admin Comments page.

## Owner-only facts still open ([CONFIRM])
Apple Developer account and who answers Apple; legal owner name for the copyright line; who reviews reports every day including weekends; Q FM streaming rights and that the live stream plays; whether stories are AI-written or imported; the age-rating answers marked [CONFIRM]; the Ads answer (Yes is the cautious one); the unrestricted-web-access answer (No, same on both stores); the existing Android upload keystore.
