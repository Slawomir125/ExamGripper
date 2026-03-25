-- INF04 Quiz – struktura bazy danych
-- Wygenerowano automatycznie

CREATE TABLE IF NOT EXISTS kategorie (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    nazwa VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS pytania (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    id_kategorii  INT NOT NULL,
    tresc         TEXT NOT NULL,
    CONSTRAINT fk_pytania_kategoria FOREIGN KEY (id_kategorii) REFERENCES kategorie(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS bloki_kodu (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    id_pytania        INT NOT NULL,
    kod               TEXT NOT NULL,
    poprzedni_blok_id INT NULL,
    czy_jest_wymagane TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_bloki_pytanie   FOREIGN KEY (id_pytania)        REFERENCES pytania(id),
    CONSTRAINT fk_bloki_poprzedni FOREIGN KEY (poprzedni_blok_id) REFERENCES bloki_kodu(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
