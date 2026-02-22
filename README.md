# Cornerfield

Cryptocurrency investment platform. Users sign up, deposit crypto, invest in plans with daily returns, withdraw profits. Admins manage everything from a separate panel.

## Stack

- PHP 8.1+ (MVC, PSR-4 autoloading)
- MariaDB / MySQL
- Tailwind CSS (pre-compiled)
- Tabler UI (admin panel)
- PHPMailer (transactional email)
- Composer

## Setup

```bash
composer install
cp .env.example .env
# edit .env with your database and app settings
mysql -u root < database/schema.sql
```

Point your web server document root at the project directory. Apache with mod_rewrite or nginx with try_files.

### Cron

Daily profit distribution needs a cron job:

```
0 0 * * * php /path/to/cornerfield/cron/daily-profits.php >> /path/to/cornerfield/logs/daily-profits.log 2>&1
```

## Structure

```
cornerfield/
├── src/
│   ├── Config/          # database, env, app config
│   ├── Controllers/     # 11 controllers (auth, dashboard, deposits, etc.)
│   ├── Models/          # 19 models
│   ├── Middleware/       # auth + csrf
│   ├── Services/        # email, payments, transfers, support
│   └── Utils/           # security, sessions, validation, csrf
├── admin/               # admin panel (20+ pages)
├── users/               # user dashboard, invest, deposit, withdraw, etc.
├── assets/
│   ├── css/             # tailwind compiled + custom
│   └── js/              # frontend logic
├── cron/                # automated tasks (daily profit calc)
├── database/            # schema
├── config/              # constants
└── login.php / register.php / logout.php
```

## Features

**Users:** register, login, deposit (BTC/ETH/USDT/BSC), invest in plans, earn daily returns, withdraw, transfer between users, support tickets, referral system.

**Admin:** user management with impersonation, deposit/withdrawal approval, investment plan CRUD, profit distribution (immediate or locked mode), email templates via SMTP, support ticket handling, platform settings, system health monitoring.

**Security:** bcrypt password hashing, session fingerprinting, rate limiting, prepared statements, CSRF protection, input sanitization, security event logging.

**Payments:** Cryptomus and NOWPayments gateway integrations. Manual deposit with proof upload also supported.

## Investment Plans

Configurable from admin panel. Default plans:

| Plan | Daily Rate | Duration | Min Deposit |
|------|-----------|----------|-------------|
| Bitcoin Starter | 2% | 30 days | $50 |
| Crypto Silver | 2.5% | 45 days | $1,000 |
| Digital Gold | 3% | 60 days | $5,000 |
| Cornerfield Elite | 3.5% | 90 days | $20,000 |

Profits can be set to distribute immediately to withdrawable balance or lock until investment maturity. Toggled from admin settings.

## Database

19 tables covering users, transactions, deposits, withdrawals, investments, investment plans, profits, referrals, support tickets, admin accounts, sessions, security logs, email logs, payment gateways, deposit/withdrawal methods, and platform settings.

Schema in `database/schema.sql`.

## License

Proprietary. All rights reserved.
