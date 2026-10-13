# Deploying CalorieTracker (free hosting)

This app deploys as a single Docker container (Nginx + PHP-FPM) to
**[Render](https://render.com)** (free web service) using a free
**[Neon](https://neon.tech)** Postgres database.

Everything the container needs is in this repo:

| File | Purpose |
|------|---------|
| `Dockerfile` | Builds assets (Node) + runs the app (PHP-FPM + Nginx) |
| `docker/entrypoint.sh` | Caches config, runs migrations, starts the server on `$PORT` |
| `docker/nginx.conf.template` | Nginx site config (port injected at boot) |
| `docker/php.ini` | Opcache + upload limits |
| `render.yaml` | Render Blueprint — defines the service + env vars |

> The same Docker image also runs on **Fly.io**, **Railway**, or **Koyeb** if you
> prefer — only the dashboard steps differ.

---

## ⚠️ First: rotate your API keys

Your local `.env` contains **real** API keys (Groq, USDA — and a Gemini key).
`.env` is gitignored, so it won't be pushed, **but these keys have been visible
on-screen**, so rotate them to be safe and paste the *new* values into Render:

- Groq: <https://console.groq.com/keys>
- USDA: <https://fdc.nal.usda.gov/api-key-signup>
- Gemini (if you switch providers): <https://aistudio.google.com/app/apikey>

Never commit real keys — always set them as environment variables on the host.

---

## Step 1 — Create the database (Neon)

1. Sign up at <https://neon.tech> and create a project. **Pick the same region as the Render service** (Render
   defaults to Oregon / US West unless you choose another when creating it).
   Every page talks to the database several times, so a database on another
   continent adds seconds to each page load.
2. In the project's **Connection Details**, note these values — you'll paste them
   into Render in Step 4:
   - **Host** → `DB_HOST` (e.g. `ep-cool-name-123456.us-east-2.aws.neon.tech`)
   - **Database** → `DB_DATABASE` (default `neondb`)
   - **Role/User** → `DB_USERNAME`
   - **Password** → `DB_PASSWORD`

   Port is `5432` and SSL is `require` (already set in `render.yaml`).

---

## Step 2 — Push this repo to GitHub

```bash
git add .
git commit -m "Add Docker + Render deployment config"
git push
```

(If the repo isn't on GitHub yet, create one and push to it — Render deploys from GitHub.)

---

## Step 3 — Create the Render service from the Blueprint

1. Go to <https://dashboard.render.com> → **New** → **Blueprint**.
2. Connect your GitHub repo. Render reads `render.yaml` and shows the
   `lorie-app` web service.
3. Click **Apply**. Render will prompt for the `sync: false` (secret) values —
   or you can fill them in the service's **Environment** tab after it's created.

---

## Step 4 — Set the secret environment variables

Generate an app key locally first:

```bash
php artisan key:generate --show
```

Then set these in Render (Environment tab):

| Variable | Value |
|----------|-------|
| `APP_KEY` | the `base64:...` string from the command above |
| `APP_URL` | your Render URL, e.g. `https://lorie-app.onrender.com` |
| `DB_HOST` | from Neon |
| `DB_DATABASE` | from Neon |
| `DB_USERNAME` | from Neon |
| `DB_PASSWORD` | from Neon |
| `GOOGLE_CLIENT_ID` | from Google Cloud (see "Google sign-in" below) |
| `GOOGLE_CLIENT_SECRET` | from Google Cloud |
| `GEMINI_API_KEY` | your Gemini key (primary AI provider) |
| `GROQ_API_KEY` | your (rotated) Groq key |
| `USDA_API_KEY` | your (rotated) USDA key |

The non-secret values (`APP_ENV`, `DB_PORT`, `DB_SSLMODE`, provider models, etc.)
are already baked into `render.yaml`.

### Google sign-in

The "Continue with Google" button only appears once `GOOGLE_CLIENT_ID` is set.
Email + password sign-up works without it.

1. In the [Google Cloud console](https://console.cloud.google.com/apis/credentials),
   create (or pick) a project and configure the **OAuth consent screen**
   (External; only the `email` and `profile` scopes are needed).
2. **Create credentials -> OAuth client ID -> Web application.**
3. Under **Authorized redirect URIs** add your app URL followed by
   `/auth/google/callback`, e.g.
   `https://lorie-app.onrender.com/auth/google/callback`
   (add `http://localhost:8000/auth/google/callback` too if you test locally).
4. Copy the client ID and secret into `GOOGLE_CLIENT_ID` and
   `GOOGLE_CLIENT_SECRET`.

While the consent screen is in **Testing** mode, only the Google accounts you
list as test users can sign in. Publish it to let anyone in.

---

## Step 5 — Deploy & verify

Render builds the image and starts the container. On boot, the entrypoint
automatically:

- caches config / routes / views,
- runs `php artisan migrate --force` (creates all tables, including the
  sessions/cache/jobs tables),
- starts Nginx on Render's `$PORT`.

When the deploy is **live**, open your `APP_URL` and create your account. Add a
food entry to confirm the AI provider and database both work.

> **Upgrading from the version without accounts?** Sign up first, before sharing
> the link. The first account ever created inherits the existing food log and
> profile; every account after that starts empty.

Watch the **Logs** tab if anything fails — migration or connection errors show up
there.

---

## Notes & tips

- **Cold starts:** the free web service sleeps after ~15 min idle. The next
  request takes ~30–50s to wake it. Fine for personal use.
- **Photo uploads** are processed in memory (sent to the AI, never written to
  disk), so the ephemeral container filesystem is not a problem.
- **Accounts:** every person signs up (email + password, or Google) and gets
  their own food log, goal and streak. There is no password-reset email, since
  no mail service is configured; Google sign-in is the recovery path.
- **Redeploys:** just `git push` — `autoDeploy` rebuilds and re-runs migrations.
- **AI providers:** Gemini (`gemini-3.5-flash`) is the primary; the app
  automatically falls back to Groq when Gemini is rate-limited, then returns to
  Gemini once its quota resets. Add both `GEMINI_API_KEY` and `GROQ_API_KEY` in
  Render's Environment tab so the fallback has somewhere to go.
- **No background worker needed:** AI analysis runs synchronously during the
  request. If you later queue work, add a second Render service (a *Background
  Worker*) running `php artisan queue:work`.

### Test the container locally (optional)

```bash
docker build -t lorie-app .
docker run --rm -p 8080:8080 --env-file .env lorie-app
# open http://localhost:8080
```

(Point `DB_HOST` at your Neon database, since the container has no local Postgres.)
