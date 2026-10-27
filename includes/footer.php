    </main>

    <!-- Erişilebilir Alt Bilgi (Footer) -->
    <footer class="site-footer no-print">
        <div class="footer-container">
            <div>
                <strong><?= APP_NAME ?></strong> &copy; <?= date('Y') ?> - Tüm hakları saklıdır.
                <span class="text-muted">| WCAG 2.1 AA Uyumlu Erişilebilir Depo Yönetimi</span>
            </div>
            <div>
                <span>Sürüm: <?= APP_VERSION ?></span>
                <span class="text-muted">| Veritabanı: <?= strtoupper(DB_DRIVER) ?></span>
            </div>
        </div>
    </footer>

    <!-- Erişilebilirlik ve Etkileşim Scripti -->
    <script src="assets/js/app.js"></script>
</body>
</html>
