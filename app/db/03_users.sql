CREATE TABLE IF NOT EXISTS uzytkownicy (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    email             VARCHAR(255) NOT NULL,
    haslo_hash        VARCHAR(255) NOT NULL,
    nazwa_wyswietlana VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_uzytkownicy_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;