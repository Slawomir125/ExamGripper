<?php
pageStart('Zadania – INF.04 Builder');

$id_pytania = (int) ($_GET['id'] ?? 0);
if ($id_pytania <= 0) {
    // Jeśli brak id, pobierz losowe z kategorii jeśli podana
    $id_kategorii = (int) ($_GET['kategoria'] ?? 0);
    if ($id_kategorii > 0) {
        $pytanie = DB_GET('SELECT * FROM pytania WHERE id_kategorii = ? ORDER BY RAND() LIMIT 1', [$id_kategorii]);
    } else {
        $pytanie = DB_GET('SELECT * FROM pytania ORDER BY RAND() LIMIT 1');
    }
    if ($pytanie) {
        $id_pytania = $pytanie['id'];
    }
}

if ($id_pytania <= 0) {
    echo '<p>Brak dostępnych pytań.</p>';
    pageEnd();
    exit;
}

$pytanie = DB_GET('SELECT * FROM pytania WHERE id = ?', [$id_pytania]);
if (!$pytanie) {
    echo '<p>Pytanie nie znalezione.</p>';
    pageEnd();
    exit;
}

// Pobierz bloki
$bloki = DB_SELECT('bloki_kodu', ['id', 'kod', 'poprzedni_blok_id', 'czy_jest_wymagane'], ['id_pytania' => $id_pytania], 'ORDER BY id ASC');

// Przetasuj bloki
shuffle($bloki);

?>

<div class="container py-5">
    <div class="mb-4">
        <h1 class="h3 mb-3">Rozwiąż zadanie</h1>
        <p class="text-muted"><?= htmlspecialchars($pytanie['tresc']) ?></p>
    </div>

    <div class="row">
        <div class="col-md-6">
            <h5>Dostępne bloczki</h5>
            <div id="available-blocks" drop drop-mode="sort" drag-group="blocks" drop-ghost="true" class="border p-3 mb-3" style="min-height: 200px;">
                <?php foreach ($bloki as $blok): ?>
                    <div drag drag-value="<?= $blok['id'] ?>" class="block-item">
                        <pre class="mb-0"><?= htmlspecialchars($blok['kod']) ?></pre>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-6">
            <h5>Twoje rozwiązanie</h5>
            <div id="solution-area" drop drop-mode="sort" drag-group="blocks"  drop-ghost="true"  class="border p-3 mb-3" style="min-height: 200px;">
                <!-- Tutaj użytkownik upuszcza bloczki -->
            </div>
            <button id="check-btn" class="btn btn-primary">Sprawdź rozwiązanie</button>
            <button id="reset-btn" class="btn btn-secondary ms-2">Reset</button>
            <div id="result" class="mt-3"></div>
        </div>
    </div>
</div>

<script defer>
const availableBlocks = getElement("available-blocks");
const solutionArea = getElement("solution-area");

click("check-btn", async () => {
    const solutionBlocks = Array.from(solutionArea.querySelectorAll("[drag]")).map((el) => parseInt(el.getAttribute("drag-value"), 10));

    if (solutionBlocks.length === 0) {
        getElement("result").innerHTML = '<div class="alert alert-warning">Przeciągnij bloczki do obszaru rozwiązania!</div>';
        return;
    }

    try {
        const result = await send("<?= fwUrl('api/check-solution') ?>", {
            id_pytania: <?= (int) $id_pytania ?>,
            kolejnosc: solutionBlocks
        });

        solutionArea.querySelectorAll("[drag]").forEach((el) => {
            el.classList.remove("bg-danger", "bg-success", "text-white");
        });

        if (result.wrong_blocks && result.wrong_blocks.length > 0) {
            result.wrong_blocks.forEach((id) => {
                const el = solutionArea.querySelector('[drag-value="' + id + '"]');

                if (el) {
                    el.classList.add("bg-danger", "text-white");
                }
            });
        }

        if (result.correct) {
            solutionArea.querySelectorAll("[drag]").forEach((el) => {
                el.classList.add("bg-success", "text-white");
            });
        }

        getElement("result").innerHTML = '<div class="alert alert-info">Punkty: ' + (result.points ?? 0) + '/' + solutionBlocks.length + '</div>';

        if (result.correct) {
            getElement("result").innerHTML += '<div class="alert alert-success">✓ Poprawne rozwiązanie!</div>';
        } else {
            getElement("result").innerHTML += '<div class="alert alert-warning">Spróbuj jeszcze raz - czerwone bloki są w złej pozycji</div>';
        }
    } catch (error) {
        console.error(error);
        getElement("result").innerHTML = '<div class="alert alert-danger">Błąd połączenia z serwerem</div>';
    }
});

click("reset-btn", () => {

    solutionArea.querySelectorAll("[drag]").forEach((block) => {
        availableBlocks.appendChild(block);
        block.classList.remove("bg-danger", "bg-success", "text-white");
    });

    setText("result", "");
});
</script>

<style>
.block-item {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 10px;
    margin: 5px;
    cursor: move;
}
</style>


<?php
pageEnd();
?>