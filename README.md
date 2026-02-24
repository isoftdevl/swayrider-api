# SwayRider API - Premium Bike Delivery Platform

[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://mysql.com)

A high-concurrency RESTful API built with **Laravel 11**, powering an enterprise-level bike delivery ecosystem. This project focuses on scalability, real-time logistics, and secure financial transactions.

## 🚀 Key Features

- **Real-time Logistics**: Live rider tracking and order updates using **Pusher**.
- **Dynamic Pricing Engine**: Automated, distance-based pricing using **Google Maps Matrix API**.
- **Financial Ecosystem**: Integrated wallet system with **Paystack** for funding and secure payouts.
- **Enterprise Security**: Role-based access control (RBAC) via **Spatie Permissions** and **Laravel Sanctum**.
- **Rider KYC**: Comprehensive onboarding flow with document verification.

## 🛠️ Technical Stack

- **Framework**: Laravel 11 (PHP 8.2)
- **Real-time**: Pusher Channels
- **Payments**: Paystack API
- **Maps**: Google Maps Platform (Distance Matrix, Geocoding)
- **Utilities**: Spatie MediaLibrary, Maatwebsite Excel, Laravel Modules

## 🏗️ Architecture

The project follows a **Service-Oriented Architecture** within Laravel:
- **Services**: Business logic encapsulated in `app/Services` (Pricing, Wallet, Payment).
- **Modules**: Domain-driven design using `nwidart/laravel-modules`.
- **API First**: Optimized JSON responses with Eloquent Resources.

## ⚙️ Setup

1. **Clone & Install**:
   ```bash
   git clone <repo-url>
   composer install
   ```
2. **Environment**:
   - Configure `.env` with DB, Paystack, and Google Maps keys.
3. **Migrate & Seed**:
   ```bash
   php artisan migrate --seed
   ```

---
*Developed for a high-growth logistics startup, focusing on backend reliability and API performance.*
