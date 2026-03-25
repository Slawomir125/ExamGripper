<?php
$menuItems = $templateData['menuItems'] ?? fwConfig('menu_items', []);
$activeMenu = $templateData['activeMenu'] ?? fwCurrentPath();
?>

<?php component('menu', [
    'items' => $menuItems,
    'active' => $activeMenu
]); ?>

<div class="container py-4">
  <?= $content ?>
</div>