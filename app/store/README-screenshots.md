# Oroma TV – store images

All images are flat PNG (no transparency) at the exact sizes the stores ask for. 9 screens per set.
The text to paste is in `store-listing.md`; the order to use all files in this folder is in `README.md`.

## Apple App Store → `app-store/`
| Folder | App Store Connect slot | Use it when |
|---|---|---|
| `screenshots-iphone-6.3in-1206x2622/` | **iPhone with Dynamic Island (medium display)**: the 6.1" and 6.3" iPhone slot (1206 × 2622) | Always. This is the slot your screen shows first. |
| `screenshots-iphone-6.9in-1320x2868/` | **iPhone 6.9" display** (large) | Always if the console offers it. Apple can also scale one set down to the other iPhone sizes. |
| `screenshots-ipad-mini-8.3in-1488x2266/` | iPad mini 8.3" | **Only if the app supports iPad** (see the note below). |
| `screenshots-ipad-13in-2064x2752/` | iPad 13" display | **Only if the app supports iPad.** |

**iPad note.** The app currently ships as iPhone-only (`supportsTablet` is false in `app.json`), so App Store Connect should not ask for iPad screenshots once a build is chosen. The iPad sets are the same screens rendered at tablet width: they work, but they are the phone layout stretched, not a tablet design, so an App Review reader may see them as "an iPhone app scaled up". If App Store Connect insists on iPad screenshots, upload the iPad mini or 13" set. For a proper iPad listing the app needs tablet support and a tablet layout first (a new build).

`app-icon-1024x1024.png` is already inside the app build; App Store Connect takes the icon from the uploaded build, so you do not upload it.

## Google Play → `google-play/`
| File | Slot |
|---|---|
| `app-icon-512x512.png` | Main store listing → **App icon** |
| `feature-graphic-1024x500.png` | Main store listing → **Feature graphic** |
| `screenshots-phone-1080x2160/*.png` | Main store listing → **Phone screenshots** (2 to 8 allowed; there are 9, so leave one out: `07-form.png` or `03-news.png`) |

## The 9 screens, in the order to upload
1. `01-live` – Live screen: player with Back / Stop / Forward / Fullscreen, Share, Listen to Q FM, Radio
2. `02-home` – Home: live card, top stories, categories
3. `03-news` – News list with search and categories
4. `04-story` – A story with photo, headline and text
5. `05-shows` – Shows & movies: on-air banner, featured episode, shows grid
6. `06-comments` – Comments with **Report** and **Hide <name>** under every comment
7. `07-form` – Comment form with the **Terms of Use tick box**, "We aim to review reports within 24 hours." and Post comment
8. `08-report` – The Report sheet (reasons list)
9. `09-more` – More: contact, Privacy Policy, Terms of Use, Hidden commenters

Screens 6, 7 and 8 show the safeguards App Store guideline 1.2 and Google's User Generated Content policy ask for. Keep them.

## How these were made, and what to know
- Rendered from the app's own code (the web build of the same screens) at real device size, with a drawn status bar and home indicator. Everything on screen is the real app. They are not mock-ups.
- Screens 1–5 and 9 use the live Oroma TV data (real stories, shows and broadcast). Curated on purpose: crime and arrest stories are left out of the Home and News lists, and the stray "More videos" card is left off the Live screen. Screens 6–8 use a local copy of a football story with three made-up commenters (Grace A., Moses O., Brenda L.), as the stores ask for made-up names; those test comments are removed from the database.
- The Live and Home images show what was on air and published on 19 Nov 2026. Ask for a retake whenever the live content changes (the capture script is kept outside the repository).
- Not included yet: a premiere page with the countdown, the Q FM radio bar, and the new "My profile" screen (add them after the profile feature ships and a real premiere is scheduled).
- When you have a real iPhone or Android phone with the test build, you can replace any of these with real device screenshots. Both stores accept either.
