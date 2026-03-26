<?php
// framework/router.php

function fwDispatch(): void
{
  $page = $_GET['page'] ?? 'persons';
  $cfg = fwConfig();

  if (!empty($cfg['page_underscore_to_slash'])) {
    $page = str_replace('_', '/', $page);
  }

  $page = trim($page, '/');
  if ($page === '') $page = 'persons';

  // Allow only safe chars and slashes
  $page = preg_replace('/[^a-zA-Z0-9_\/\-]/', '', $page);

  $file = dirname(__DIR__) . '/app/pages/' . $page . '.php';

  if (!is_file($file)) {
    // Check if it's an API call
    if (str_starts_with($page, 'api/')) {
      $apiPath = substr($page, 4); // remove 'api/'
      $file = dirname(__DIR__) . '/app/api/' . $apiPath . '.php';
      if (is_file($file)) {
        require $file;
        return;
      }
    }
    // fallback
    $file = dirname(__DIR__) . '/app/pages/persons.php';
  }

  require $file;
}
