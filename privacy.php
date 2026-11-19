<?php
/**
 * Privacy Policy — https://oromatv.com/privacy.php
 * Linked from the website footer, the mobile apps (More → Privacy Policy) and both store listings.
 * Keep it in step with what the code really does: comments and reports (includes/comment-moderation.php, api/routes.php,
 * comment-submit.php), the contact form, the anonymous viewer heartbeat (includes/live-functions.php record_viewer),
 * rate limiting (api/bootstrap.php rate_limit), and what the app keeps on the phone (app/lib/presence.js, app/lib/moderation.js).
 * Numbers quoted here (7 days for viewer IDs, 90 days for a name or e-mail block = COMMENT_BAN_DAYS, 30 days for a device block =
 * COMMENT_DEVICE_BAN_DAYS, rate-limit windows from 1 minute to 1 hour in api/routes.php) must match those files.
 */
require_once __DIR__ . '/includes/functions.php';

$activeNav       = '';
$pageTitle       = 'Privacy Policy · ' . SITE_NAME;
$pageDescription = 'How ' . SITE_NAME . ' handles your information on the website and in the Android and iPhone apps.';
$extraHeadHtml   = '<link rel="stylesheet" href="' . h(BASE_PATH) . '/assets/css/legal.css?v=' . filemtime(__DIR__ . '/assets/css/legal.css') . '" />';
$updated         = '18 November 2026';
$email           = SITE_EMAIL;

require __DIR__ . '/includes/header.php';
?>

<section class="legal-hero">
    <div class="container">
        <h1>Privacy Policy</h1>
        <p>Last updated: <?= h($updated) ?> · Applies to oromatv.com and the Oroma TV apps for Android and iPhone</p>
    </div>
</section>

