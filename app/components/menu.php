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

<style>
.custom-nav-link {
    color: rgba(255, 255, 255, 0.6) !important;
    transition: all 0.2s ease-in-out;
}
.custom-nav-link:hover {
    color: #fff !important;
    background-color: rgba(255, 255, 255, 0.08);
}
.custom-nav-link.active {
    color: #fff !important;
    background-color: rgba(255, 255, 255, 0.1);
}
.user-dropdown-toggle {
    color: #fff !important;
    background-color: rgba(255, 255, 255, 0.08);
    transition: all 0.2s ease-in-out;
    border: 0;
    text-decoration: none;
}
.user-dropdown-toggle:hover,
.user-dropdown-toggle:focus,
.user-dropdown:hover .user-dropdown-toggle {
    background-color: rgba(255, 255, 255, 0.12);
    color: #fff !important;
}
.user-icon-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background-color: rgba(56, 189, 248, 0.18);
    color: #38bdf8;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.user-dropdown-menu {
    background-color: #0f172a;
    border: 1px solid rgba(255,255,255,0.08);
    min-width: 180px;
}
.user-dropdown-menu .dropdown-item {
    color: rgba(255,255,255,0.85);
}
.user-dropdown-menu .dropdown-item:hover,
.user-dropdown-menu .dropdown-item:focus {
    background-color: rgba(255,255,255,0.08);
    color: #fff;
}
@media (min-width: 992px) {
    .user-dropdown:hover .dropdown-menu {
        display: block;
        margin-top: 0;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm py-3" style="background-color: #0f172a !important; border-bottom: 1px solid rgba(255,255,255,0.05);">
  <div class="container position-relative">
    
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold fs-4 m-0" style="color: #fff; letter-spacing: -0.5px;" href="<?= route('index') ?>">
      <span style="color: #38bdf8; display: flex;">
        <i class="bi bi-box-seam-fill" style="font-size: 1.5rem; line-height: 1;"></i>
      </span>
      <span>Exam<span style="color: #38bdf8;">Gripper</span></span>
    </a>

    <button class="navbar-toggler border-0 shadow-none p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="offcanvas-lg offcanvas-end" tabindex="-1" id="mobileOffcanvas" style="background-color: #0f172a;">
      <div class="offcanvas-header py-4 px-4 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
        <h5 class="offcanvas-title d-flex align-items-center gap-2 fw-bold text-white m-0">
          <span style="color: #38bdf8; display: flex;">
            <i class="bi bi-box-seam-fill" style="font-size: 1.25rem;"></i>
          </span>
          Exam<span style="color: #38bdf8;">Gripper</span>
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
              class="nav-link custom-nav-link fw-medium px-4 py-2 rounded-pill text-center text-lg-start"
              href="<?= htmlspecialchars(route('logowanie'), ENT_QUOTES, 'UTF-8') ?>"
            >
              Zaloguj
            </a>
          <?php else: ?>
            <div class="dropdown user-dropdown mt-2 mt-lg-0 ms-lg-2">
              <a
                class="nav-link user-dropdown-toggle fw-medium px-4 py-2 rounded-pill d-flex align-items-center justify-content-center justify-content-lg-start gap-2"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                <div class="user-icon-circle">
                  <i class="bi bi-person-fill"></i>
                </div>
                <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
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