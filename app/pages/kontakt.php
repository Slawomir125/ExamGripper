<?php
pageStart("Kontakt");
?>

<div class="container py-5" style="max-width: 900px;">

    <div class="mb-5 text-center">
        <h1 class="fw-bold mb-3">Skontaktuj się z nami</h1>
        <p class="fs-5 text-muted">
            Masz pytanie? Napisz do nas — odpowiemy tak szybko, jak to możliwe.
        </p>
    </div>

    <div class="row g-4">

        <div class="col-md-5">
            <div class="p-4 border rounded-4 h-100">
                <h4 class="fw-bold mb-4">Dane kontaktowe</h4>

                <div class="mb-3">
                    <div class="fw-semibold">Email</div>
                    <div>ExamGripper@twojastara.pl</div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold">Telefon</div>
                    <div>+48 517 952 134</div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold">Godziny pracy</div>
                    <div>Pon – Pt: 9:00 – 17:00</div>
                </div>

                <div class="mt-4">
                    <div class="fw-semibold mb-2">Adres</div>
                    <div>
                        ul. Ujazdowska 67<br>
                        47-143 Zimna Wódka
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="p-4 border rounded-4">
                <h4 class="fw-bold mb-4">Napisz wiadomość</h4>

                <div id="form-success" class="alert alert-success d-none">
                    Wiadomość została wysłana.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Imię</label>
                    <input id="name" type="text" class="form-control form-control-lg">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input id="email" type="email" class="form-control form-control-lg">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Wiadomość</label>
                    <textarea id="message" class="form-control form-control-lg" rows="5"></textarea>
                </div>

                <button id="send-btn" class="btn btn-dark btn-lg w-100 fw-bold">
                    Wyślij wiadomość
                </button>
            </div>
        </div>

    </div>

</div>

<script>
function sendMessage()
{
    let name = getElement("name").value;
    let email = getElement("email").value;
    let message = getElement("message").value;

    if (!name || !email || !message)
    {
        alert("Uzupełnij wszystkie pola");
        return;
    }

    // tutaj możesz podpiąć backend (send / API)
    // na razie tylko symulacja

    getElement("form-success").classList.remove("d-none");

    getElement("name").value = "";
    getElement("email").value = "";
    getElement("message").value = "";
}

click("send-btn", () => {
    sendMessage();
});
</script>

<?php
pageEnd();
?>