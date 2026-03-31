<?php
session_start();

pageStart("Logowanie");

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    $email = $_POST["email"] ?? "";
    $haslo = $_POST["haslo"] ?? "";

    if ($email && $haslo)
    {
        $user = DB_GET("SELECT * FROM uzytkownicy WHERE email = ?", [$email]);

        if ($user && password_verify($haslo, $user["haslo_hash"]))
        {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["nazwa_wyswietlana"];

            header("Location: " . route(""));
            exit;
        }
        else
        {
            $error = "Nieprawidłowy email lub hasło";
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
        <h1 class="fw-bold mb-3">Zaloguj się</h1>
        <p class="fs-5 text-muted">Uzyskaj dostęp do swojego konta</p>
    </div>

    <?php if (!empty($error)) { ?>
        <div class="alert alert-danger fw-semibold">
            <?= $error ?>
        </div>
    <?php } ?>

    <form method="post" class="p-4 border rounded-4">

        <div class="mb-4">
            <label class="form-label fw-semibold">Email</label>
            <input name="email" type="email" class="form-control form-control-lg">
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Hasło</label>
            <input name="haslo" type="password" class="form-control form-control-lg">
        </div>

        <button class="btn btn-dark btn-lg w-100 fw-bold mb-3">
            Zaloguj się
        </button>

        <div class="text-center">
            <a href="<?= route("rejestracja") ?>" class="fw-semibold">
                Nie masz konta? Zarejestruj się
            </a>
        </div>

    </form>

</div>

<?php
pageEnd();
?>