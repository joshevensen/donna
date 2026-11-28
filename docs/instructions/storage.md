# File storage

Where Donna keeps the files you attach, and later the skills she loads. It is one DigitalOcean **Space** (S3-compatible storage).

Your bucket: `donna-joshevensen`, region `nyc3`.

What goes where:

- **In the Space, under `files/`:** the original of every PDF and image you attach, and a smaller copy of each large photo (`….preview.jpg`). These never change after upload.
- **In the database, not the Space:** the text of every markdown, CSV and text file, every version of it, and the text read out of each PDF. The nightly database dump covers those ([backups.md](backups.md)).

Nothing in the Space is ever deleted automatically. Asking Donna to delete a file hides it and lets her bring it back; the bytes stay.

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
- **`FILESYSTEM_DISK=s3` is what sends files to the Space.** Without it the app still works, but every PDF and image is written to the droplet's own disk (`storage/app/private/files`), which is small and can't be shrunk once grown. Set it before the first upload.

Save, then redeploy: the deploy script caches the configuration ([forge-site.md](forge-site.md)).

## 4. Check it

After the redeploy, on the server:

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan tinker --execute 'Storage::disk("s3")->put("check.txt", "ok"); echo Storage::disk("s3")->get("check.txt"); Storage::disk("s3")->delete("check.txt");'
```

It should print `ok`. In the Space's file list you will see nothing left behind.

Then confirm the app itself is using the Space, not the droplet's disk:

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan tinker --execute 'echo config("filesystems.default");'
```

It should print `s3`. If it prints `local`, `FILESYSTEM_DISK=s3` is missing from the site's environment, or the site wasn't redeployed after adding it.

If the first command fails, the usual causes are a wrong `AWS_ENDPOINT` (it must not contain the bucket name), a key limited to a different bucket, or a configuration that wasn't redeployed. In the web app the symptom is a chip that says "Couldn't upload" on a PDF or an image while text files upload fine: text goes to the database and never touches the Space.

## If files were uploaded before `FILESYSTEM_DISK=s3` was set

They are on the droplet, in `/home/forge/donna.joshevensen.com/storage/app/private/files` *(unverified: with zero-downtime deployments Forge keeps `storage` shared between releases, at the site's top level)*. Copy that folder's contents into the Space's `files/` folder, keeping the names, and they work again: the database only remembers each file's path, not which disk it was on.

## Rotating the key

Create a new key as in step 2, replace the two values in Forge, redeploy, then delete the old key in DigitalOcean.

Next: [backups.md](backups.md).

---

[← All setup instructions](README.md)
