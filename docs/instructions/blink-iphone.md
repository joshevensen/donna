# Donna on your iPhone (Blink Shell)

**This is the fallback.** The main way to talk to Donna on the iPhone is the web app on the Home Screen ([web-push.md](web-push.md)). Set this up if you want a way in that doesn't depend on the web app.

Donna also works from an iPhone through a terminal app that connects to the server over SSH and runs `donna:chat` there. [Blink Shell](https://apps.apple.com/app/blink-shell/id1594898306) does this.

Dropping the connection costs nothing. Donna writes her replies on the server, and the next time you open `donna:chat` it shows the latest exchange, including a reply that finished while you were away.

## Before you start

- The server is set up and Donna answers from your Mac ([terminal-on-mac.md](terminal-on-mac.md)).
- Blink Shell is installed on the phone.

## On the server

Create a token of its own for this, so it doesn't share one with your Mac.

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan donna:token blink
```

Add it to the site's environment in Forge, next to the other settings ([environment-variables.md](environment-variables.md)):

```dotenv
DONNA_API_URL=https://donna.joshevensen.com
DONNA_API_TOKEN=the-token-from-above
```

**Redeploy afterwards.** The deploy script rebuilds the config cache; until it runs, `donna:chat` still reports that the URL and token are missing.

Then add a shortcut, so a session is just `donna`. Run this once over SSH from your Mac:

```bash
echo "alias donna='cd /home/forge/donna.joshevensen.com/current && php8.4 artisan donna:chat'" >> ~/.bashrc
```

## In Blink

1. **Make a key.** Run `config`, tap **Keys**, tap **+**, choose **Generate New**, name it `donna`, and pick **Ed25519**. Blink keeps the private key in the iOS Keychain, encrypted by the Secure Enclave. It has no passphrase option, so turn on Face ID for the app itself (Blink's [docs](https://docs.blink.sh/basics/ssh-keys) say it can protect the whole app; look in its settings).
2. **Authorize it.** Copy the key's **public** key from its screen under Keys and paste it into Forge: the server's **Settings → SSH → Add key**, named `iPhone` *(unverified; see the fallback below)*.
3. **Add the host.** In `config`, open **Hosts**, tap **+**, and set Alias `donna`, Hostname `donna.joshevensen.com`, User `forge`, and Key `donna`. See Blink's [Hosts docs](https://docs.blink.sh/basics/hosts).
4. **Connect.** In Blink, run `ssh donna`, then `donna`.

Blink's docs don't say where the public-key copy option is, and it hasn't been confirmed. If step 2 has no obvious button, do it from the Mac instead: generate the pair there, then import the private half into Blink (Blink's docs mention importing keys but not the steps).

```bash
ssh-keygen -t ed25519 -f ~/donna-phone -C iphone
```

Add `~/donna-phone.pub` in Forge as above, import `donna-phone` into Blink under **Keys → +**, and delete the private file from the Mac afterwards.

## Using it

- **Sending a message:** Enter starts a new line. Tap **F5** in Blink's Smart Keys bar to send. Ctrl+D works too: hold **CTRL** there and press **D**. The bar is hidden when an external keyboard is attached. If F5 does nothing, set `DONNA_SUBMIT_KEY` to another key (`f1` to `f12`) in the site's environment and redeploy. What Blink sends for each key hasn't been confirmed.
- **Leaving:** type `/quit`, or just close Blink.
- **Dropped connections:** iOS can suspend Blink in the background, which can end a plain SSH session. Run `ssh donna` and `donna` again and nothing is lost. [Mosh](https://docs.blink.sh/advanced/advanced-mosh) survives network changes better, and `mosh donna` works the same way once `mosh` is installed on the server (`sudo apt-get install -y mosh`) and its UDP ports (60000 to 61000) are open in the server's firewall. Blink's docs don't say whether either survives the app being backgrounded, so it is optional.
- **A lost phone:** delete the `iPhone` key in Forge. The phone never holds the API token, which lives in the server's environment, so removing the key cuts off its access.

---

[← All setup instructions](README.md)
