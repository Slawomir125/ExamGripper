<?php
pageStart('Quiz INF 04');
?>
<section class="py-5 my-5 border-top" animation="fade-in" animation-trigger="view" animation-time="0.7s" animation-margin="140px">
    <div class="row w-100 mb-4">
        <div class="col-12 text-center">
            <h2 class="h3 fw-bold">Trening Teorii INF.04</h2>
            <p class="text-muted">Sprawdź swoją wiedzę przed prawdziwym egzaminem</p>
        </div>
    </div>
    
    <div id="quiz-wrapper">
        <div class="row justify-content-center g-4 m-0" id="quiz-container" defaultBinding="quizPytania">
            <div template class="col-12 col-lg-6 mb-3 quiz-slide" style="display: none; animation: fade-in 0.4s ease-out;">
                <div class="card border-0 text-white shadow-lg rounded-4 h-100 mx-auto" style="max-width: 600px; background-color: #0f172a;">
                    <div class="card-body p-4 p-md-5">
                        <div class="small text-uppercase fw-semibold mb-3 opacity-75" style="color: #38bdf8;">
                            Szybki Quiz
                        </div>
                        <h5 class="fw-bold mb-4 fs-5" style="line-height: 1.5;">{{pytanie}}</h5>
                        
                        <div class="d-grid gap-3 mt-4">
                            <button class="btn btn-outline-light text-start p-3 odp-btn" data-sprawdz="true" data-poprawna="{{poprawna}}" data-wybrana="a" style="border-radius: 12px; border-width: 2px; transition: all 0.2s;">
                                <span class="fw-bold me-2 marker" style="color: #38bdf8;">A.</span> <span class="odp-text">{{odp_a}}</span>
                            </button>
                            <button class="btn btn-outline-light text-start p-3 odp-btn" data-sprawdz="true" data-poprawna="{{poprawna}}" data-wybrana="b" style="border-radius: 12px; border-width: 2px; transition: all 0.2s;">
                                <span class="fw-bold me-2 marker" style="color: #38bdf8;">B.</span> <span class="odp-text">{{odp_b}}</span>
                            </button>
                            <button class="btn btn-outline-light text-start p-3 odp-btn" data-sprawdz="true" data-poprawna="{{poprawna}}" data-wybrana="c" style="border-radius: 12px; border-width: 2px; transition: all 0.2s;">
                                <span class="fw-bold me-2 marker" style="color: #38bdf8;">C.</span> <span class="odp-text">{{odp_c}}</span>
                            </button>
                            <button class="btn btn-outline-light text-start p-3 odp-btn" data-sprawdz="true" data-poprawna="{{poprawna}}" data-wybrana="d" style="border-radius: 12px; border-width: 2px; transition: all 0.2s;">
                                <span class="fw-bold me-2 marker" style="color: #38bdf8;">D.</span> <span class="odp-text">{{odp_d}}</span>
                            </button>
                        </div>
                        <div class="feedback-icon mt-4 text-center" style="display:none; min-height: 38px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center g-4 m-0" id="quiz-end-screen" style="display: none; animation: fade-in 0.5s ease-out;">
            <div class="col-12 col-lg-6 text-center">
                <div class="card border-0 text-white shadow-lg p-5 mx-auto" style="max-width: 600px; background-color: #0f172a; border-radius: 16px;">
                    <div class="mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="#38bdf8" viewBox="0 0 16 16">
                            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                        </svg>
                    </div>
                    <h3 class="fw-bold mb-3" style="color: #38bdf8;">To wszystko!</h3>
                    <p class="text-white-50 mb-4">Odpowiedziałeś na wylosowane pytania.</p>
                    <div>
                        <button class="btn btn-primary px-4 py-2" style="border-radius: 8px;" onclick="location.reload()">
                            Losuj kolejne pytania
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<script>
onReady(() => {
    // Inicjalizuj logikę pokazywania tylko pierwszego slajdu
    let quizStarted = false;
    let initInterval = setInterval(() => {
        if (!quizStarted) {
            const slides = document.querySelectorAll('#quiz-container .quiz-slide:not([template])');
            if (slides.length > 0) {
                slides[0].style.display = 'block';
                quizStarted = true;
                clearInterval(initInterval);
            }
        }
    }, 100);

    clickData('sprawdz', (val, btn) => {
        const wybrana = btn.getAttribute('data-wybrana');
        const poprawna = btn.getAttribute('data-poprawna');
        const cardBody = btn.closest('.card-body');
        const btns = cardBody.querySelectorAll('.odp-btn');
        const feedback = cardBody.querySelector('.feedback-icon');
        const currentSlide = btn.closest('.quiz-slide');
        
        btns.forEach(b => {
            b.disabled = true;
            b.style.opacity = '1';
            let w = b.getAttribute('data-wybrana');
            
            b.classList.remove('btn-outline-light');
            
            if (w === poprawna) {
                b.classList.add('btn-success', 'border-success', 'text-white', 'shadow-sm');
            } else if (w === wybrana && wybrana !== poprawna) {
                b.classList.add('btn-danger', 'border-danger', 'text-white');
            } else {
                b.classList.add('btn-outline-secondary');
                b.style.opacity = '0.4';
            }
        });

        feedback.style.display = 'block';
        if (wybrana === poprawna) {
            feedback.innerHTML = '<span class="text-success fw-bold fs-4">✓ Świetnie!</span>';
        } else {
            feedback.innerHTML = '<span class="text-danger fw-bold fs-4">✗ Odpowiedź niepoprawna</span>';
        }

        setTimeout(() => {
            currentSlide.style.display = 'none';
            
            const slides = Array.from(document.querySelectorAll('#quiz-container .quiz-slide:not([template])'));
            const currentIndex = slides.indexOf(currentSlide);
            
            if (currentIndex !== -1 && currentIndex + 1 < slides.length) {
                slides[currentIndex + 1].style.display = 'block';
            } else {
                document.getElementById('quiz-end-screen').style.display = 'block';
            }
        }, 1600);
    });
});
</script>


<?php

$quizData = DB_QUERY('SELECT * FROM quiz_pytania ORDER BY RAND() LIMIT 40');
if (!is_array($quizData)) {
    $quizData = [];
}

pageEnd([
    'quizPytania' => $quizData
]);
?>