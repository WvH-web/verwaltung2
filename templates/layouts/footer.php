
  </div><!-- /container-fluid -->
</main><!-- /wvh-main -->

<!-- ===== FOOTER ===== -->
<footer class="wvh-footer mt-auto py-3">
  <div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
      <span class="text-muted small">
        &copy; <?= date('Y') ?> Wilhelm von Humboldt Online Privatschule &mdash; <?= APP_NAME ?> v<?= APP_VERSION ?>
      </span>
      <span class="text-muted small">
        <i class="bi bi-clock me-1"></i><?= date('H:i') ?> Uhr (Berlin)
      </span>
    </div>
  </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- WvH Custom JS -->
<script src="<?= APP_URL ?>/assets/js/wvh.js"></script>

<?= $extraScript ?? '' ?>
</body>
</html>
