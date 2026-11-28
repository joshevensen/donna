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

The droplet needs a 2 GB swap file as a safety net, Postgres needs pgvector (the extension Donna's memory search uses), and reading attached PDFs needs `pdftotext`. One recipe sets up all three.

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
SWAP_BYTES=$(( SWAP_SIZE_GB * 1024 * 1024 * 1024 ))
if [ -n "$(swapon --show --noheadings)" ] && ! swapon --show=NAME --noheadings | grep -qx /swapfile; then
  echo "The droplet has swap that isn't /swapfile; leaving it alone."
else
  if [ -f /swapfile ] && [ "$(stat -c %s /swapfile)" -ne "$SWAP_BYTES" ]; then
    echo "Resizing /swapfile to ${SWAP_SIZE_GB}G"
    if swapon --show=NAME --noheadings | grep -qx /swapfile; then
      swapoff /swapfile
    fi
    rm -f /swapfile
  fi
  if [ ! -f /swapfile ]; then
    fallocate -l "${SWAP_SIZE_GB}G" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
  fi
  swapon --show=NAME --noheadings | grep -qx /swapfile || swapon /swapfile
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

echo "==> pdftotext (reads the text out of attached PDFs)"
apt-get -o DPkg::Lock::Timeout=120 install -y poppler-utils
pdftotext -v 2>&1 | head -n 1

echo "==> Done. Safe to run again."
```

Then run the recipe on the new server. It can be run again at any time: it changes nothing that is already right.

What it does:

- **Swap.** It makes sure `/swapfile` is 2 GB and kept across reboots. If there isn't one it makes it; if one already exists at another size it is replaced at the right size. Swap that isn't `/swapfile` is left alone. The droplet has little memory, so this is the safety net.
- **pgvector.** It finds which Postgres version is running, installs the matching pgvector package, and turns the extension on in the `donna` database. The extension has to be created by the Postgres superuser, and the migrations assume it is there. If the `donna` database doesn't exist, it stops and says so.
- **pdftotext.** It installs `poppler-utils`. Donna uses its `pdftotext` to read the text of a PDF you attach, because it keeps the columns of a table apart, which matters for statements and exports. Without it she falls back to a reader written in PHP that finds the same words but runs a table's cells together (`Nov 1po_1Qx0 $100.00In transit`). Nothing breaks; her reading of tables just gets worse. A server set up before Files existed needs the recipe run once more to get it.

## 3. Check it

The recipe's output shows the swap file, a `vector` row from Postgres and a `pdftotext version` line. To look again later, SSH in:

```bash
ssh forge@<server-ip>
```

```bash
swapon --show
```

```bash
sudo -u postgres psql -d donna -c '\dx vector'
```

```bash
pdftotext -v
```

One more thing Files use, which Forge should already have installed with PHP *(unverified: Forge's PHP comes with the GD and EXIF extensions)*:

```bash
php8.4 -m | grep -i -E '^(gd|exif)$'
```

It should print `exif` and `gd`. They make the smaller, upright copy of a photo that the thread shows and Donna is shown. If either is missing, install it as root (`apt-get install -y php8.4-gd`; EXIF is part of `php8.4-common`) and restart PHP from Forge. Without GD a photo is still stored and shown, but at full size, and one over about 3.5 MB can't be shown to Donna at all: she will say it is too large to look at.

Next: [forge-site.md](forge-site.md).

---

[← All setup instructions](README.md)
