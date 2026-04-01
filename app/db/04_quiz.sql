CREATE TABLE IF NOT EXISTS quiz_pytania (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pytanie TEXT NOT NULL,
    odp_a VARCHAR(255) NOT NULL,
    odp_b VARCHAR(255) NOT NULL,
    odp_c VARCHAR(255) NOT NULL,
    odp_d VARCHAR(255) NOT NULL,
    poprawna CHAR(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

INSERT INTO quiz_pytania (pytanie, odp_a, odp_b, odp_c, odp_d, poprawna) VALUES
('Jaką funkcją w PHP połączysz się z bazą danych MySQL wykorzystując interfejs obiektowy (nie licząc PDO)?', 'mysqli_connect()', 'new PDO()', 'new mysqli()', 'db_connect()', 'c'),
('W jakim języku pisze się skrypty domyślnie wykonywane po stronie przeglądarki klienta?', 'PHP', 'Python', 'JavaScript', 'C++', 'c'),
('Który znacznik HTML służy do definiowania alternatywnego tekstu dla obrazka?', 'title', 'src', 'alt', 'href', 'c'),
('Jakie jest domyślne zachowanie typu input "submit" w formularzu HTML?', 'Czyści wszystkie pola formularza', 'Wysyła dane formularza', 'Otwiera nowe okno w przeglądarce', 'Zapisuje dane w formacie JSON', 'b'),
('Które zapytanie SQL służy do wybierania danych z bazy?', 'GET', 'SELECT', 'FETCH', 'EXTRACT', 'b');
