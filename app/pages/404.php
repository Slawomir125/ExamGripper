<?php
pageStart('404', 'blank');
?>

<div class="text-center py-5">
  <h1 class="display-6 mb-3">404</h1>
  <p class="text-muted mb-4">Nie znaleziono strony.</p>
  <a href="<?= htmlspecialchars(fwUrl(''), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
    Wróć na start
  </a>
</div>

<?php pageEnd(); ?>