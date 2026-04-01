<?php
pageStart('INF.04 Builder');
?>

<section class="inf04-hero py-5 my-4 my-md-5 border-bottom" children-animation="fade-up" animation-trigger="default" animation-time="0.6s" children-animation-delay="0.12s">
    <div class="row align-items-center g-4">
        <div class="col-12 col-lg-7">
            <div class="inf04-section-label text-uppercase fw-semibold mb-2">
                Nauka do egzaminu praktycznego
            </div>

            <h1 class="inf04-hero-title display-5 fw-bold mb-3">
                Układaj bloczki i buduj poprawne rozwiązania zadań INF.04
            </h1>

            <p class="lead text-muted mb-4">
                Ćwicz logikę programowania, składnię i rozwiązywanie zadań praktycznych przez przeciąganie gotowych fragmentów kodu we właściwe miejsca.
            </p>

            <div class="d-grid gap-3 d-sm-flex justify-content-sm-start mt-4">
                <a href="<?= route('zadania/') ?>" class="btn btn-lg px-4 fw-semibold border-0 text-white inf04-btn-primary" animation="pop" animation-delay="0.15s">
                    Rozpocznij naukę
                </a>

                <a href="<?= route('kategorie/') ?>" class="btn btn-lg px-4 fw-semibold inf04-btn-outline" animation="pop" animation-delay="0.25s">
                    Zobacz kategorie
                </a>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="small text-uppercase text-muted fw-semibold mb-3 px-2">
                Jak to działa
            </div>

            <div class="vstack gap-3" children-animation="fade-up" animation-trigger="default" animation-time="0.55s" children-animation-delay="0.1s">
                <div class="card border-0 text-white shadow-sm inf04-dark-card inf04-step-card">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="fs-1 fw-bold inf04-step-number">1</div>
                        <div>
                            <div class="fw-semibold">Wybierz kategorię</div>
                            <div class="small text-white-50">Przejdź do działu zgodnego z zakresem egzaminu INF.04.</div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 text-white shadow-sm inf04-dark-card inf04-step-card">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="fs-1 fw-bold inf04-step-number">2</div>
                        <div>
                            <div class="fw-semibold">Ułóż rozwiązanie</div>
                            <div class="small text-white-50">Przeciągnij bloczki tak, aby stworzyć poprawny kod zgodny z poleceniem.</div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 text-white shadow-sm inf04-dark-card inf04-step-card">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="fs-1 fw-bold inf04-step-number">3</div>
                        <div>
                            <div class="fw-semibold">Sprawdź wynik</div>
                            <div class="small text-white-50">Zobacz, które elementy są poprawne i czego jeszcze brakuje.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 my-5 border-bottom" animation="fade-in" animation-trigger="view" animation-time="0.7s" animation-margin="140px">
    <div class="row g-4 align-items-stretch">
        <div class="col-12 col-lg-6">
            <div class="card h-100 border-0 text-white shadow-sm inf04-dark-card inf04-mode-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="small text-uppercase fw-semibold mb-2 opacity-75">
                        Tryb nauki
                    </div>

                    <h2 class="h3 mb-3">
                        Ucz się krok po kroku
                    </h2>

                    <p class="mb-4 text-white-50">
                        Pracuj spokojnie, sprawdzaj odpowiedzi, poprawiaj błędy i utrwalaj poprawną kolejność elementów kodu.
                    </p>

                    <div class="vstack gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="inf04-check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></span>
                            <span>Sprawdzanie odpowiedzi po każdym zadaniu</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="inf04-check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></span>
                            <span>Podpowiedzi i wyjaśnienia poprawnego rozwiązania</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="inf04-check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></span>
                            <span>Wygodna nauka przez układanie bloczków</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100 border-0 text-white shadow-sm inf04-dark-card inf04-mode-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="small text-uppercase fw-semibold mb-2 opacity-75">
                        Tryb egzaminacyjny
                    </div>

                    <h2 class="h3 mb-3">
                        Sprawdź się pod presją zadania
                    </h2>

                    <p class="mb-4 text-white-50">
                        Rozwiązuj zadania w bardziej wymagającej formie i buduj pewność przed prawdziwym egzaminem.
                    </p>

                    <div class="vstack gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="inf04-check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></span>
                            <span>Mniej wskazówek i większy nacisk na samodzielność</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="inf04-check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></span>
                            <span>Zadania wzorowane na części praktycznej egzaminu</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="inf04-check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></span>
                            <span>Lepsze przygotowanie do realnej pracy z poleceniem</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 my-5" animation="fade-in" animation-trigger="view" animation-time="0.7s" animation-margin="140px">
    <div class="card border-0 bg-white shadow-sm inf04-light-card">
        <div class="card-body p-4 p-md-5">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-8">
                    <div class="small text-uppercase text-muted fw-semibold mb-2">
                        Szybki start
                    </div>

                    <h2 class="h3 mb-2 text-dark">
                        Zacznij od pierwszych zadań i buduj regularny progres
                    </h2>

                    <p class="text-muted mb-0">
                        Wybierz kategorię, ułóż pierwsze rozwiązanie i sprawdź, które elementy wymagają jeszcze powtórki.
                    </p>
                </div>

                <div class="col-12 col-lg-4 text-lg-end d-grid d-lg-block">
                    <a href="<?= route('zadania/') ?>" class="btn btn-lg px-4 fw-semibold border-0 text-white inf04-btn-primary">
                        Przejdź do zadań
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>



<style>
    .odp-btn:hover:not(:disabled) {
        background-color: rgba(255,255,255,0.1);
        border-color: #38bdf8 !important;
    }
    .odp-btn.btn-success { background-color: #198754 !important; }
    .odp-btn.btn-danger { background-color: #dc3545 !important; }
    .odp-btn.btn-success .marker, .odp-btn.btn-danger .marker { color: white !important; }
</style>


<?php
pageEnd();
?>