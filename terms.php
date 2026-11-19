<?php
/**
 * Terms of Use — https://oromatv.com/terms.php
 * Linked from the website footer, the comment forms (website and apps) and both store listings.
 * Contains the community rules that App Store guideline 1.2 and Google Play's User Generated Content policy require:
 * no tolerance for objectionable content, a way to report it, timely action, the right to remove content and block abusers.
 */
require_once __DIR__ . '/includes/functions.php';

$activeNav       = '';
$pageTitle       = 'Terms of Use · ' . SITE_NAME;
$pageDescription = 'The rules for using ' . SITE_NAME . ': the website, the Android and iPhone apps, and the comments section.';
$extraHeadHtml   = '<link rel="stylesheet" href="' . h(BASE_PATH) . '/assets/css/legal.css?v=' . filemtime(__DIR__ . '/assets/css/legal.css') . '" />';
$updated         = '18 November 2026';
$email           = SITE_EMAIL;

require __DIR__ . '/includes/header.php';
?>

<section class="legal-hero">
    <div class="container">
        <h1>Terms of Use</h1>
        <p>Last updated: <?= h($updated) ?> · Applies to oromatv.com and the Oroma TV apps for Android and iPhone</p>
    </div>
</section>

<div class="container">
<main class="legal">

    <nav class="toc" aria-label="Contents">
        <b>On this page</b>
        <ol>
            <li><a href="#using">Using Oroma TV</a></li>
            <li><a href="#rules">Community rules for comments</a></li>
            <li><a href="#reports">Reporting and moderation</a></li>
            <li><a href="#content">Your content</a></li>
            <li><a href="#ours">Our content, YouTube and radio</a></li>
            <li><a href="#service">Live streams and availability</a></li>
            <li><a href="#liability">Disclaimers</a></li>
            <li><a href="#changes">Changes, law and contact</a></li>
        </ol>
    </nav>

    <p>By using the <?= h(SITE_NAME) ?> website or apps, you agree to these terms and to our <a href="<?= h(BASE_PATH) ?>/privacy.php">Privacy Policy</a>. If you do not agree, please do not use them.</p>

    <h2 id="using">1. Using Oroma TV</h2>
    <p>Watching, listening and reading are free and need no account. You may use <?= h(SITE_NAME) ?> for your own personal, non-commercial use. Please do not try to break, overload or misuse the service, copy it automatically in bulk, or get around its protections.</p>

    <h2 id="rules">2. Community rules for comments</h2>
    <div class="note"><strong>We have no tolerance for objectionable content or abusive users.</strong> Comments are for respectful conversation about the story.</div>
    <p>You must not post, and we will remove, content that:</p>
    <ul>
        <li>is hateful, or attacks people for their tribe, ethnicity, race, religion, gender, disability, nationality or any similar trait;</li>
        <li>harasses, bullies, threatens or encourages violence or self-harm;</li>
        <li>is sexually explicit, or shows or exploits children in any way;</li>
        <li>is false or misleading in a way that could harm people, or that impersonates another person or organisation;</li>
        <li>shares someone's private information (phone number, address, photos) without their permission;</li>
        <li>is spam, advertising, scams, or links to harmful or unrelated sites;</li>
        <li>breaks the law, or copies other people's work without permission.</li>
    </ul>
    <p>Comments are checked automatically before they appear, and some are held for our team to review first.</p>

    <h2 id="reports">3. Reporting and moderation</h2>
    <ul>
        <li><strong>Report a comment.</strong> In the app, press <em>Report</em> next to any comment and choose a reason. On the website, use the <em>Report</em> button under the comment. You can also email <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a>.</li>
        <li><strong>Hide a commenter.</strong> There are no accounts, so in the app you can hide all comments that use a commenter's name. This works on your phone only, and you can undo it at any time in More › Hidden commenters.</li>
        <li><strong>What we do.</strong> A comment that is reported from several different networks is hidden automatically until our team checks it. <strong>We aim to review reports within 24 hours.</strong> If a comment breaks these rules we remove it.</li>
        <li><strong>Consequences.</strong> We may remove any comment, and put a commenter on our block list, at our discretion and without notice, especially for repeated or serious breaches. New comments from a blocked name, email address or device are held for our team to review and may not appear. We may report illegal content to the authorities.</li>
    </ul>

    <h2 id="content">4. Your content</h2>
    <p>You are responsible for what you post. You keep ownership of your comments, and by posting you give <?= h(SITE_NAME) ?> a free licence to show, store and, if needed, edit or remove them on our website and apps. Please only post things you have the right to share. To ask us to remove your own comment, email <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a>.</p>

    <h2 id="ours">5. Our content, YouTube and radio</h2>
    <p>The stories, photos, programmes, logos and design of <?= h(SITE_NAME) ?> belong to us or to the people who license them to us. You may share links to them and use the Share buttons, but you may not copy, sell or republish our content without our written permission. If you believe something on <?= h(SITE_NAME) ?> infringes your rights, email us and we will look into it promptly.</p>
    <p><strong>YouTube.</strong> Videos in our apps and on our website play through YouTube, and the apps use YouTube API Services. <strong>By using them you agree to be bound by the <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener noreferrer">YouTube Terms of Service</a>.</strong> YouTube may show its own ads in its player, under its own terms. Google's handling of your data is described in the <a href="http://www.google.com/policies/privacy" target="_blank" rel="noopener noreferrer">Google Privacy Policy</a> and in our <a href="<?= h(BASE_PATH) ?>/privacy.php#third">Privacy Policy</a>.</p>
    <p><strong>Radio.</strong> The Q&nbsp;FM stream is provided by the station's streaming provider and is covered by that provider's own terms.</p>

    <h2 id="service">6. Live streams and availability</h2>
    <p>We aim to be on air and online all the time, but we cannot promise it. The programme guide can change. When nothing is scheduled, the Live screen may show a replay or a selection of past shows. Some programmes are recordings shown at a set time so everyone watches together; the Live screen and the Watch Live page say "everyone watches together" for these. News stories are published in good faith; if you spot a mistake, tell us.</p>

    <h2 id="liability">7. Disclaimers</h2>
    <p><?= h(SITE_NAME) ?> is provided "as is". To the extent the law allows, we are not responsible for losses that come from using, or not being able to use, the website or apps, or from comments written by other people. Nothing in these terms limits any right you have by law that cannot be limited.</p>

    <h2 id="changes">8. Changes, law and contact</h2>
    <p>We may update these terms; the date at the top shows the latest version, and continuing to use <?= h(SITE_NAME) ?> means you accept the update. These terms are governed by the laws of Uganda. Questions, reports or complaints: <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a>, +256&nbsp;772&nbsp;033&nbsp;333, or the <a href="<?= h(BASE_PATH) ?>/contact.php">contact page</a>. Postal address: <?= h(SITE_NAME) ?>, Oyam Road, Lira City, Uganda.</p>
</main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
