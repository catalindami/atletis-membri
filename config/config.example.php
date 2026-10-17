<?php
/**
 * Copiază acest fișier ca config.php (în același folder) și completează valorile.
 * config.php NU se urcă pe GitHub (este în .gitignore).
 */
return [
    'app' => [
        'name'     => 'ACS Atletis – Membri',
        'url'      => 'https://membri.acsatletis.ro',
        'timezone' => 'Europe/Bucharest',
        'debug'    => false,   // true doar temporar, pentru depanare
        // Cod secret folosit o singură dată, pentru crearea primului cont de admin
        // din browser: https://membri.acsatletis.ro/?r=setup&token=ACEST_COD
        // Pune un șir lung și aleatoriu. După crearea adminului, pagina se dezactivează singură.
        'setup_token' => 'SCHIMBA-MA-cu-un-sir-lung-aleatoriu',
    ],

    // Datele bazei de date create din cPanel → MySQL® Databases
    'db' => [
        'host'    => 'localhost',
        'name'    => 'cpaneluser_membri',
        'user'    => 'cpaneluser_membri',
        'pass'    => 'parola-bazei-de-date',
        'charset' => 'utf8mb4',
    ],

    // Contul de email creat din cPanel → Email Accounts → Connect Devices
    'mail' => [
        'transport'  => 'smtp',          // 'smtp' pe server; 'log' = scrie emailurile în storage/logs/mail.log (test)
        'host'       => 'mail.acsatletis.ro',
        'port'       => 465,
        'encryption' => 'ssl',           // 'ssl' pentru 465, 'tls' pentru 587
        'username'   => 'secretariat@acsatletis.ro',
        'password'   => 'parola-contului-de-email',
        'from_email' => 'secretariat@acsatletis.ro',
        'from_name'  => 'ACS Atletis',
        'reply_to'   => 'secretariat@acsatletis.ro',
    ],

    'notifications' => [
        'days_before'          => 7,    // membrii primesc emailul cu 7 zile înainte de expirare
        'admin_email'          => 'adresa-ta@exemplu.ro',
        'weekly_summary_day'   => 1,    // ziua rezumatului: 1 = luni ... 7 = duminică
        'summary_horizon_days' => 14,   // rezumatul arată expirările din următoarele 14 zile
        'medical_horizon_days' => 30,   // vize medicale care expiră în următoarele 30 de zile
    ],
];
