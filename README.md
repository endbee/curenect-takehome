# Todo App (Symfony)

A simple Todo list built with Symfony. It supports user accounts, per-user ownership, due dates and email reminders scheduled via Messenger.

---

## Features

- Todos with **task**, **done**, and optional **due date**
- **User auth** (login/register) + ownership
- **Authorization** via `TodoVoter` (VIEW/EDIT/DELETE)
- **Symfony Forms** with validation
- **Reminders**: Doctrine EventSubscriber schedules emails via **Messenger**
- **SQLite** by default (no server setup), Doctrine Migrations
- Bootstrap UI (CDN) + visual due date badges

---

## Prerequisites

- **PHP** ≥ 8.4 with `pdo_sqlite` enabled
- **Composer** ≥ 2
- **SQLite** CLI (optional; helpful for debugging Messenger queue)
- **Symfony CLI** (optional, but handy): https://symfony.com/download
- (Dev) **SMTP catcher** (choose one):
    - **Mailpit** single EXE (recommended on Windows)
    - **smtp4dev** (Windows GUI SMTP server)

---

## Getting Started

```bash
# 1) Install dependencies
composer install

# 2) Create / migrate database (SQLite file in var/data.db)
php bin/console doctrine:migrations:migrate
```

### Run the web server (choose one)

```bash
# Symfony CLI (auto-reloads, dev toolbar)
symfony server:start --no-tls --port=8000

# OR PHP's built-in server
php -S 127.0.0.1:8000 -t public
```

Open: http://localhost:8000

### Create a user

- Visit **/register**, create an account, then log in at **/login**.

---

## Email Reminders in Development (SMTP Catcher)

The app sends reminder emails via Symfony Mailer from a Messenger handler. In development, use a local SMTP catcher so no real emails are sent.

### Option A — Mailpit (Windows, no Docker)

1) Download `mailpit.exe` from the official releases and place it, e.g., at `C:\tools\mailpit\mailpit.exe`.
2) Start it in **PowerShell**:

```powershell
& "C:\tools\mailpit\mailpit.exe" --smtp 127.0.0.1:1025 --listen 127.0.0.1:8025
```

- SMTP listens on **127.0.0.1:1025**
- Web UI at **http://127.0.0.1:8025**

3) Tell Symfony to use it:

```dotenv
# .env.local
MAILER_DSN=smtp://127.0.0.1:1025
```

### Option B — smtp4dev (Windows GUI)

```powershell
choco install smtp4dev
# Launch smtp4dev, note the SMTP port (e.g., 2525), then set:
# .env.local
MAILER_DSN=smtp://127.0.0.1:2525
```

**Notes**

- Catchers expect **plain SMTP** (no TLS/auth). Don’t add `?encryption=` or credentials to the DSN.
- Allow the app through the Windows firewall when prompted.

---

## Background Worker (Messenger)

The app uses Doctrine transport (DB table `messenger_messages`). Start the worker in a **separate terminal**:

```bash
php bin/console messenger:consume async -vv
```

> After changing config (e.g., email or router base URL), **restart the worker**. It’s a long-running process and won’t pick up new config automatically.

---

## Absolute URLs in Emails (Port 8000)

Emails are generated in a background worker (no HTTP request), so configure the router’s base URL to include the port:

```yaml
# config/packages/framework.yaml (or config/packages/dev/framework.yaml)
framework:
  router:
    default_uri: '%env(resolve:APP_BASE_URL)%'
```

```dotenv
# .env.local
APP_BASE_URL=http://localhost:8000
```

Then, in code, generate absolute URLs:

```php
$link = $this->urls->generate('index', [], UrlGeneratorInterface::ABSOLUTE_URL);
```

---

## Usage

- Add todos on `/` (task + optional due date).
- Toggle and delete via buttons; actions are guarded by the voter (only the owner can edit/delete).
- **Reminders**: When you create or update a todo with a due date, the app schedules:
    - an “early” reminder (lead time configurable; default 2 minutes), and
    - a reminder **at** the due time.
- Emails arrive in your SMTP catcher’s inbox.

## Configuration Cheatsheet

- **Database URL** (defaults to SQLite):
  ```dotenv
  DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
  ```
- **Mailer** (dev catcher):
  ```dotenv
  MAILER_DSN="smtp://127.0.0.1:1025"
  ```
- **Reminder lead time** (seconds), e.g., 30 minutes:
  ```yaml
  # config/services.yaml
  parameters:
    app.todo_reminder_lead_seconds: 1800
  ```

---

## Common Commands

```bash
# Validate mapping vs DB
php bin/console doctrine:schema:validate

# Generate a blank migration
php bin/console doctrine:migrations:generate

# Generate a schema diff migration
php bin/console make:migration

# Apply migrations
php bin/console doctrine:migrations:migrate

# See Messenger routing
php bin/console debug:messenger

# Tail dev logs (Linux/macOS)
tail -f var/log/dev.log

# Tail dev logs (Windows PowerShell)
Get-Content .\var\log\dev.log -Wait
```

---

## Debugging the Queue (SQLite)

```powershell
# List queued messages (PowerShell)
sqlite3 .\var\data.db -header -column "SELECT id,queue_name,available_at,created_at FROM messenger_messages ORDER BY available_at;"
```

You should see two rows per scheduled reminder (one for the “early” reminder, one for the due-time reminder) with future `available_at` timestamps.

---

## Tech Stack

- Symfony (HTTP Kernel, Routing, Security, Forms, Validator, Twig)
- Doctrine ORM + Migrations
- Messenger (Doctrine transport)
- Mailer (SMTP)
- SQLite

---
