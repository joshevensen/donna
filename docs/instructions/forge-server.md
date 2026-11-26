# Forge server

One DigitalOcean droplet, created and managed through Laravel Forge, runs everything: the app, Postgres, the queue worker and the scheduler.

## Before you start

- A Forge account with DigitalOcean connected as a server provider *(unverified; where this lives in Forge may differ)*.

## 1. Create the server

In Forge, create a server on DigitalOcean:

- **Type:** App server
- **Size:** Basic, 2 GB RAM / 1 vCPU ($12 a month)
- **PHP:** 8.4
- **Database:** Postgres, with a database named `donna`
- **Backups:** turn on DigitalOcean droplet backups (see also [backups.md](backups.md))

Note the server's IP address. [forge-site.md](forge-site.md) needs it for DNS.

## 2. Run the setup recipe

Forge doesn't give the droplet swap, and it doesn't install pgvector, the Postgres extension Donna's memory search needs. One recipe does both.

In Forge, create a new recipe *(unverified; recipes live under your account in Forge)*:

- **Name:** `Donna server setup`
- **User:** `root`
- **Script:** the block below

```bash
#!/usr/bin/env bash
set -euo pipefail

### CONFIG: tweak if needed
SWAP_SIZE_GB="${SWAP_SIZE_GB:-2}"   # 2 GB of swap for a 2 GB droplet
DB_NAME="${DB_NAME:-donna}"         # the database Forge created

if [ "$(id -u)" -ne 0 ]; then
  echo "Run this recipe as root." >&2
  exit 1
fi

echo "==> Swap"
if [ -n "$(swapon --show --noheadings)" ]; then
  echo "Swap is already active; leaving it alone."
else
  if [ ! -f /swapfile ]; then
    fallocate -l "${SWAP_SIZE_GB}G" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
  fi
  swapon /swapfile
  grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
swapon --show

echo "==> pgvector"
if [ "$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'")" != "1" ]; then
  echo "There is no database called ${DB_NAME}. Create it in Forge first, then run this again." >&2
  exit 1
fi
PG_VER="$(( $(sudo -u postgres psql -tAc 'SHOW server_version_num') / 10000 ))"
echo "Postgres ${PG_VER}"
export DEBIAN_FRONTEND=noninteractive
apt-get -o DPkg::Lock::Timeout=120 update -y
apt-get -o DPkg::Lock::Timeout=120 install -y "postgresql-${PG_VER}-pgvector"
sudo -u postgres psql -d "${DB_NAME}" -c 'CREATE EXTENSION IF NOT EXISTS vector;'
sudo -u postgres psql -d "${DB_NAME}" -c '\dx vector'

echo "==> Done. Safe to run again."
```

Then run the recipe on the new server. It can be run again at any time: it leaves existing swap alone and skips what is already installed.

What it does:

- **Swap.** If the droplet has none, it makes a 2 GB swap file and keeps it across reboots. The droplet has little memory, so this is the safety net.
- **pgvector.** It finds which Postgres version is running, installs the matching pgvector package, and turns the extension on in the `donna` database. The extension has to be created by the Postgres superuser, and the migrations assume it is there. If the `donna` database doesn't exist, it stops and says so.

## 3. Check it

The recipe's output ends with the swap file listed and a `vector` row from Postgres. To look again later, SSH in:

```bash
ssh forge@<server-ip>
```

```bash
swapon --show
```

```bash
sudo -u postgres psql -d donna -c '\dx vector'
```

Next: [forge-site.md](forge-site.md).

---

[← All setup instructions](README.md)
