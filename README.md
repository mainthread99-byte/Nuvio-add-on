# Nuvio Latin Streaming Add-on

A modular Nuvio add-on that scrapes HLS streams from Latin streaming sites (starting with Pelispedia).

## Features

- **Admin Panel** — Enable/disable sites and configure proxy
- **Modular Scrapers** — Easy to add new sites
- **Proxy Support** — Bypass geo-blocks and Cloudflare
- **Nuvio Integration** — Standard add-on manifest + stream routes

## Quick Start

### Local Testing

1. Install PHP 8.0+
2. Edit `config.json` to enable/configure sites
3. Run: `php -S localhost:8000`
4. Visit admin: http://localhost:8000/admin.php (default password: `changeme`)

### Test Scraper

```bash
# Test extracting a stream from Pelispedia
curl 'http://localhost:8000/?action=stream&type=movie&id=https://pelispedia.mov/pelicula/jackass-la-ultima-y-nos-vamos-YOJCwS'
```

### Add to Nuvio

Use the manifest endpoint in Nuvio add-on settings:
```
https://your-addon-url/?action=manifest
```

## Deployment to Render

### 1. Create GitHub Secrets

In your GitHub repo → Settings → Secrets and variables → Actions, add:

- `RENDER_API_KEY` — Get from Render dashboard → Account → API Keys
- `RENDER_SERVICE_ID` — Your Render service ID (from service URL)
- `NUVIO_APP_URL` — Your deployed app URL (e.g., https://addon.onrender.com)
- `NUVIO_TEST_URL` — Test Pelispedia URL for CI

Optional:
- `NUVIO_PROXY_URL` — Proxy URL (e.g., http://proxy.example.com:8080)
- `NUVIO_PROXY_USER` — Proxy username
- `NUVIO_PROXY_PASS` — Proxy password

### 2. Deploy

Push to `main` branch or run GitHub Actions workflow manually.

## Adding New Sites

### 1. Create a Scraper

Create `scrapers/mysite.php`:

```php
<?php
function scrape_mysite($imdbId, $config) {
    // Your scraping logic
    // Return the .m3u8 URL or null
    
    $html = _curl_get($imdbId, $config);
    // ... extract and return .m3u8
}
```

### 2. Enable in Config

Edit `config.json`:

```json
"sites": {
  "mysite": {
    "name": "My Site",
    "enabled": true
  }
}
```

### 3. Test

Use admin panel or curl:
```bash
curl 'http://localhost:8000/?action=stream&type=movie&id=<url-or-id>'
```

## File Structure

```
addon/
├── index.php              # Nuvio add-on entry point (manifest + stream routes)
├── admin.php              # Admin panel (site toggles, proxy config)
├── config.json            # Configuration (sites, proxy, password)
├── scrapers/
│   └── pelispedia.php     # Pelispedia scraper module
├── .github/workflows/
│   └── deploy.yml         # CI/CD workflow for Render
├── Dockerfile             # Docker build config
└── README.md              # This file
```

## Security Notes

- **Admin password** stored in `config.json` — change default in admin panel
- **Proxy credentials** stored in `config.json` or env vars — use GitHub Secrets for Render
- **Never commit secrets** to git — use `.gitignore` if testing locally

## Troubleshooting

- **403 error from target site** — Enable proxy in admin panel
- **Scraper returns empty** — Check `config.json` to ensure site is enabled
- **Render deploy fails** — Check workflow logs in GitHub Actions

## Next Steps

1. Test with Jackass URL locally
2. Deploy to Render
3. Add proxy credentials if needed
4. Provide new site URLs for scraper implementation
