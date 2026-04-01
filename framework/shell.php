<?php
$bodyClass = $templateData['bodyClass'] ?? '';
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    html, body {
        min-height: 100%;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
    }

    .app-page {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .app-main {
        flex: 1 0 auto;
    }

    .app-footer {
        flex-shrink: 0;
    }
  </style>

  <link href="<?= $assetsBase ?>/app.css?v=7" rel="stylesheet">

  <script src="<?= $assetsBase ?>/app.js?v=8"></script>
  <script src="<?= $assetsBase ?>/send.js?v=7"></script>
  <script src="<?= $assetsBase ?>/modal.js?v=7"></script>
  <script src="<?= $assetsBase ?>/binding.js?v=7"></script>
  <script src="<?= $assetsBase ?>/drag.js?v=8"></script>
  <script src="<?= $assetsBase ?>/animate.js?v=7"></script>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
  <div class="app-page">
    <script id="page-data" type="application/json"><?= $pageDataJson ?></script>

    <main class="app-main">
      <?php require $layoutFile; ?>
    </main>

    <footer class="app-footer d-flex flex-wrap justify-content-between align-items-center p-3 border-top">
      <p class="col-md-4 mb-0 text-body-secondary">© 2025 ExamGripper, Inc</p>
      <ul class="nav col-md-4 justify-content-end">
        <li class="nav-item"><a href="<?= route("regulamin"); ?>" class="nav-link px-2 text-body-secondary">Regulamin</a></li>
        <li class="nav-item"><a href="<?= route("kontakt"); ?>" class="nav-link px-2 text-body-secondary">Kontakt</a></li>
      </ul>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>