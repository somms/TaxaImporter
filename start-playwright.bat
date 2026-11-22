@echo off
REM Start Playwright server for IUCN scraping (Windows)

echo Starting Playwright server for IUCN scraping...

REM Check if Node.js is installed
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Node.js is not installed. Please install it from https://nodejs.org/
    pause
    exit /b 1
)

echo Node.js found:
node --version

REM Check if node_modules exists
if not exist "node_modules\" (
    echo Installing Playwright dependencies...
    call npm install
    echo Installing Chromium browser...
    call npm run install-browsers
)

REM Start the server
echo Starting Playwright server on http://localhost:3001
echo Press Ctrl+C to stop
echo.

node bin\playwright-server.js
