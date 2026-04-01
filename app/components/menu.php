<?php
fwSessionStart();
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
$isLogged = !empty($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
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
        <div class="navbar-nav ms-auto gap-2 align-items-lg-center">
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

          <?php if (!$isLogged): ?>
            <a
              class="nav-link eg-login-link fw-semibold px-4 py-2 rounded-pill text-center text-lg-start mt-2 mt-lg-0 ms-lg-2"
              href="<?= htmlspecialchars(route('logowanie'), ENT_QUOTES, 'UTF-8') ?>"
            >
              <i class="bi bi-box-arrow-in-right me-2"></i>Zaloguj
            </a>
          <?php else: ?>
            <div class="dropdown user-dropdown mt-2 mt-lg-0 ms-lg-2">
              <a
                class="nav-link user-dropdown-toggle fw-semibold px-4 py-2 rounded-pill d-flex align-items-center justify-content-center justify-content-lg-start gap-2"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                <div class="user-icon-circle">
                  <i class="bi bi-person-fill"></i>
                </div>
                <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                <i class="bi bi-chevron-down small opacity-75"></i>
              </a>

              <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu shadow">
                <li>
                  <a class="dropdown-item" href="<?= htmlspecialchars(route('wyloguj'), ENT_QUOTES, 'UTF-8') ?>">
                    Wyloguj
                  </a>
                </li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
  </div>
</nav>