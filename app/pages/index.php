<?php
pageStart('INF.04 Builder');
?>

<section class="py-5 border-bottom" children-animation="fade-up" animation-trigger="default" animation-time="0.6s" children-animation-delay="0.12s">
    <div class="row align-items-center g-4">
        <div class="col-12 col-lg-7">
            <div class="small text-uppercase text-primary fw-semibold mb-2">
                Nauka do egzaminu praktycznego
            </div>

            <h1 class="display-5 fw-bold mb-3">
                Układaj bloczki i buduj poprawne rozwiązania zadań INF.04
            </h1>

            <p class="lead text-muted mb-4">
                Ćwicz logikę programowania, składnię i rozwiązywanie zadań praktycznych przez przeciąganie gotowych fragmentów kodu we właściwe miejsca.
            </p>

            <div class="d-flex flex-wrap gap-3">
                <a href="<?= route('zadania/') ?>" class="btn btn-dark btn-lg" animation="pop" animation-delay="0.15s">
                    Rozpocznij naukę
                </a>

                <a href="<?= route('kategorie/') ?>" class="btn btn-outline-secondary btn-lg" animation="pop" animation-delay="0.25s">
                    Zobacz kategorie
                </a>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="small text-uppercase text-muted fw-semibold mb-3">
                        Jak to działa
                    </div>

                    <div class="vstack gap-3" children-animation="fade-up" animation-trigger="default" animation-time="0.55s" children-animation-delay="0.1s">
                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-4">1</div>
                            <div>
                                <div class="fw-semibold">Wybierz kategorię</div>
                                <div class="text-muted small">Przejdź do działu zgodnego z zakresem egzaminu INF.04.</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-4">2</div>
                            <div>
                                <div class="fw-semibold">Ułóż rozwiązanie</div>
                                <div class="text-muted small">Przeciągnij bloczki tak, aby stworzyć poprawny kod zgodny z poleceniem.</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-4">3</div>
                            <div>
                                <div class="fw-semibold">Sprawdź wynik</div>
                                <div class="text-muted small">Zobacz, które elementy są poprawne i czego jeszcze brakuje.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 border-bottom" animation="fade-in" animation-trigger="view" animation-time="0.7s" animation-margin="140px">
    <div class="row g-4 align-items-stretch">
        <div class="col-12 col-lg-6">
            <div class="card h-100 border-0 bg-dark text-white rounded-4 shadow-sm">
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

                    <div class="vstack gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <span>•</span>
                            <span>Sprawdzanie odpowiedzi po każdym zadaniu</span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span>•</span>
                            <span>Podpowiedzi i wyjaśnienia poprawnego rozwiązania</span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span>•</span>
                            <span>Wygodna nauka przez układanie bloczków</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100 border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="small text-uppercase text-muted fw-semibold mb-2">
                        Tryb egzaminacyjny
                    </div>

                    <h2 class="h3 mb-3">
                        Sprawdź się pod presją zadania
                    </h2>

                    <p class="text-muted mb-4">
                        Rozwiązuj zadania w bardziej wymagającej formie i buduj pewność przed prawdziwym egzaminem.
                    </p>

                    <div class="vstack gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <span>•</span>
                            <span>Mniej wskazówek i większy nacisk na samodzielność</span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span>•</span>
                            <span>Zadania wzorowane na części praktycznej egzaminu</span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span>•</span>
                            <span>Lepsze przygotowanie do realnej pracy z poleceniem</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" animation="fade-in" animation-trigger="view" animation-time="0.7s" animation-margin="140px">
    <div class="row g-4 align-items-center">
        <div class="col-12 col-lg-8">
            <div class="small text-uppercase text-muted fw-semibold mb-2">
                Szybki start
            </div>

            <h2 class="h3 mb-2">
                Zacznij od pierwszych zadań i buduj regularny progres
            </h2>

            <p class="text-muted mb-0">
                Wybierz kategorię, ułóż pierwsze rozwiązanie i sprawdź, które elementy wymagają jeszcze powtórki.
            </p>
        </div>

        <div class="col-12 col-lg-4 text-lg-end">
            <a href="<?= route('zadania/') ?>" class="btn btn-primary btn-lg">
                Przejdź do zadań
            </a>
        </div>
    </div>
</section>

<?php
pageEnd();
?>