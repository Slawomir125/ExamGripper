<?php
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../../framework/framework.php';

$sesja_id = session_id();

if (!$sesja_id) {
    error('no_session', 'Brak aktywnej sesji');
}

$deleted = DB_EXECUTE('DELETE FROM postepy WHERE sesja_id = ?', [$sesja_id]);

output(['deleted' => $deleted]);
?>