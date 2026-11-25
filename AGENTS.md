# AGENTS.md

## Project

JMACX LED Inventory is a Laravel 13 application with Blade-rendered pages, the existing vanilla CSS/JavaScript assets, and a MySQL/MariaDB inventory schema.

## Running and testing

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan serve
php artisan test
```

Configure database credentials in `.env`. Import `schema.sql` to create the application schema; the SQL scripts in `migrations/` and `seed.sql` are applied separately as needed.

## Architecture

- `routes/web.php` maps the login and existing `.html` compatibility URLs to `PageController`; Blade pages live in `resources/views/pages/`.
- `routes/api.php` sends `/api/*` through Laravel's `LegacyApiController`, preserving the existing JSON API contract. API resource handlers remain in `api/controllers/` and use Laravel's configured PDO connection.
- `app/Models/User.php` maps Laravel's user model to the existing `users` table and `password_hash` column.
- `public/assets/` contains the existing CSS and JavaScript. The web server document root must be `public/`.
- The existing MySQL schema and optional demo data are in `schema.sql` and `seed.sql`. **Never execute `database.sql` on a database that must be preserved; it drops tables.**

## References

See `README.md`, `requirements.md`, `usecase.md`, and `DATABASE_SCHEMA.md`.
