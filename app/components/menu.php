<?php
$items = $items ?? fwConfig('menu_items', []);

function menuPath(string $url): string
{
    $url = trim($url);

    if ($url === '' || $url === '/') {
        return 'index';
    }

    $url = trim($url, '/');

    if ($url === '') {
        return 'index';
    }

    return $url;
}

$currentPath = menuPath(fwCurrentPath());
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">
      <img src="<?=fwBaseUrl(); ?>/public/img/logo.png" alt="Bootstrap" height="40">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainMenu">
      <div class="navbar-nav ms-auto">
        <?php foreach ($items as $item): ?>
          <?php
            $url = (string) ($item['url'] ?? '');
            $label = (string) ($item['label'] ?? ($url === '' ? 'index' : $url));
            $itemPath = menuPath($url);
            $isActive = ($itemPath === $currentPath);
          ?>

          <a
            class="nav-link <?= $isActive ? 'active' : '' ?>"
            href="<?= htmlspecialchars(fwUrl($url), ENT_QUOTES, 'UTF-8') ?>"
          >
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</nav>