<div class="container">
<main class="legal">

    <nav class="toc" aria-label="Contents">
        <b>On this page</b>
        <ol>
            <li><a href="#who">Who we are</a></li>
            <li><a href="#collect">What we collect</a></li>
            <li><a href="#use">How we use it</a></li>
            <li><a href="#share">Who sees it</a></li>
            <li><a href="#third">Third-party services</a></li>
            <li><a href="#keep">How long we keep it</a></li>
            <li><a href="#rights">Your choices and rights</a></li>
            <li><a href="#kids">Children</a></li>
            <li><a href="#security">Security</a></li>
            <li><a href="#changes">Changes and contact</a></li>
        </ol>
    </nav>

    <p>This policy explains what information <?= h(SITE_NAME) ?> handles when you read our news, watch our live broadcasts, listen to Q&nbsp;FM or use our apps, and what we do with it. We keep it short and plain. <strong>We do not sell your information. We add no advertising of our own to the apps, and they contain no advertising or tracking software of our own.</strong> Videos play through YouTube, which may show its own ads under its own terms (see section 5).</p>

    <h2 id="who">1. Who we are</h2>
    <p><?= h(SITE_NAME) ?> is a television and news service based in Lira City, Uganda (Oyam Road, Lira City), with a Q&nbsp;FM radio player. We publish the website <strong>oromatv.com</strong> and the Oroma TV apps for Android and iPhone, which are built and maintained for us by Kakebe Technologies Limited. In this policy "we" means <?= h(SITE_NAME) ?>. You can reach us at <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a> or on +256&nbsp;772&nbsp;033&nbsp;333.</p>

    <h2 id="collect">2. What we collect</h2>
    <p>You do not need an account to use <?= h(SITE_NAME) ?>. We only handle the information below.</p>
    <table>
        <thead><tr><th>Information</th><th>When</th><th>Why</th></tr></thead>
        <tbody>
            <tr><td data-l="Information"><strong>Comments</strong>: the name you type, an optional email address, your comment and the time</td><td data-l="When">Only if you post a comment on a story</td><td data-l="Why">To show your comment under the story and to moderate comments. Your name and comment are public; your email address is never shown.</td></tr>
            <tr><td data-l="Information"><strong>A one-way code for your device</strong>, stored with every comment you post and every report you send (see "About the one-way codes" below)</td><td data-l="When">When you post a comment or report one</td><td data-l="Why">To hold new comments from a device we blocked for abuse, and to count each device's report on a comment only once. In the app it is made from your anonymous viewer ID; on the website it is made from your network address (IP) and browser type.</td></tr>
            <tr><td data-l="Information"><strong>Reports</strong> about a comment: the reason you choose, whether it came from the app or the website, the time, your device code and a second one-way code for your network address</td><td data-l="When">Only if you report a comment</td><td data-l="Why">To keep the community safe: to count how many different networks reported a comment and to ignore repeat reports. We do not show anyone who reported a comment.</td></tr>
            <tr><td data-l="Information"><strong>Block list</strong>: if we block someone for breaking the community rules, the name and email address they used, a one-way device code, an internal note, which team member added it, the date and when the block ends (unless it was made permanent)</td><td data-l="When">Only if we block a commenter</td><td data-l="Why">So that new comments from that name, email address or device are held for our team to review.</td></tr>
            <tr><td data-l="Information"><strong>Messages to us</strong>: name, email address, subject and message</td><td data-l="When">Only if you use the contact form</td><td data-l="Why">To read and answer your message. The message is emailed to us and is not published.</td></tr>
            <tr><td data-l="Information"><strong>An anonymous viewer ID</strong>: a random code made on your device (it is not your name, email or phone number), whether you are using the app or the website, which programme or channel you were watching (a scheduled programme, the main channel or the off-air playlist), and when it was first and last seen</td><td data-l="When">While the Live screen or Watch Live page is open (about every 30 seconds)</td><td data-l="Why">To count how many people are watching right now and how many watched each programme. On its own it does not tell us who you are, but in the app the same ID is used to make the device code stored with your comments and reports, so while the ID is stored the two can be linked.</td></tr>
            <tr><td data-l="Information"><strong>Story views</strong>: how many times each story was opened</td><td data-l="When">When you open a story</td><td data-l="Why">To show which stories are popular. It is a total, not a record of you.</td></tr>
            <tr><td data-l="Information"><strong>Technical data</strong>: your IP address, device and browser type and the pages you request, including words you search for (they are part of the web address)</td><td data-l="When">Automatically, on every visit</td><td data-l="Why">Our hosting company keeps standard server records for security and fixing faults. Our servers also use your IP address to make a short code that limits how often one network can post comments or send reports. Those small files hold only times, not what you wrote.</td></tr>
        </tbody>
    </table>

    <p><strong>About the one-way codes.</strong> The device code and the network code are one-way hashes (HMAC): your device or network details are scrambled with a secret key that stays on our server, and only the scrambled result is stored. We do not store your IP address or browser details in the comment or report records. The codes are not your name or email address, but the same device always gives the same code. That is how we spot repeat abuse and count different reporters, so we treat the codes as personal information. We use them for nothing else and do not share them.</p>
    <p><strong>On the website</strong> we use one cookie that is necessary for the site to work and keep forms safe. It holds a random session code and no personal information. Our server may also send the same cookie to the apps, which may keep it; we use it for nothing else. On the Watch Live page your browser's local storage keeps your anonymous viewer ID. We use no advertising or tracking cookies.</p>
    <p><strong>In the apps</strong> we do not ask for, and cannot see, your location, contacts, photos, camera or microphone. The apps keep four things in your phone's secure storage and nowhere else: your anonymous viewer ID, the list of commenters you chose to hide (the names as shown with their comments), the numbers of the comments you reported (so they disappear from your view) and a note that you agreed to the Terms of Use. The hidden list is never sent to us.</p>

    <h2 id="use">3. How we use it</h2>
    <ul>
        <li>to show comments and keep them respectful (see our <a href="<?= h(BASE_PATH) ?>/terms.php">Terms of Use</a>);</li>
        <li>to check new comments automatically, hide a comment when several different networks report it, review reports, and remove content or block abusers;</li>
        <li>to answer your messages;</li>
        <li>to show how many people are watching live, and which stories are popular;</li>
        <li>to protect the website, the apps and our viewers from spam and misuse, including limiting how often one device or network can post comments or send reports;</li>
        <li>to meet legal duties when the law requires it.</li>
    </ul>

    <h2 id="share">4. Who sees it</h2>
    <ul>
        <li><strong>Everyone</strong> can see the name and text of a comment you post.</li>
        <li><strong>Our team</strong> sees comments, email addresses you give us, reports, the block list and messages.</li>
        <li><strong>Service providers</strong> that run our service for us: our web hosting company, Hostinger, and the content delivery network it uses (they store the website and the database, and keep server records), and the services in section 5. They may only use the information to provide their service.</li>
        <li><strong>Authorities</strong>, if we are legally required to share something, or must protect people from harm.</li>
    </ul>
    <p>We do not sell personal information and we do not share it for advertising.</p>

    <h2 id="third">5. Third-party services</h2>
    <ul>
        <li><strong>YouTube.</strong> Our live programmes and many shows play through YouTube's player inside the app and the website. While a video loads or plays, YouTube may receive information such as your IP address and device details, may store cookies or similar data, and may show its own ads, under its own terms and policy. We do not choose those ads. <strong>The Oroma TV app uses YouTube API Services; by using it you also agree to be bound by the <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener noreferrer">YouTube Terms of Service</a>, and Google's handling of your data is described in the <a href="http://www.google.com/policies/privacy" target="_blank" rel="noopener noreferrer">Google Privacy Policy</a>.</strong></li>
        <li><strong>Q&nbsp;FM radio.</strong> The Q&nbsp;FM stream is provided by the station's streaming provider. When you press "Listen to Q&nbsp;FM" your device connects to that provider, which sees the same technical data (such as your IP address) that any radio stream sees.</li>
        <li><strong>Website extras.</strong> The website loads fonts and icons from Google Fonts and cdnjs, and social buttons open Facebook, X, WhatsApp, Telegram or other services only if you press them.</li>
        <li><strong>Add to calendar.</strong> This opens Google Calendar in your browser only if you choose it.</li>
        <li><strong>Advertising.</strong> The website footer has an "Advertise" link. It opens our contact page, where you can send us an advertising enquiry. The website does not load any advertising network.</li>
    </ul>
    <p>Those services have their own privacy policies, and we do not control them.</p>

    <h2 id="keep">6. How long we keep it</h2>
    <p>We delete information on request (see section 7). Apart from that, these are the periods we apply. Where we say "until we remove it", nothing deletes it automatically.</p>
    <ul>
        <li><strong>Comments</strong> stay until we remove them or you ask us to.</li>
        <li><strong>Reports</strong> are kept for as long as the comment they are about, and are deleted with it. We do not delete them on a schedule.</li>
        <li><strong>Block list entries</strong> stop applying after 90 days for a name or email address and after 30 days for a device, unless an administrator makes a name or email block permanent. The entry itself stays in our records until we remove it; nothing deletes it automatically.</li>
        <li><strong>Messages to us</strong> are kept in our email for as long as needed to deal with them.</li>
        <li><strong>Anonymous viewer IDs</strong> are deleted about a week after they were last seen. The clean-up runs from time to time, so it can sometimes take a little longer.</li>
        <li><strong>Spam-limiting files</strong> hold only the times of recent requests under a code made from your network address. Entries stop counting after a short window (from one minute to an hour). The small files stay in the server's temporary folder.</li>
        <li><strong>Server records</strong> are kept by our hosting provider for the period it sets.</li>
        <li><strong>On your phone or browser</strong>, the items in section 2 stay until you remove them (see section 7).</li>
    </ul>

    <h2 id="rights">7. Your choices and rights</h2>
    <p>Under Uganda's Data Protection and Privacy Act, 2019 (and similar laws elsewhere) you may ask us to show you the personal information we hold about you, correct it, or delete it. The simplest way is to email <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a> with the name or email you used and, for a comment, the story it is under. <strong>We will respond within 30 days, and remove a comment you ask us to remove sooner than that.</strong> The anonymous viewer ID and the one-way codes are not linked to a name, so we may not be able to find them from your name alone; if that is the case we will tell you.</p>
    <ul>
        <li>You can use the apps and the website without giving us any personal information. Commenting and the contact form are optional.</li>
        <li>In the app you can report any comment, and hide all comments that use a commenter's name. Hiding works on your phone only, and you can undo it in More › Hidden commenters.</li>
        <li>You can clear your browser's local storage to remove the anonymous viewer ID from the website. In the app, you can show hidden commenters again in More › Hidden commenters. On Android, uninstalling the app removes the items listed in section 2. On iPhone the app keeps its items in the Keychain, which iOS may keep after the app is deleted, so a reinstalled app can still remember your hidden commenters, the comments you reported and the viewer ID. They are small and never leave your phone, except the viewer ID, which our server forgets about a week after it was last seen.</li>
    </ul>
    <p>If you are unhappy with how we handled your information you may also complain to Uganda's Personal Data Protection Office.</p>

    <h2 id="kids">8. Children</h2>
    <p><?= h(SITE_NAME) ?> is a general news and entertainment service and is not directed at children under 13. We do not knowingly collect personal information from children under 13. If you think a child under 13 has sent us personal information, email us and we will delete it.</p>

    <h2 id="security">9. Security</h2>
    <p>The website and the apps talk to our servers over encrypted HTTPS connections. We limit who can see comments, emails, reports and messages. The secret key behind the one-way codes is kept on our server. No online service is completely secure, so we cannot promise absolute security. Our hosting provider may keep data in data centres outside Uganda.</p>

    <h2 id="changes">10. Changes and contact</h2>
    <p>If we change this policy we will update the date at the top, and tell you in the app or on the website if the change is important. Questions or requests: <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a>, +256&nbsp;772&nbsp;033&nbsp;333, or use the <a href="<?= h(BASE_PATH) ?>/contact.php">contact page</a>. Postal address: <?= h(SITE_NAME) ?>, Oyam Road, Lira City, Uganda.</p>

    <div class="note">Our <a href="<?= h(BASE_PATH) ?>/terms.php">Terms of Use</a> set out the community rules for comments and how we handle reports. We aim to review reports within 24 hours.</div>
</main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
