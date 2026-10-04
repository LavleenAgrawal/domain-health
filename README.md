# DomainHealth

Check a domain's DNS and email setup, scan supported blocklists, or identify its mail provider.

## What you need

- PHP 8.3+
- Composer
- Node.js 20+
- MySQL or MariaDB

## Tech and versions

- PHP 8.3.33 with Laravel 13.34.0
- Next.js 14.2.24 with React 18.3.1
- Tailwind CSS 3.4.16
- Node.js 24.21.0 and npm 11.19.0
- Composer 2.10.3
- PHPUnit 11.5.56

## Set up once

1. Create `api/.env` from `api/.env.example` and add your database details.
2. Create `web/.env.local` from `web/.env.example`.
3. Run:

```bash
cd api
composer install
php artisan key:generate
php artisan migrate
```

## Start the app

Open three terminals:

```bash
# Terminal 1: API
cd api
php artisan serve --port=8001

# Terminal 2: background checks
cd api
php artisan queue:work database --queue=domain-checks

# Terminal 3: website
cd web
npm install
npm run dev -- -p 3001
```

Open [http://localhost:3001](http://localhost:3001).

## View
<img width="1908" height="902" alt="Screenshot (5)" src="https://github.com/user-attachments/assets/a269de2a-16b3-4d07-86fe-e55e200142c1" />
<img width="1913" height="895" alt="Screenshot (6)" src="https://github.com/user-attachments/assets/6bf5ffd3-acba-4e4c-b06e-24c358f6eb71" />


Sample CSV and TXT files are available in `samples/`.
