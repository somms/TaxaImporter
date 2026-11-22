#!/bin/bash

# Start Playwright server for IUCN scraping
# This script checks if Node.js and Playwright are installed,
# then starts the Playwright HTTP server

set -e

echo "🎭 Starting Playwright server for IUCN scraping..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install it first:"
    echo "   - Ubuntu/Debian: sudo apt install nodejs npm"
    echo "   - macOS: brew install node"
    echo "   - Windows: Download from https://nodejs.org/"
    exit 1
fi

echo "✅ Node.js found: $(node --version)"

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install it first."
    exit 1
fi

echo "✅ npm found: $(npm --version)"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "📦 Installing Playwright dependencies..."
    npm install
    echo "🌐 Installing Chromium browser..."
    npm run install-browsers
fi

# Check if Playwright is installed
if [ ! -d "node_modules/playwright" ]; then
    echo "❌ Playwright is not installed. Running npm install..."
    npm install
fi

echo "✅ Playwright is installed"

# Start the server
echo "🚀 Starting Playwright server on http://localhost:3001"
echo "   Press Ctrl+C to stop"
echo ""

node bin/playwright-server.js
