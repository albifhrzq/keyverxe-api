# Keyverxe API

Backend API untuk aplikasi e-commerce mechanical keyboard Keyverxe.

Project ini berjalan sebagai API-only service dengan autentikasi cookie-based (Laravel Sanctum) dan integrasi pembayaran Xendit.

## Repo Terkait

- Frontend repository: https://github.com/albifhrzq/keyverxe-be

## Tech Stack

- PHP 8.3+
- Laravel 13
- Laravel Sanctum (SPA cookie authentication)
- MySQL (default) via Eloquent ORM
- Xendit PHP SDK v7
- PHPUnit 12 untuk testing
- Laravel Pint untuk formatting

## Fitur Utama

- Auth register/login/logout + role (`admin`, `customer`)
- Produk, kategori, order, dan payment management
- Checkout flow dengan pembuatan invoice Xendit
- Webhook endpoint untuk update status pembayaran
- Route protection berbasis role middleware

## Prasyarat

- PHP 8.3+
- Composer 2+
- Node.js 20+ dan npm
- MySQL/MariaDB yang aktif

## Setup Lokal

1. Masuk ke folder backend:

```bash
cd keyverxe-api
```

2. Install dependency:

```bash
composer install
npm install
```

3. Siapkan file environment:

```bash
cp .env.example .env
```

Panduan penggunaan `.env.example`:

- Anggap `.env.example` sebagai template konfigurasi default untuk development.
- Semua perubahan konfigurasi dilakukan di file `.env`, bukan di `.env.example`.
- Jika ada variabel baru, tambahkan juga placeholder-nya ke `.env.example` agar setup tim tetap konsisten.

4. Generate app key:

```bash
php artisan key:generate
```

5. Atur konfigurasi di `.env` (minimal):

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=keyverxe
DB_USERNAME=root
DB_PASSWORD=

XENDIT_SECRET_KEY=xnd_development_xxxxx
XENDIT_WEBHOOK_TOKEN=your_callback_verification_token
XENDIT_SUCCESS_REDIRECT_URL=http://localhost:3000/orders
XENDIT_FAILURE_REDIRECT_URL=http://localhost:3000/checkout/failed
```

6. Jalankan migrasi, seeder, dan buat symlink storage:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
```

7. Jalankan backend:

```bash
php artisan serve
```

Backend akan tersedia di `http://localhost:8000`.

## Akun Admin Seeder (Development)

Setelah menjalankan seeder (`php artisan db:seed`), akun admin default adalah:

- Email: `admin@keyverxe.com`
- Password: `password`

Catatan keamanan: akun ini hanya untuk development lokal. Jangan gunakan di staging/production, dan segera reset password jika pernah dipakai di luar lokal.

## Menjalankan Full Dev Mode (Opsional)

Untuk menjalankan server + queue + logs + vite dalam satu command:

```bash
composer dev
```

## Testing dan Quality

```bash
composer test
./vendor/bin/pint
```

## Endpoint Penting

- CSRF cookie: `GET /sanctum/csrf-cookie`
- Auth: `POST /api/register`, `POST /api/login`, `POST /api/logout`
- Produk publik: `GET /api/products`, `GET /api/products/{slug}`
- Checkout: `POST /api/checkout` (auth customer)
- Webhook Xendit: `POST /api/webhook/xendit`

## Catatan Integrasi Frontend

- Pastikan frontend mengirim request dengan `withCredentials: true`.
- `FRONTEND_URL` dan `SANCTUM_STATEFUL_DOMAINS` harus sesuai domain frontend yang aktif.
