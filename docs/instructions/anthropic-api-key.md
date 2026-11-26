# Anthropic API key

The key Donna thinks with. Her replies, topic tagging and the daily memory extraction all call Anthropic with it.

## Before you start

- The Forge site exists ([forge-site.md](forge-site.md)). That is where the key goes.

## Steps

1. Sign in at [console.anthropic.com](https://console.anthropic.com) and create an API key. Name it `donna`.
2. **Set a hard monthly spend limit** in the Console's limits (or billing) settings before you do anything else. Donna tracks what she costs, but nothing in her code stops spending. Pick a number you would be unhappy to pass.
3. In Forge, open the site's environment file and add the key as `ANTHROPIC_API_KEY`, then **redeploy**. The deploy caches the configuration, so the key isn't picked up until it runs. The key is not needed on your Mac or your phone.

## Good to know

- Donna uses Sonnet 5.5 for replies and extraction and Haiku 5.5 for topic tagging, so the key needs access to both. Daily extraction uses the Batch API, which needs no extra setup.
- To rotate the key: create a new one, change `ANTHROPIC_API_KEY` in the site's environment, redeploy, then delete the old key.

Next: [voyage-api-key.md](voyage-api-key.md).

---

[← All setup instructions](README.md)
