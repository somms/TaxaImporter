# Playwright Setup for IUCN Scraping

This document explains how to set up and use Playwright to scrape the IUCN Red List website, which uses JavaScript to load search results dynamically.

## Why Playwright?

The IUCN website is a Single Page Application (SPA) that loads search results via JavaScript after the initial page load. Traditional HTTP requests only get the empty HTML shell, not the actual search results. Playwright solves this by using a real browser that executes JavaScript.

## Installation

### Option 1: Local Installation (Recommended for Development)

1. **Install Node.js** (if not already installed):
   - Download from https://nodejs.org/
   - Or use your package manager: `apt install nodejs npm` (Linux) or `brew install node` (Mac)

2. **Install Playwright dependencies**:
   ```bash
   cd /path/to/BVImporter
   npm install
   npm run install-browsers
   ```

3. **Start the Playwright server**:
   ```bash
   node bin/playwright-server.js
   ```

   The server will start on `http://localhost:3001`

### Option 2: Docker (Alternative)

If you prefer to use the Docker Playwright container you already have:

1. **Modify the Playwright server script** to connect to the Docker container's WebSocket endpoint
2. **Or** run the Node.js server inside the Docker container

## Usage

Once the Playwright server is running, your PHP code will automatically use it when configured in the pipeline YAML:

```yaml
options:
  playwright_url: http://localhost:3001
  use_playwright: true
```

## Testing

Test if the Playwright server is running:

```bash
curl http://localhost:3001/health
```

Expected response:
```json
{"status":"ok","playwright":"available"}
```

Test rendering a page:

```bash
curl -X POST http://localhost:3001/render \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.iucnredlist.org/search?searchType=species&query=Cervus",
    "waitForSelector": "#redlist-js",
    "waitTimeout": 10000
  }'
```

## Running the Pipeline

With Playwright server running:

```bash
php src/Importer.php clean-species --pipeline=ObsMammals-IUCN
```

## Troubleshooting

### Server not starting

- **Error: Cannot find module 'playwright'**
  - Run: `npm install`

- **Error: Executable doesn't exist**
  - Run: `npm run install-browsers`

### Server running but not working

- Check the server logs for errors
- Verify the URL in the config matches where the server is running
- Test with the curl commands above

### Slow performance

- Playwright launches a real browser for each request, which takes time
- Consider implementing a browser pool if you need better performance
- The server keeps browsers open between requests to improve speed

## Configuration Options

In `config/pipelines/ObsMammals-IUCN.yml`:

```yaml
options:
  # Playwright configuration (recommended)
  playwright_url: http://localhost:3001
  use_playwright: true

  # FlareSolverr (alternative, not recommended for IUCN)
  flaresolverr_url: http://localhost:8191/v1
  use_flaresolverr: false

  # API v4 (always tried first)
  api_token: YOUR_TOKEN_HERE
```

## How it Works

1. **API v4 First**: The system tries the official IUCN API v4 first (fastest)
2. **Playwright Fallback**: If API doesn't find the species, Playwright renders the search page
3. **Parse Results**: The rendered HTML is parsed to extract species information
4. **API for Details**: If a taxonid is found, the API is used to get full details

This hybrid approach gives you:
- ✅ Speed for exact matches (API)
- ✅ Synonym support (Playwright scraping)
- ✅ Full species details (API)
