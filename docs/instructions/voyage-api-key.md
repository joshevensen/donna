# Voyage AI key

The key Donna's memory searches with. Voyage turns every message and fact into numbers so she can find things by meaning. Anthropic has no embeddings model, which is why this is a separate key.

## Before you start

- The Forge site exists ([forge-site.md](forge-site.md)). That is where the key goes.

## Steps

1. Sign in at [voyageai.com](https://www.voyageai.com) and open **API keys** *(unverified; the label may differ)*.
2. Create a key. It works against `api.voyageai.com`, which is the address the app uses. (A key made in a MongoDB Atlas account is a different key and only works against `ai.mongodb.com`; see below.)
3. In Forge, open the site's environment file and add the key as `VOYAGEAI_API_KEY`, then **redeploy**. If you haven't redeployed since adding the Anthropic key, one redeploy covers both.

## Good to know

- Donna uses the `voyage-4` model. The first 200 million tokens are free; she prices them at list anyway, so her cost report runs slightly high.
- The vectors are 1024 numbers long and the database columns are built for that. Changing `DONNA_EMBEDDING_MODEL` to a model with a different length needs a migration, and every existing row needs embedding again.
- If the key is rejected with a `403` ("This API key cannot access this endpoint") and the Voyage dashboard says the key was never used, the key and the address don't match. A voyageai.com key needs `VOYAGEAI_URL=https://api.voyageai.com/v1` (the default); an Atlas key needs `VOYAGEAI_URL=https://ai.mongodb.com/v1`. Redeploy and restart the queue worker after changing it.
- Until embeddings work, Donna still saves messages, facts and topics without vectors and finds them by full-text search. Once the key works, the `EmbedMissing` job fills them in.

Next: [queue-and-scheduler.md](queue-and-scheduler.md).

---

[← All setup instructions](README.md)
