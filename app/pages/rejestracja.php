<?php
session_start();

pageStart("Rejestracja");
if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    $email = $_POST["email"] ?? "";
    $haslo = $_POST["haslo"] ?? "";
    $nazwa = $_POST["nazwa"] ?? "";

    if ($email && $haslo && $nazwa)
    {
        $exists = DB_GET("SELECT id FROM uzytkownicy WHERE email = ?", [$email]);

        if ($exists)
        {
            $error = "Ten email jest już zajęty";
        }
        else
        {
            $hash = password_hash($haslo, PASSWORD_DEFAULT);

            DB_EXECUTE("
                INSERT INTO uzytkownicy (email, haslo_hash, nazwa_wyswietlana)
                VALUES (?, ?, ?)
            ", [$email, $hash, $nazwa]);

            $user_id = DB_LAST_ID();

            header("Location: " . "logowanie");
            exit;
        }
    }
    else
    {
        $error = "Uzupełnij wszystkie pola";
    }
}
?>

<div class="container py-5" style="max-width: 500px;">

    <div class="text-center mb-5">
        <h1 class="fw-bold mb-3">Załóż konto</h1>
        <p class="fs-5 text-muted">Dołącz i zacznij korzystać</p>
    </div>

    <?php if (!empty($error)) { ?>
        <div class="alert alert-danger fw-semibold">
            <?= $error ?>
        </div>
    <?php } ?>

    <form method="post" class="p-4 border rounded-4">

        <div class="mb-4">
            <label class="form-label fw-semibold">Nazwa wyświetlana</label>
            <input name="nazwa" type="text" class="form-control form-control-lg">
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Email</label>
            <input name="email" type="email" class="form-control form-control-lg">
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Hasło</label>
            <input name="haslo" type="password" class="form-control form-control-lg">
        </div>

        <button class="btn btn-dark btn-lg w-100 fw-bold mb-3">
            Zarejestruj się
        </button>

        <div class="text-center">
            <a href="<?= route("logowanie") ?>" class="fw-semibold">
                Masz już konto? Zaloguj się
            </a>
        </div>

    </form>

</div>

<?php
pageEnd();
?>