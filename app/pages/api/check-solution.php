<?php
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../../framework/framework.php';

if (empty($_SESSION['user_id'])) {
    error('unauthorized', 'Musisz być zalogowany', 403);
}

$user_id = (int) $_SESSION['user_id'];

$data       = input();
$id_pytania = (int) ($data['id_pytania'] ?? 0);
$kolejnosc  = $data['kolejnosc'] ?? [];

if ($id_pytania <= 0 || !is_array($kolejnosc)) {
    error('invalid_input', 'Nieprawidłowe dane wejściowe');
}

// Pobierz poprawną kolejność
$bloki = DB_QUERY(
    'SELECT id, poprzedni_blok_id FROM bloki_kodu WHERE id_pytania = ? ORDER BY id ASC',
    [$id_pytania]
);

// Buduj poprawną kolejność na podstawie poprzedni_blok_id (linked list)
$block_map = [];
foreach ($bloki as $blok) {
    $block_map[$blok['id']] = $blok;
}

$current = null;
foreach ($block_map as $id => $blok) {
    if ($blok['poprzedni_blok_id'] === null) {
        $current = $id;
        break;
    }
}

$correct_order = [];
while ($current !== null) {
    $correct_order[] = $current;
    $next = null;
    foreach ($block_map as $id => $blok) {
        if ($blok['poprzedni_blok_id'] == $current) {
            $next = $id;
            break;
        }
    }
    $current = $next;
}

// Sprawdź punkty: każdy bloczek na właściwej pozycji +1
$points      = 0;
$wrong_blocks = [];
$user_order  = $kolejnosc;

foreach ($user_order as $index => $block_id) {
    if (isset($correct_order[$index]) && $correct_order[$index] == $block_id) {
        $points++;
    } else {
        $wrong_blocks[] = $block_id;
    }
}

$correct = count($wrong_blocks) === 0 && count($user_order) === count($correct_order);

// Zapisz postęp powiązany z użytkownikiem
DB_EXECUTE(
    'INSERT INTO postepy (id_pytania, user_id, kolejnosc, czy_poprawne) VALUES (?, ?, ?, ?)',
    [
        $id_pytania,
        $user_id,
        json_encode($kolejnosc),
        $correct ? 1 : 0,
    ]
);

output([
    'correct'      => $correct,
    'points'       => $points,
    'wrong_blocks' => $wrong_blocks,
    'total'        => count($correct_order),
]);
?>