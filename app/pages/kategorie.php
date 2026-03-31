<?php
session_start();

$user_id   = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$logged_in = $user_id !== null;

pageStart('Kategorie – INF.04 Builder');

$kategorie_raw = DB_QUERY('SELECT id, nazwa FROM kategorie ORDER BY id ASC');

$kategorie = [];
foreach ($kategorie_raw as $k) {
    $kat_id = (int) $k['id'];

    $liczba_pytan = (int) (DB_GET(
        'SELECT COUNT(*) AS cnt FROM pytania WHERE id_kategorii = ?',
        [$kat_id]
    )['cnt'] ?? 0);

    $entry = [
        'id'           => $kat_id,
        'nazwa'        => $k['nazwa'],
        'liczba_pytan' => $liczba_pytan,
    ];

    if ($logged_in) {
        $correct = (int) (DB_GET(
            'SELECT COUNT(DISTINCT p.id_pytania) AS cnt
             FROM postepy p
             JOIN pytania q ON q.id = p.id_pytania
             WHERE p.user_id = ? AND q.id_kategorii = ? AND p.czy_poprawne = 1',
            [$user_id, $kat_id]
        )['cnt'] ?? 0);

        $entry['poprawne'] = $correct;
        $entry['percent']  = $liczba_pytan > 0 ? round($correct / $liczba_pytan * 100) : 0;
    }

    $kategorie[] = $entry;
}
?>

<div class="mb-5">
    <div class="small text-uppercase text-primary fw-semibold mb-2">
        Przeglądaj według działu
    </div>

    <h1 class="h2 fw-bold mb-2">
        Kategorie zadań
    </h1>

    <p class="text-muted mb-0">
        Wybierz dział, który chcesz przećwiczyć. Każda kategoria zawiera zestaw zadań z danego obszaru egzaminu INF.04.
    </p>
</div>

<div class="row g-4">
    <?php foreach ($kategorie as $k): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="<?= route('zadania/') ?>?kategoria=<?= $k['id'] ?>"
               class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none text-dark"
               style="transition: transform 0.15s, box-shadow 0.15s;"
               onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 .5rem 1.5rem rgba(0,0,0,.1)'"
               onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="fw-bold fs-5"><?= htmlspecialchars($k['nazwa']) ?></div>
                        <?php if ($logged_in): ?>
                            <span class="badge <?= $k['poprawne'] === $k['liczba_pytan'] && $k['liczba_pytan'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $k['poprawne'] ?>/<?= $k['liczba_pytan'] ?>
                            </span>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="text-muted" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        <?php endif; ?>
                    </div>

                    <?php if ($logged_in): ?>
                        <div class="progress mb-2" style="height: 6px;">
                            <div
                                class="progress-bar <?= $k['percent'] >= 100 ? 'bg-success' : 'bg-primary' ?>"
                                style="width: <?= $k['percent'] ?>%"
                            ></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span><?= $k['poprawne'] ?> rozwiązanych poprawnie</span>
                            <span><?= $k['percent'] ?>%</span>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small">
                            <?= $k['liczba_pytan'] ?> zadań do rozwiązania
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php
pageEnd();
?>