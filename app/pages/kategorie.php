<?php
pageStart('Kategorie – INF.04 Builder');
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

<div class="row g-4" defaultBinding="kategorie">
    <div template class="col-12 col-sm-6 col-lg-4">
        <a href="<?= route('zadania/') ?>?kategoria={{id}}" class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none text-dark"
           style="transition: transform 0.15s, box-shadow 0.15s;"
           onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 .5rem 1.5rem rgba(0,0,0,.1)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold fs-5">{{nazwa}}</div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="text-muted" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </div>
                <div class="text-muted small">
                    {{liczba_pytan}} zadań do rozwiązania
                </div>
            </div>
        </a>
    </div>
</div>

<?php
$kategorie_raw = DB_SELECT('kategorie', ['id', 'nazwa'], [], 'ORDER BY id ASC');

$kategorie = array_map(function ($k) {
    $k['liczba_pytan'] = DB_GET(
        'SELECT COUNT(*) AS cnt FROM pytania WHERE id_kategorii = ?',
        [$k['id']]
    )['cnt'] ?? 0;
    return $k;
}, $kategorie_raw);

pageEnd([
    'kategorie' => $kategorie,
]);
?>
