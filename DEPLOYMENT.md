# Deployment

Mithril uses a webhook-based deployment triggered by GitHub Actions on push to `main`.

## How it works

1. Push to `main` triggers `.github/workflows/deploy.yml`
2. The workflow sends a POST request to `deploy.php` on your server
3. The deploy script authenticates via Bearer token, then runs:
   - `git fetch` + `git pull`
   - `composer install --no-dev`
   - `php artisan migrate --force`
   - `php artisan optimize:clear` + `php artisan optimize`
   - `npm ci --omit=dev` + `npm run build`
4. The response includes a log of each step for debugging

## Server prerequisites

- PHP 8.4+ with `exec()` enabled (not in `disable_functions`)
- Git, Composer, Node.js, and npm available in `PATH` for the web server user
- The web server user must have read/write access to the project directory
- The git remote `origin` must be configured (SSH key or token-based HTTPS)

## Setup

### 1. Generate a deploy token

```bash
openssl rand -hex 32
```

### 2. Add the token to your server's `.env`

```env
DEPLOY_TOKEN=your-generated-token-here
```

### 3. Configure GitHub repository

Add these in your repository settings (Settings > Secrets and variables > Actions):

| Type       | Name           | Value                                    |
|------------|----------------|------------------------------------------|
| **Secret** | `DEPLOY_TOKEN` | The token from step 1                    |
| **Variable** | `DEPLOY_URL` | Your production URL (e.g. `https://mithril.example.com`) |

### 4. Verify web server routing

Ensure `deploy.php` is accessible at `https://your-domain/deploy.php`. With Laravel's default nginx/Apache config serving from `public/`, this should work out of the box.

### 5. Test manually

```bash
curl -X POST \
  -H "Authorization: Bearer your-token-here" \
  https://your-domain/deploy.php
```

A successful response looks like:

```json
{
  "success": true,
  "message": "Deployment successful.",
  "timestamp": "2026-03-10T14:30:00+00:00",
  "branch": "main",
  "log": {
    "git_status": "",
    "git_fetch": "",
    "git_pull": "...",
    "composer_install": "...",
    "migrate": "Nothing to migrate.",
    "cache_clear": "...",
    "cache_warm": "...",
    "npm_install": "...",
    "npm_build": "..."
  }
}
```

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| 405 Method Not Allowed | GET request instead of POST | Ensure `curl -X POST` is used |
| 401 Invalid token | Token mismatch | Verify `DEPLOY_TOKEN` in `.env` matches the GitHub secret |
| 500 DEPLOY_TOKEN not configured | Missing from `.env` | Add `DEPLOY_TOKEN=...` to the server's `.env` file |
| 500 Uncommitted changes | Dirty working tree on server | SSH in and resolve manually (`git stash` or `git checkout .`) |
| Git fetch/pull failed | SSH key not configured or permissions issue | Ensure the web server user can `git pull` from the repo |
| Composer/npm not found | Binary not in web server user's PATH | Install globally or symlink to `/usr/local/bin/` |
| Migration failed | Database credentials or permissions | Check `.env` DB settings and that the DB user has ALTER/CREATE privileges |

## Security notes

- The deploy endpoint only accepts POST requests
- Authentication uses constant-time token comparison (`hash_equals`) to prevent timing attacks
- Token is only accepted via the `Authorization: Bearer` header (never via URL parameters)
- The deploy script runs outside Laravel's request lifecycle intentionally — it must work even when the framework is not bootable (e.g. mid-composer-install)
