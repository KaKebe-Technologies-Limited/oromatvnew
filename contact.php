<?php
require_once __DIR__ . '/includes/functions.php';

$activeNav       = 'contact';
$pageTitle       = 'Contact Us · ' . SITE_NAME;
$pageDescription = 'Get in touch with ' . SITE_NAME . ' — send us a news tip, advertising inquiry, or general message.';

$success = false;
$errors  = [];
$fields  = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

$subjects = [
    'General Inquiry',
    'News Tip',
    'Advertising',
    'Technical Support',
    'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fields['name']    = trim($_POST['name']    ?? '');
    $fields['email']   = trim($_POST['email']   ?? '');
    $fields['subject'] = trim($_POST['subject'] ?? '');
    $fields['message'] = trim($_POST['message'] ?? '');

    // honeypot
    if (!empty($_POST['website'])) {
        $success = true; // silent discard
    } else {
        if ($fields['name'] === '')                        $errors[] = 'Your name is required.';
        if ($fields['email'] === '')                       $errors[] = 'Your email address is required.';
        elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL))
                                                           $errors[] = 'Please enter a valid email address.';
        if (!in_array($fields['subject'], $subjects, true)) $errors[] = 'Please select a subject.';
        if ($fields['message'] === '')                     $errors[] = 'A message is required.';
        elseif (mb_strlen($fields['message']) < 10)        $errors[] = 'Your message is too short (minimum 10 characters).';

        if (!$errors) {
            $to      = SITE_EMAIL;
            $subject = '[' . SITE_NAME . '] ' . $fields['subject'] . ' from ' . $fields['name'];
            $body    = "Name: {$fields['name']}\n"
                     . "Email: {$fields['email']}\n"
                     . "Subject: {$fields['subject']}\n\n"
                     . "Message:\n{$fields['message']}\n\n"
                     . "---\nSent via " . SITE_URL . "/contact.php";

            $headers  = "From: noreply@oromatv.com\r\n";
            $headers .= "Reply-To: {$fields['email']}\r\n";
            $headers .= "X-Mailer: OromaTV/1.0\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            if (@mail($to, $subject, $body, $headers)) {
                $success = true;
                $fields  = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
            } else {
                $errors[] = 'Message could not be sent right now. Please email us directly at <a href="mailto:info@oromatv.com">info@oromatv.com</a>.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<!-- ── HERO ── -->
<section class="hero-banner hero-banner-compact">
    <div class="hero-banner-bg"></div>
    <div class="container">
        <span class="cat-badge" style="margin-bottom:14px;display:inline-block;">
            <i class="fas fa-envelope"></i> Get In Touch
        </span>
        <h1>Contact Oroma TV</h1>
        <p class="lead">Have a news tip, advertising inquiry, or just want to say hello? We'd love to hear from you.</p>
    </div>
</section>

<!-- ── MAIN CONTENT ── -->
<div class="container section">
    <div class="contact-layout">

        <!-- ── LEFT: FORM ── -->
        <div class="contact-form-col reveal">
            <div class="contact-card">
                <h2 class="contact-card-title">
                    <i class="fas fa-paper-plane"></i> Send Us a Message
                </h2>

                <?php if ($success): ?>
                    <div class="contact-success">
                        <div class="contact-success-icon"><i class="fas fa-circle-check"></i></div>
                        <h3>Message Sent!</h3>
                        <p>Thank you for reaching out. We'll get back to you within 1–2 business days.</p>
                        <a href="<?= h(BASE_PATH) ?>/contact.php" class="btn btn-primary" style="margin-top:16px;">
                            Send Another Message
                        </a>
                    </div>

                <?php else: ?>

                    <?php if ($errors): ?>
                        <div class="contact-errors">
                            <i class="fas fa-circle-exclamation"></i>
                            <ul>
                                <?php foreach ($errors as $e): ?>
                                    <li><?= $e ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= h(BASE_PATH) ?>/contact.php" novalidate>
                        <?= csrf_field() ?>
                        <!-- honeypot -->
                        <input type="text" name="website" class="hp-field"
                               tabindex="-1" autocomplete="off" aria-hidden="true" />

                        <div class="contact-form-row">
                            <div class="form-group">
                                <label for="name">Full Name <span class="req">*</span></label>
                                <input class="form-control<?= in_array('Your name is required.', $errors) ? ' is-error' : '' ?>"
                                       type="text" id="name" name="name"
                                       value="<?= h($fields['name']) ?>"
                                       placeholder="e.g. John Okello" maxlength="100" required />
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address <span class="req">*</span></label>
                                <input class="form-control<?= in_array('Your email address is required.', $errors) || in_array('Please enter a valid email address.', $errors) ? ' is-error' : '' ?>"
                                       type="email" id="email" name="email"
                                       value="<?= h($fields['email']) ?>"
                                       placeholder="you@example.com" maxlength="150" required />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="subject">Subject <span class="req">*</span></label>
                            <select class="form-control<?= in_array('Please select a subject.', $errors) ? ' is-error' : '' ?>"
                                    id="subject" name="subject" required>
                                <option value="">— Select a subject —</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= h($s) ?>"
                                        <?= $fields['subject'] === $s ? 'selected' : '' ?>>
                                        <?= h($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="message">Message <span class="req">*</span></label>
                            <textarea class="form-control<?= in_array('A message is required.', $errors) || in_array('Your message is too short (minimum 10 characters).', $errors) ? ' is-error' : '' ?>"
                                      id="message" name="message" rows="6"
                                      placeholder="Write your message here…"
                                      maxlength="3000" required><?= h($fields['message']) ?></textarea>
                            <div class="form-hint">Maximum 3000 characters.</div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>

                <?php endif; ?>
            </div>
        </div>

        <!-- ── RIGHT: INFO ── -->
        <div class="contact-info-col">

            <!-- contact details -->
            <div class="contact-card reveal">
                <h2 class="contact-card-title">
                    <i class="fas fa-address-card"></i> Contact Information
                </h2>
                <ul class="contact-info-list">
                    <li>
                        <span class="contact-info-icon"><i class="fas fa-envelope"></i></span>
                        <div>
                            <strong>Email</strong>
                            <a href="mailto:info@oromatv.com">info@oromatv.com</a>
                        </div>
                    </li>
                    <li>
                        <span class="contact-info-icon"><i class="fas fa-phone"></i></span>
                        <div>
                            <strong>Phone</strong>
                            <a href="tel:+256772033333">+256 772 033 333</a>
                        </div>
                    </li>
                    <li>
                        <span class="contact-info-icon"><i class="fas fa-location-dot"></i></span>
                        <div>
                            <strong>Address</strong>
                            <span>Oyam Road, Lira City, Uganda</span>
                        </div>
                    </li>
                    <li>
                        <span class="contact-info-icon"><i class="fas fa-clock"></i></span>
                        <div>
                            <strong>Office Hours</strong>
                            <span>Monday – Friday, 9:00 AM – 5:00 PM (EAT)</span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- social links -->
            <div class="contact-card reveal" style="margin-top:22px;">
                <h2 class="contact-card-title">
                    <i class="fas fa-share-nodes"></i> Follow Us
                </h2>
                <div class="contact-socials">
                    <a href="https://www.youtube.com/@oromatv" target="_blank" rel="noopener noreferrer"
                       class="contact-social-btn youtube">
                        <i class="fab fa-youtube"></i>
                        <span>YouTube</span>
                    </a>
                    <a href="https://www.facebook.com/oromatv" target="_blank" rel="noopener noreferrer"
                       class="contact-social-btn facebook">
                        <i class="fab fa-facebook-f"></i>
                        <span>Facebook</span>
                    </a>
                    <a href="https://twitter.com/oromatv" target="_blank" rel="noopener noreferrer"
                       class="contact-social-btn twitter">
                        <i class="fab fa-twitter"></i>
                        <span>Twitter / X</span>
                    </a>
                    <a href="https://www.instagram.com/oromatv" target="_blank" rel="noopener noreferrer"
                       class="contact-social-btn instagram">
                        <i class="fab fa-instagram"></i>
                        <span>Instagram</span>
                    </a>
                    <a href="https://t.me/oromatv" target="_blank" rel="noopener noreferrer"
                       class="contact-social-btn telegram">
                        <i class="fab fa-telegram-plane"></i>
                        <span>Telegram</span>
                    </a>
                </div>
            </div>

            <!-- news tip CTA -->
            <div class="contact-tip-box reveal" style="margin-top:22px;">
                <i class="fas fa-bolt"></i>
                <div>
                    <strong>Got a News Tip?</strong>
                    <p>Are you witnessing breaking news? Send us your story, photos or videos and help us keep the community informed.</p>
                    <a href="mailto:tips@oromatv.com" class="btn btn-primary btn-sm" style="margin-top:10px;">
                        <i class="fas fa-bolt"></i> Submit a Tip
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
