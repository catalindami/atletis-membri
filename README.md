# ACS Atletis – Gestiune membri

Aplicație PHP + MySQL pentru evidența membrilor clubului: date personale, contribuția anuală, profil Strava, plus emailuri automate la expirarea contribuției și un rezumat săptămânal pentru administrator.

- PHP 8.1+ (testat pe 8.3; serverul rulează 8.4), MySQL 5.7+ / MariaDB 10.3+
- Fără Composer pe server: biblioteca de email (PHPMailer 6.10) este inclusă în `lib/`

## Structura

```
public/      ← SINGURUL folder accesibil din browser (document root-ul subdomeniului)
app/         ← logica aplicației și paginile
config/      ← config.php (NU e pe GitHub) + config.example.php
cron/        ← scriptul zilnic de notificări
cli/         ← creare/resetare admin din Terminal
database/    ← schema.sql pentru phpMyAdmin
lib/         ← PHPMailer
storage/logs ← jurnale (erori PHP, cron, emailuri de test)
```

---

## Instalare pe cPanel (o singură dată)

### 1. Baza de date
1. cPanel → **MySQL® Databases** → *Create New Database*: `membri` (cPanel o va numi `utilizator_membri`).
2. Tot acolo → *Add New User*: un utilizator nou cu o parolă puternică (folosește *Password Generator*). Notează parola.
3. *Add User To Database* → alege utilizatorul și baza → bifează **ALL PRIVILEGES**.
4. cPanel → **phpMyAdmin** → selectează baza din stânga → tab **Import** → alege `database/schema.sql` → *Import*.

### 2. Contul de email pentru notificări
1. cPanel → **Email Accounts** → *Create*: de ex. `secretariat@acsatletis.ro`.
2. La cont → **Connect Devices**: notează serverul de ieșire (SMTP), portul (de obicei **465**, SSL).

### 3. Codul, prin Git
1. cPanel → **Git™ Version Control** → *Create*.
2. *Clone URL*: adresa repository-ului de pe GitHub.
   Pentru un repository **privat**, vezi secțiunea „Repository privat” de mai jos.
3. *Repository Path*: `membri-app` (va fi `/home/UTILIZATOR/membri-app`).
4. *Repository Name*: `membri-app` → *Create*.

### 4. Subdomeniul
1. cPanel → **Domains** (sau *Subdomains*) → *Create A New Domain*: `membri.acsatletis.ro`.
2. **Document Root**: `membri-app/public` ← important: spre `public`, nu spre `membri-app`.
3. cPanel → **SSL/TLS Status** → rulează *AutoSSL* pentru subdomeniu, ca să meargă pe `https://`.

### 5. Configurarea
1. cPanel → **File Manager** → `membri-app/config/`.
2. Copiază `config.example.php` cu numele `config.php` și editează-l:
   - `db`: numele bazei, utilizatorul și parola de la pasul 1;
   - `mail`: datele contului de email de la pasul 2;
   - `notifications.admin_email`: adresa ta, unde vine rezumatul;
   - `app.setup_token`: un șir lung, aleatoriu (minim 20 de caractere).
3. Permisiuni recomandate pentru `config.php`: **600** sau **640**.

### 6. Contul de admin
Deschide în browser:
`https://membri.acsatletis.ro/?r=setup&token=CODUL_DIN_setup_token`
Creează contul (parolă minim 12 caractere, cu litere și cifre). După asta pagina de setup nu mai funcționează.

Alternativ, din cPanel → **Terminal**:
`cd ~/membri-app && php cli/create-admin.php adresa@exemplu.ro`
(Aceeași comandă resetează parola dacă o uiți.)

### 7. Cron job-ul pentru emailuri
cPanel → **Cron Jobs** → *Add New Cron Job*:
- Common Settings: *Once Per Day* (sau minute `0`, oră `8` pentru 08:00)
- Command:
  ```
  /usr/local/bin/php /home/UTILIZATOR/membri-app/cron/notifications.php >> /home/UTILIZATOR/membri-app/storage/logs/cron.log 2>&1
  ```
Înlocuiește `UTILIZATOR` cu utilizatorul tău de cPanel. Dacă `/usr/local/bin/php` nu există, încearcă `/usr/local/bin/ea-php84` sau întreabă hostingul care e calea PHP pentru CLI.

**Test imediat** (din Terminal): trimite rezumatul acum, ca să verifici că emailul ajunge:
```
php ~/membri-app/cron/notifications.php --summary-only --force-summary
```

---

## Actualizări
Când apare o versiune nouă pe GitHub: cPanel → **Git™ Version Control** → *Manage* → tab **Pull or Deploy** → **Update from Remote**. Atât. `config.php` și jurnalele nu sunt atinse.

Dacă o actualizare aduce modificări la baza de date, vor fi într-un fișier separat în `database/` și se importă la fel, din phpMyAdmin.

## Repository privat
cPanel are nevoie de o cheie SSH ca să citească un repository privat:
1. cPanel → **SSH Access** → *Manage SSH Keys* → *Generate a New Key* (fără parolă), apoi *Manage* → *Authorize*.
2. *View/Download* cheia **publică** și adaug-o în GitHub → repository → **Settings → Deploy keys** → *Add deploy key* (doar citire).
3. La pasul 3 folosește adresa SSH: `git@github.com:CONT/REPOSITORY.git`.

---

## Cum funcționează notificările
- Zilnic, fiecare membru **activ, cu email**, a cărui contribuție expiră în următoarele **7 zile** primește un singur email pentru acea dată de expirare. Dacă cron-ul nu rulează într-o zi, emailul pleacă în ziua următoare.
- Când prelungești contribuția, următorul email va fi trimis pentru noua dată.
- Membrii fără email apar în rezumat, ca să-i contactezi telefonic.
- **Luni** (configurabil), adminul primește rezumatul: expirări în următoarele 14 zile și contribuții expirate.
- Fiecare email trimis sau eșuat este înregistrat în tabela `notification_log`; ultimele apar pe pagina fiecărui membru.

## Securitate și GDPR
- Doar `public/` este accesibil din web (subdomeniul arată direct spre el); folderele `app`, `config`, `cron` etc. au în plus `.htaccess` cu `Require all denied`.
- Parole stocate cu `password_hash` (bcrypt), protecție CSRF pe toate formularele, blocare după 5 încercări greșite în 15 minute, deconectare după 2 ore de inactivitate, HTTPS forțat.
- Paginile au `noindex`, deci nu apar în Google.
- Exportul CSV conține date personale: păstrează-l în siguranță și șterge-l când nu mai ai nevoie de el.
- Ștergerea unui membru este definitivă (dreptul la ștergere din GDPR). Pentru membrii retrași, debifează „Membru activ”.
- Fă backup periodic al bazei de date (cPanel → **Backup** → *Download a MySQL Database Backup*).

## Depanare
- Pagină albă sau eroare 500: vezi `storage/logs/php-error.log`. Temporar poți pune `'debug' => true` în `config.php`, dar revino la `false` după.
- Emailurile nu pleacă: vezi `storage/logs/cron.log` și coloana `error` din tabela `notification_log`. Pentru test fără trimitere reală, pune `'transport' => 'log'`, iar emailurile vor fi scrise în `storage/logs/mail.log`.
- Emailurile ajung în spam: verifică în cPanel → **Email Deliverability** că SPF și DKIM sunt valide pentru `acsatletis.ro`.

## Pregătit pentru viitor
Tabela `users` are deja rolurile `admin` / `member` și legătura `member_id` către membri, astfel încât logarea membrilor cu email și parolă se poate adăuga fără să refacem baza de date.
