<?php
session_start();
pageStart('Postępy – INF.04 Builder');

$sesja_id = session_id();

// Statystyki per kategoria
$kategorie = DB_QUERY('SELECT id, nazwa FROM kategorie ORDER BY id ASC');

$stats = [];
foreach ($kategorie as $kat) {
    $total = (int) (DB_GET('SELECT COUNT(*) AS cnt FROM pytania WHERE id_kategorii = ?', [$kat['id']])['cnt'] ?? 0);

    $attempted = (int) (DB_GET(
        'SELECT COUNT(DISTINCT p.id_pytania) AS cnt
         FROM postepy p
         JOIN pytania q ON q.id = p.id_pytania
         WHERE p.sesja_id = ? AND q.id_kategorii = ?',
        [$sesja_id, $kat['id']]
    )['cnt'] ?? 0);

    $correct = (int) (DB_GET(
        'SELECT COUNT(DISTINCT p.id_pytania) AS cnt
         FROM postepy p
         JOIN pytania q ON q.id = p.id_pytania
         WHERE p.sesja_id = ? AND q.id_kategorii = ? AND p.czy_poprawne = 1',
        [$sesja_id, $kat['id']]
    )['cnt'] ?? 0);

    $stats[] = [
        'id'        => $kat['id'],
        'nazwa'     => $kat['nazwa'],
        'total'     => $total,
        'attempted' => $attempted,
        'correct'   => $correct,
        'percent'   => $total > 0 ? round($correct / $total * 100) : 0,
    ];
}

// Ostatnie 10 prób
$historia = DB_QUERY(
    'SELECT p.id, p.czy_poprawne, p.data, q.tresc, k.nazwa AS kategoria
     FROM postepy p
     JOIN pytania q ON q.id = p.id_pytania
     JOIN kategorie k ON k.id = q.id_kategorii
     WHERE p.sesja_id = ?
     ORDER BY p.data DESC
     LIMIT 10',
    [$sesja_id]
);

?>

<div class="d-flex align-items-start justify-content-between mb-5 flex-wrap gap-3">
    <div>
        <div class="small text-uppercase text-primary fw-semibold mb-2">
            Twoja sesja
        </div>
        <h1 class="h2 fw-bold mb-2">
            Postępy nauki
        </h1>
        <p class="text-muted mb-0">
            Postępy są śledzone dla bieżącej sesji przeglądarki.
        </p>
    </div>

    <div class="pt-2">
        <button id="reset-btn" class="btn btn-outline-danger btn-sm">
            ↺ Resetuj postępy
        </button>
        <div id="reset-result" class="mt-2"></div>
    </div>
</div>

<div class="row g-4 mb-5">
    <?php foreach ($stats as $s): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="fw-bold fs-5"><?= htmlspecialchars($s['nazwa']) ?></div>
                        <span class="badge <?= $s['correct'] === $s['total'] && $s['total'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $s['correct'] ?>/<?= $s['total'] ?>
                        </span>
                    </div>

                    <div class="progress mb-2" style="height: 8px;">
                        <div
                            class="progress-bar <?= $s['percent'] >= 100 ? 'bg-success' : 'bg-primary' ?>"
                            style="width: <?= $s['percent'] ?>%"
                        ></div>
                    </div>

                    <div class="d-flex justify-content-between text-muted small">
                        <span><?= $s['correct'] ?> rozwiązanych poprawnie</span>
                        <span><?= $s['percent'] ?>%</span>
                    </div>

                    <?php if ($s['attempted'] > 0 && $s['correct'] < $s['total']): ?>
                        <div class="text-muted small mt-1">
                            <?= $s['attempted'] ?> z <?= $s['total'] ?> zadań podjętych
                        </div>
                    <?php endif; ?>

                    <a href="<?= route('zadania/') ?>?kategoria=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary mt-3">
                        <?= $s['attempted'] === 0 ? 'Zacznij ćwiczyć' : 'Ćwicz dalej' ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($historia)): ?>
    <div class="mb-3">
        <h2 class="h5 fw-bold">Ostatnie próby</h2>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Zadanie</th>
                    <th>Kategoria</th>
                    <th>Wynik</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historia as $wpis): ?>
                    <tr>
                        <td class="text-muted small" style="max-width: 320px;">
                            <?= htmlspecialchars(mb_strimwidth($wpis['tresc'], 0, 80, '…')) ?>
                        </td>
                        <td><?= htmlspecialchars($wpis['kategoria']) ?></td>
                        <td>
                            <?php if ($wpis['czy_poprawne']): ?>
                                <span class="badge bg-success">✓ Poprawnie</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">✗ Błędnie</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($wpis['data']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-light border">
        Nie rozwiązałeś jeszcze żadnego zadania w tej sesji. <a href="<?= route('zadania/') ?>">Zacznij teraz →</a>
    </div>
<?php endif; ?>

<script defer>
click("reset-btn", async () => {
    if (!confirm("Czy na pewno chcesz zresetować wszystkie postępy tej sesji?")) {
        return;
    }

    try {
        await send("<?= fwUrl('api/reset-postepy') ?>", {});
        window.location.reload();
    } catch (error) {
        getElement("reset-result").innerHTML = '<div class="alert alert-danger py-1 px-2 small">Błąd podczas resetowania</div>';
    }
});
</script>

<?php
pageEnd();
?>