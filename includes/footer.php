<footer class="site-footer">
    <div class="container">
        <div class="inner">
            <a href="<?= h(BASE_PATH) ?>/index.php" class="footer-brand">
                <img src="<?= h(BASE_PATH) ?>/img/logo.png" alt="<?= h(SITE_NAME) ?>" />
            </a>
            <nav>
                <a href="<?= h(BASE_PATH) ?>/index.php">Home</a>
                <a href="<?= h(BASE_PATH) ?>/news.php">News</a>
                <a href="<?= h(BASE_PATH) ?>/index.php#watch">Watch Live</a>
            </nav>
        </div>
        <div class="bottom">
            <span>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. All rights reserved.</span>
            <span class="bottom-right">
                Powered by Kakebe Technologies
                <a href="<?= h(BASE_PATH) ?>/admin/login.php" class="admin-login-link"><i class="fas fa-lock"></i> Admin Login</a>
            </span>
        </div>
    </div>
</footer>

<script src="<?= h(BASE_PATH) ?>/assets/js/main.js"></script>
</body>
</html>
