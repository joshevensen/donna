# File storage

Where Donna keeps files: uploads, and later the skills she loads. It is one DigitalOcean **Space** (S3-compatible storage). The app doesn't use it until Files arrive in Phase 3, so you can do this now and nothing changes until then. The site runs fine without it.

Your bucket: `donna-joshevensen`, region `nyc3`.

## 1. Keep the bucket private

1. In DigitalOcean, open **Spaces Object Storage** and click the bucket.
2. Under **Settings**, make sure **File Listing** is **Restricted**. Donna reads files with her own key and serves them herself, so nothing in it should be public.
3. Don't turn on the CDN unless you have a reason; private files don't benefit from it.

## 2. Make an access key for the app

Backups use their own key ([backups.md](backups.md)). Give the app a separate one, so rotating or leaking one doesn't touch the other.

1. In DigitalOcean, open **Spaces Object Storage**, then the **Access Keys** tab, then **Create Access Key**.
2. Name it `donna-app`.
3. Choose **Limited Access**, pick the `donna-joshevensen` bucket, and give it **Read/Write/Delete**. *(unverified: the permission names may differ.)*
4. Copy the key and the secret now. The secret is shown once.

## 3. Add it to the site's environment

In Forge, open the site's **Environment** and add:

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=nyc3
AWS_BUCKET=donna-joshevensen
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
AWS_URL=https://donna-joshevensen.nyc3.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

- `AWS_ENDPOINT` is the region's address **without** the bucket name. The address you were given for the bucket (`https://donna-joshevensen.nyc3.digitaloceanspaces.com`) goes in `AWS_URL`.
- Despite the `AWS_` names, these are your DigitalOcean values. Laravel's S3 driver reads those names.
- Don't set `FILESYSTEM_DISK=s3` before Phase 3 ships: until then the app needs the S3 driver package, which isn't installed yet. Add the other lines now if you like, and add that one when Phase 3 deploys.

Save, then redeploy: the deploy script caches the configuration ([forge-site.md](forge-site.md)).

## 4. Check it

Once Phase 3 is deployed, from the app directory on the server:

```bash
php artisan tinker --execute 'Storage::disk("s3")->put("check.txt", "ok"); echo Storage::disk("s3")->get("check.txt"); Storage::disk("s3")->delete("check.txt");'
```

It should print `ok`. In the Space's file list you will see nothing left behind.

If it fails, the usual causes are a wrong `AWS_ENDPOINT` (it must not contain the bucket name), a key limited to a different bucket, or a configuration that wasn't redeployed.

## Rotating the key

Create a new key as in step 2, replace the two values in Forge, redeploy, then delete the old key in DigitalOcean.

Next: [backups.md](backups.md).

---

[← All setup instructions](README.md)
