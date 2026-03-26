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
            <div id="available-blocks" drop drop-mode="sort" drag-group="blocks" class="border p-3 mb-3" style="min-height: 200px;">
                <?php foreach ($bloki as $blok): ?>
                    <div drag drag-value="<?= $blok['id'] ?>" class="block-item">
                        <pre class="mb-0"><?= htmlspecialchars($blok['kod']) ?></pre>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-6">
            <h5>Twoje rozwiązanie</h5>
            <div id="solution-area" drop drop-mode="sort" drag-group="blocks" class="border p-3 mb-3" style="min-height: 200px;">
                <!-- Tutaj użytkownik upuszcza bloczki -->
            </div>
            <button id="check-btn" class="btn btn-primary">Sprawdź rozwiązanie</button>
            <button id="reset-btn" class="btn btn-secondary ms-2">Reset</button>
            <div id="result" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const draggables = document.querySelectorAll('.draggable');
    const droppable = document.getElementById('solution-area');
    const available = document.getElementById('available-blocks');
    const checkBtn = document.getElementById('check-btn');
    const resultDiv = document.getElementById('result');

    draggables.forEach(item => {
        item.addEventListener('dragstart', handleDragStart);
    });

    droppable.addEventListener('dragover', handleDragOver);
    droppable.addEventListener('drop', handleDrop);
    available.addEventListener('dragover', handleDragOver);
    available.addEventListener('drop', handleDrop);

    function handleDragStart(e) {
        e.dataTransfer.setData('text/plain', e.target.dataset.id);
    }

    function handleDragOver(e) {
        e.preventDefault();
    }

    function handleDrop(e) {
        e.preventDefault();
        const id = e.dataTransfer.getData('text/plain');
        const draggedElement = document.querySelector(`[data-id="${id}"]`);
        if (draggedElement && e.target.classList.contains('droppable') || e.target.id === 'available-blocks') {
            e.target.appendChild(draggedElement);
        }
    }

    checkBtn.addEventListener('click', function() {
        const solutionBlocks = Array.from(droppable.querySelectorAll('.block-item')).map(el => el.getAttribute('drag-value'));
        fetch('?page=api/check-solution', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pytania: <?= $id_pytania ?>, kolejnosc: solutionBlocks })
        })
        .then(response => response.json())
        .then(data => {
            // Reset colors
            droppable.querySelectorAll('.block-item').forEach(el => el.classList.remove('bg-danger', 'text-white'));
            // Highlight wrong blocks
            data.wrong_blocks.forEach(id => {
                const el = droppable.querySelector(`[drag-value="${id}"]`);
                if (el) el.classList.add('bg-danger', 'text-white');
            });
            resultDiv.innerHTML = `<div class="alert alert-info">Punkty: ${data.points}/${solutionBlocks.length}</div>`;
            if (data.correct) {
                resultDiv.innerHTML += '<div class="alert alert-success">Poprawne rozwiązanie!</div>';
            }
        });
    });

    document.getElementById('reset-btn').addEventListener('click', function() {
        const blocks = Array.from(droppable.querySelectorAll('.block-item'));
        blocks.forEach(block => {
            available.appendChild(block);
            block.classList.remove('bg-danger', 'text-white');
        });
        resultDiv.innerHTML = '';
    });
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

<script src="<?= fwUrl('public/assets/drag.js') ?>"></script>

<?php
pageEnd();
?>