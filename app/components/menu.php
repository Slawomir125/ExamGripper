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

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm py-3 eg-navbar">
  <div class="container position-relative">
    
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold fs-4 m-0 eg-navbar-brand" href="<?= route('index') ?>">
      <span class="eg-navbar-brand-icon">
        <i class="bi bi-box-seam-fill eg-navbar-brand-icon-main"></i>
      </span>
      <span>Exam<span class="eg-navbar-brand-accent">Gripper</span></span>
    </a>

    <button class="navbar-toggler border-0 shadow-none p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="offcanvas-lg offcanvas-end eg-navbar-offcanvas" tabindex="-1" id="mobileOffcanvas">
      <div class="offcanvas-header py-4 px-4 border-bottom eg-navbar-offcanvas-header">
        <h5 class="offcanvas-title d-flex align-items-center gap-2 fw-bold text-white m-0">
          <span class="eg-navbar-brand-icon">
            <i class="bi bi-box-seam-fill eg-navbar-brand-icon-offcanvas"></i>
          </span>
          Exam<span class="eg-navbar-brand-accent">Gripper</span>
        </h5>
        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas" data-bs-target="#mobileOffcanvas" aria-label="Close"></button>
      </div>
      
      <div class="offcanvas-body px-4 px-lg-0 py-4 py-lg-0">
        <div class="navbar-nav ms-auto gap-2">
          <?php foreach ($items as $item): ?>
            <?php
              $url = (string) ($item['url'] ?? '');
              $label = (string) ($item['label'] ?? ($url === '' ? 'index' : $url));
              $itemPath = menuPath($url);
              $isActive = ($itemPath === $currentPath);
            ?>

            <a
              class="nav-link custom-nav-link fw-medium px-4 py-2 rounded-pill text-center text-lg-start <?= $isActive ? 'active shadow-sm' : '' ?>"
              href="<?= htmlspecialchars(fwUrl($url), ENT_QUOTES, 'UTF-8') ?>"
            >
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    
  </div>
</nav>