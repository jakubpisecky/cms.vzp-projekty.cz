# cms.vzp-projekty.cz

Krátký popis: co aplikace dělá (CMS pro …), kdo ji používá a v jakém prostředí běží.

## Požadavky
- PHP: 8.1.34
- MySQL: 8.1.34
- Rozšíření PHP: pdo_mysql, intl, mbstring, gd (upřesni)

## Lokální spuštění
1. Zkopíruj `.env.example` → `.env` a doplň hodnoty
2. `php -S localhost:8000 -t public` (nebo Docker)
3. Přístup do adminu: `/admin` (pokud jinak, upřesni)

## Nasazení
- Hosting / server: (upřesni)
- Způsob deploye: Git push / SFTP / CI (upřesni)

## Databáze
- Migrations: (zatím ručně / nástroj …)
- Export schématu: viz `db/schema.sql` (doplníme)

## Bezpečnost
- CSRF ochrana: (ano/ne)
- XSS sanitace: (ano/ne)
- Hesla: password_hash (upřesni)
