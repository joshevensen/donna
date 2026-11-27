# Backups

Everything Donna knows lives in one Postgres database, so that is what to protect. Two layers.

## 1. Droplet backups

Turned on when you created the server ([forge-server.md](forge-server.md)). These capture the whole machine.

## 2. Database dumps to Spaces

A nightly dump stored somewhere that survives the droplet.

1. In DigitalOcean, create a **Spaces** bucket and an access key for it. Keep the key and secret for the next step.
2. In Forge, add a backup configuration for the server: provider "S3 compatible" (or DigitalOcean Spaces), your Spaces endpoint, bucket, key and secret; database `donna`; daily; keep 14.

If your Forge plan doesn't include database backups, install `s3cmd` on the server and configure it once with the Spaces key:

```bash
s3cmd --configure
```

Then add a nightly scheduled job in Forge that runs:

```bash
pg_dump -U forge donna | gzip | s3cmd put - s3://YOUR-BUCKET/donna/$(date +\%F).sql.gz
```

## Prove it works

Restore one dump into a scratch database once, so you know the backups are real and how long a restore takes.

Spaces credentials never go in the app's environment. The app itself doesn't use Spaces until files arrive in Phase 3.

Next: [mail.md](mail.md).

---

[← All setup instructions](README.md)
