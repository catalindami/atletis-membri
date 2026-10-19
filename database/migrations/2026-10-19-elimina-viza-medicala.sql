-- Opțional: șterge coloana vizei medicale din baza de date existentă.
-- Aplicația funcționează și fără această modificare (coloana nu mai este folosită).
-- Rulează din cPanel → phpMyAdmin → baza de date → tab-ul SQL.
ALTER TABLE members DROP COLUMN medical_expires_on;
