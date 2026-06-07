#!/bin/bash

# MCP GitHub Server Wrapper
# Dit script laadt de GITHUB_TOKEN uit de .env file en start de GitHub MCP server

# Navigeer naar project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

# Laad .env file en export GITHUB_TOKEN
if [ -f .env ]; then
    export $(grep -v '^#' .env | grep GITHUB_TOKEN | xargs)
fi

# Check of GITHUB_TOKEN is gezet
if [ -z "$GITHUB_TOKEN" ]; then
    echo "ERROR: GITHUB_TOKEN niet gevonden in .env file" >&2
    exit 1
fi

# Start de GitHub MCP server met de token
export GITHUB_PERSONAL_ACCESS_TOKEN="$GITHUB_TOKEN"
exec npx -y @modelcontextprotocol/server-github "$@"
