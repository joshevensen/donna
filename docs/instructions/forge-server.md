# Forge server

One DigitalOcean droplet, created and managed through Laravel Forge, runs everything: the app, Postgres, the queue worker and the scheduler.

## Before you start

- A Forge account with DigitalOcean connected as a server provider *(unverified; where this lives in Forge may differ)*.

## Create the server

In Forge, create a server on DigitalOcean:

- **Type:** App server
- **Size:** Basic, 2 GB RAM / 1 vCPU ($12 a month)
- **PHP:** 8.4
- **Database:** Postgres, with a database named `donna`
- **Backups:** turn on DigitalOcean droplet backups (see also [backups.md](backups.md))

Note the server's IP address. [forge-site.md](forge-site.md) needs it for DNS.

## Two things Forge doesn't do

SSH in:

```bash
ssh forge@<server-ip>
```

**Swap.** The droplet has little memory, so give it a safety net. Check whether it has any:

```bash
swapon --show
```

If that prints nothing, add 2 GB:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

**pgvector.** Postgres needs the vector extension for Donna's memory search. Find the Postgres version:

```bash
psql --version
```

Install the package for that version (here 17; swap in yours):

```bash
sudo apt-get install -y postgresql-17-pgvector
```

Then enable it in the database. The extension has to be created by the Postgres superuser, and the migrations assume it is there:

```bash
sudo -u postgres psql -d donna -c 'CREATE EXTENSION IF NOT EXISTS vector;'
```

## Check it

```bash
swapon --show
```

```bash
sudo -u postgres psql -d donna -c '\dx'
```

The first lists the swap file; the second lists `vector`.

Next: [forge-site.md](forge-site.md).
