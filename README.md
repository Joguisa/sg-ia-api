# SG-IA API (PHP 8 + MySQL)
API REST para serious game de alfabetización en cáncer de colon.

## Stack
PHP 8+, Apache, PDO-MySQL, Composer.

## Estructura
`src/{Controllers,Services,Repositories,Models,Middleware,Utils,Database}`, `public/`, `config/`, `db/`.

## Config
1) `cp .env.example .env` y edita credenciales.
2) Importa `db/schema/sg-ia-db.sql`.

## Run local
DocumentRoot -> `public/`. Reescritura activada. `composer install`.

## Seguridad
No subir `.env` ni credenciales. Variables vía entorno.

# DB
Importar `db/schema/001_sg-ia-db.sql` en MySQL 8+. Zona horaria UTC.
