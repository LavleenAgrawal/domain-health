# DomainHealth

Check a domain's DNS and email setup, scan supported blocklists, or identify its mail provider.

## What you need

- PHP 8.3+
- Composer
- Node.js 20+
- MySQL or MariaDB

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

Sample CSV and TXT files are available in `samples/`.
