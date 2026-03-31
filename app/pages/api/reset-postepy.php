<?php
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../../framework/framework.php';

if (empty($_SESSION['user_id'])) {
    error('unauthorized', 'Musisz być zalogowany', 403);
}

$user_id = (int) $_SESSION['user_id'];

$deleted = DB_EXECUTE(
    'DELETE FROM postepy WHERE user_id = ?',
    [$user_id]
);

output(['deleted' => $deleted]);
?>