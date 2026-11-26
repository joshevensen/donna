# Voyage AI key

The key Donna's memory searches with. Voyage turns every message and fact into numbers so she can find things by meaning. Anthropic has no embeddings model, which is why this is a separate key.

## Before you start

- The Forge site exists ([forge-site.md](forge-site.md)). That is where the key goes.

## Steps

1. Voyage AI is owned by MongoDB, so the key comes from a MongoDB Atlas account. Sign in to Atlas and open **Model API keys** *(unverified; the label may differ)*. No MongoDB database is involved.
2. Create a key. It is a different key from one made on voyageai.com and only works against `ai.mongodb.com`, which is the address the app uses.
3. In Forge, open the site's environment file and add the key as `VOYAGEAI_API_KEY`, then **redeploy**. If you haven't redeployed since adding the Anthropic key, one redeploy covers both.

## Good to know

- Donna uses the `voyage-4` model. The first 200 million tokens are free; she prices them at list anyway, so her cost report runs slightly high.
- The vectors are 1024 numbers long and the database columns are built for that. Changing `DONNA_EMBEDDING_MODEL` to a model with a different length needs a migration, and every existing row needs embedding again.
- If the key is rejected, check it came from Atlas. `VOYAGEAI_URL` can point the app somewhere else.

Next: [queue-and-scheduler.md](queue-and-scheduler.md).

---

[← All setup instructions](README.md)
