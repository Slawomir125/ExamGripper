<?php
$bodyClass = $templateData['bodyClass'] ?? '';
?>
<!doctype html>
<html lang="pl">
<head >
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= $assetsBase ?>/app.css?v=7" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css">
  

  
  <script src="<?= $assetsBase ?>/app.js?v=8"></script>
  <script src="<?= $assetsBase ?>/send.js?v=7"></script>
  <script src="<?= $assetsBase ?>/modal.js?v=7"></script>
  <script src="<?= $assetsBase ?>/binding.js?v=7"></script>
  <script src="<?= $assetsBase ?>/drag.js?v=8"></script>
  <script src="<?= $assetsBase ?>/animate.js?v=7"></script>
</head>
<body >

<script id="page-data" type="application/json"><?= $pageDataJson ?></script>

<?php require $layoutFile; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>