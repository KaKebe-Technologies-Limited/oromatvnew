<!-- ── NEWSLETTER ── -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-box reveal">
            <div class="newsletter-text">
                <span class="newsletter-icon"><i class="fas fa-envelope-open-text"></i></span>
                <div>
                    <h3>Stay informed with Oroma TV</h3>
                    <p>Get the latest news, updates and show announcements straight to your inbox.</p>
                </div>
            </div>
            <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                <input type="email" placeholder="Enter your email" aria-label="Email address" required />
                <button type="submit" class="btn btn-primary">Subscribe <i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </div>
</section>

<!-- ── FOOTER ── -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-about">
                <a href="<?= h(BASE_PATH) ?>/index.php" class="footer-brand" aria-label="<?= h(SITE_NAME) ?> — home">
                    <img src="<?= h(BASE_PATH) ?>/img/logo-trim.png" alt="<?= h(SITE_NAME) ?>" />
                </a>
                <p class="footer-tagline">Live TV, radio, and the stories that matter to the Oroma community — streaming free, anywhere in the world.</p>
                <div class="footer-socials">
                    <?php foreach (social_links() as $s): ?>
                        <a href="<?= h($s['url']) ?>" class="social-icon" target="_blank" rel="noopener noreferrer"
                           aria-label="<?= h($s['label']) ?>"><i class="<?= h($s['icon']) ?>"></i></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="footer-col">
                <h4>Explore</h4>
                <nav>
                    <a href="<?= h(BASE_PATH) ?>/index.php">Home</a>
                    <a href="<?= h(BASE_PATH) ?>/news.php">Latest News</a>
                    <a href="<?= h(BASE_PATH) ?>/watch.php">Watch Live TV</a>
                    <a href="<?= h(BASE_PATH) ?>/previous-shows.php">Previous Shows</a>
                </nav>
            </div>

            <div class="footer-col">
                <h4>Categories</h4>
                <nav>
                    <?php
                    $footerCats = db()->query("SELECT name,slug FROM categories WHERE is_active=1 ORDER BY display_order ASC, name ASC LIMIT 6")->fetchAll();
                    foreach ($footerCats as $fc):
                    ?>
                        <a href="<?= h(BASE_PATH) ?>/news.php?category=<?= urlencode($fc['slug']) ?>"><?= h($fc['name']) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="footer-col">
                <h4>Company</h4>
                <nav>
                    <a href="<?= h(BASE_PATH) ?>/contact.php">About Oroma TV</a>
                    <a href="<?= h(BASE_PATH) ?>/contact.php">Contact Us</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Use</a>
                    <a href="<?= h(BASE_PATH) ?>/contact.php">Advertise</a>
                </nav>
            </div>
        </div>

        <div class="bottom">
            <span>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. All rights reserved.</span>
            <div class="bottom-right">
                <span>Powered by Kakebe Technologies</span>
                <a href="<?= h(BASE_PATH) ?>/admin/login.php" class="admin-login-link">
                    <i class="fas fa-lock"></i> Admin
                </a>
            </div>
        </div>
    </div>
</footer>

<button id="back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<script src="<?= h(BASE_PATH) ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
