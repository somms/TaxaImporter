#!/usr/bin/env node

/**
 * Simple HTTP server that uses Playwright to render pages
 * This allows PHP to request JavaScript-rendered HTML
 *
 * Usage:
 *   node playwright-server.js [port]
 *
 * Or with Docker Playwright:
 *   Connect to ws://localhost:3000 from the Playwright Docker container
 */

const http = require('http');

const PORT = process.env.PORT || 3001;

// Check if playwright is available
let playwright;
try {
    playwright = require('playwright');
} catch (e) {
    console.error('Playwright is not installed. Install it with: npm install playwright');
    process.exit(1);
}

const server = http.createServer(async (req, res) => {
    // Enable CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
    }

    if (req.method === 'POST' && req.url === '/render') {
        let body = '';

        req.on('data', chunk => {
            body += chunk.toString();
        });

        req.on('end', async () => {
            try {
                const params = JSON.parse(body);
                const url = params.url;
                const waitForSelector = params.waitForSelector || 'body';
                const waitTimeout = params.waitTimeout || 10000;

                console.log(`[${new Date().toISOString()}] Rendering: ${url}`);

                // Launch browser and render page
                const browser = await playwright.chromium.launch({
                    headless: true
                });

                const context = await browser.newContext({
                    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                });

                const page = await context.newPage();

                // Intercept API calls to capture search results
                let apiResults = null;
                page.on('response', async (response) => {
                    const url = response.url();
                    // Look for API calls that might contain search results
                    if (url.includes('/api/') || url.includes('/search') || url.includes('species')) {
                        try {
                            const contentType = response.headers()['content-type'] || '';
                            if (contentType.includes('application/json')) {
                                const json = await response.json();
                                console.log(`Captured API response from: ${url}`);
                                console.log(`Response data:`, JSON.stringify(json).substring(0, 200));
                                apiResults = json;
                            }
                        } catch (e) {
                            // Not JSON or failed to parse
                        }
                    }
                });

                // Navigate to URL
                await page.goto(url, {
                    waitUntil: 'networkidle',
                    timeout: 30000
                });

                // Wait for React to render content
                // The IUCN site uses React which takes time to load
                console.log('Waiting for React app to load...');

                // Wait for the #redlist-js div to have content
                try {
                    await page.waitForFunction(
                        () => {
                            const redlistDiv = document.querySelector('#redlist-js');
                            return redlistDiv && redlistDiv.innerHTML.trim().length > 100;
                        },
                        { timeout: 8000 }
                    );
                    console.log('React content loaded in #redlist-js');
                } catch (e) {
                    console.warn('Timeout waiting for #redlist-js content, continuing anyway...');
                }

                // Additional wait for any animations or lazy loading
                await page.waitForTimeout(2000);

                // Log what we found
                const redlistContent = await page.evaluate(() => {
                    const div = document.querySelector('#redlist-js');
                    return div ? div.innerHTML.length : 0;
                });
                console.log(`#redlist-js content length: ${redlistContent} bytes`);

                // Get the rendered HTML
                const html = await page.content();

                await browser.close();

                console.log(`[${new Date().toISOString()}] Successfully rendered ${url} (${html.length} bytes)`);

                // Return the HTML
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: true,
                    html: html,
                    url: url
                }));

            } catch (error) {
                console.error(`[${new Date().toISOString()}] Error:`, error.message);

                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: false,
                    error: error.message
                }));
            }
        });

    } else if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            playwright: 'available'
        }));

    } else {
        res.writeHead(404, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            error: 'Not found',
            usage: 'POST /render with JSON body: { "url": "...", "waitForSelector": "...", "waitTimeout": 10000 }'
        }));
    }
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`Playwright HTTP server listening on http://0.0.0.0:${PORT}`);
    console.log(`Health check: http://localhost:${PORT}/health`);
    console.log(`Render endpoint: POST http://localhost:${PORT}/render`);
});
