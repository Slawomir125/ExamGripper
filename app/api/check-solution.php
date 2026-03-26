<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../framework/framework.php';

$data = input();
$id_pytania = (int) ($data['id_pytania'] ?? 0);
$kolejnosc = $data['kolejnosc'] ?? [];

if ($id_pytania <= 0 || !is_array($kolejnosc)) {
    error('invalid_input', 'Nieprawidłowe dane wejściowe');
}

// Pobierz poprawną kolejność
$bloki = DB_SELECT('bloki_kodu', ['id', 'poprzedni_blok_id'], ['id_pytania' => $id_pytania], 'ORDER BY id ASC');

// Buduj poprawną kolejność na podstawie poprzedni_blok_id
$correct_order = [];
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

while ($current !== null) {
    $correct_order[] = $current;
    // Znajdź następny blok, gdzie poprzedni_blok_id == current
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
$points = 0;
$wrong_blocks = [];
$user_order = $kolejnosc;

foreach ($user_order as $index => $block_id) {
    if (isset($correct_order[$index]) && $correct_order[$index] == $block_id) {
        $points++;
    } else {
        $wrong_blocks[] = $block_id;
    }
}

$correct = count($wrong_blocks) === 0;

// Zapisz postęp
$sesja_id = session_id();
DB::execRaw('INSERT INTO postepy (id_pytania, sesja_id, kolejnosc, czy_poprawne) VALUES (?, ?, ?, ?)', [
    $id_pytania,
    $sesja_id,
    json_encode($kolejnosc),
    $correct ? 1 : 0
]);

output(['correct' => $correct, 'points' => $points, 'wrong_blocks' => $wrong_blocks]);
?>