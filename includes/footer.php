<!-- ── NEWSLETTER ── -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-box reveal">
            <div class="newsletter-text">
                <i class="fas fa-envelope-open-text"></i>
                <div>
                    <h3>Stay Informed</h3>
                    <p>Get the latest Oroma TV news delivered to your inbox daily.</p>
                </div>
            </div>
            <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                <input type="email" placeholder="Your email address" aria-label="Email address" required />
                <button type="submit" class="btn btn-gold">Subscribe <i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </div>
</section>

<!-- ── FOOTER ── -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div>
                <a href="<?= h(BASE_PATH) ?>/index.php" class="footer-brand">
                    <img src="<?= h(BASE_PATH) ?>/img/logo.png" alt="<?= h(SITE_NAME) ?>" />
                </a>
                <p class="footer-tagline">Live TV, radio, and the stories that matter to the Oroma community — streaming free, anywhere in the world.</p>
                <div class="footer-socials">
                    <a href="#" class="social-icon" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="#" class="social-icon" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon" aria-label="Twitter / X"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Explore</h4>
                <nav>
                    <a href="<?= h(BASE_PATH) ?>/index.php">Home</a>
                    <a href="<?= h(BASE_PATH) ?>/news.php">Latest News</a>
                    <a href="<?= h(BASE_PATH) ?>/index.php#watch">Watch Live TV</a>
                    <a href="<?= h(BASE_PATH) ?>/index.php#watch">Radio Stream</a>
                </nav>
            </div>

            <div class="footer-col">
                <h4>Categories</h4>
                <nav>
                    <?php
                    $footerCats = db()->query("SELECT name,slug FROM categories WHERE is_active=1 ORDER BY display_order ASC LIMIT 6")->fetchAll();
                    foreach ($footerCats as $fc):
                    ?>
                        <a href="<?= h(BASE_PATH) ?>/news.php?category=<?= urlencode($fc['slug']) ?>"><?= h($fc['name']) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="footer-col">
                <h4>About</h4>
                <nav>
                    <a href="#">About Oroma TV</a>
                    <a href="#">Contact Us</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Use</a>
                    <a href="#">Advertise</a>
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
