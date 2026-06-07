#!/bin/bash

# AimTrack Agent Cloning Script
# Creates isolated development environments for AI agents

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 <agent-name> [base-branch]"
    echo ""
    echo "Arguments:"
    echo "  agent-name    Name for the agent (used for directory命名)"
    echo "  base-branch   Git branch to clone from (default: main)"
    echo ""
    echo "Example:"
    echo "  $0 claude-agent-1"
    echo "  $0 claude-agent-2 develop"
    echo ""
}

# Check arguments
if [ $# -lt 1 ]; then
    print_error "Missing agent name"
    show_usage
    exit 1
fi

AGENT_NAME="$1"
BASE_BRANCH="${2:-main}"

# Validate agent name
if [[ ! "$AGENT_NAME" =~ ^[a-zA-Z0-9_-]+$ ]]; then
    print_error "Agent name can only contain letters, numbers, underscores, and hyphens"
    exit 1
fi

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Check if we're in the AimTrack project
if [ ! -f "$PROJECT_ROOT/composer.json" ] || [ ! -f "$PROJECT_ROOT/artisan" ]; then
    print_error "This script must be run from within the AimTrack project"
    exit 1
fi

# Create parent directory for agent environments if it doesn't exist
AGENT_ENVS_DIR="$PROJECT_ROOT/../aimtrack-agent-envs"
mkdir -p "$AGENT_ENVS_DIR"

AGENT_DIR="$AGENT_ENVS_DIR/$AGENT_NAME"

# Check if agent environment already exists
if [ -d "$AGENT_DIR" ]; then
    print_error "Agent environment '$AGENT_NAME' already exists at $AGENT_DIR"
    print_status "Choose a different name or remove the existing environment first"
    exit 1
fi

print_status "Creating isolated development environment for agent: $AGENT_NAME"
print_status "Base branch: $BASE_BRANCH"
print_status "Target directory: $AGENT_DIR"

# Clone the repository
print_status "Cloning AimTrack repository..."
git clone --branch "$BASE_BRANCH" "$PROJECT_ROOT" "$AGENT_DIR"

# Enter the agent directory
cd "$AGENT_DIR"

# Create agent-specific configuration
print_status "Creating agent-specific configuration..."

# Create .env.local with unique compose project name
cat > .env.local << EOF
# Agent-specific environment configuration for $AGENT_NAME
COMPOSE_PROJECT_NAME=aimtrack_${AGENT_NAME}
UID=$(id -u)
GID=$(id -g)

# Use different ports to avoid conflicts
APP_PORT=$((8080 + $(echo $AGENT_NAME | md5sum | cut -c1-2 | tr -d '[:space:]')))
FORWARD_DB_PORT=$((3306 + $(echo $AGENT_NAME | md5sum | cut -c1-2 | tr -d '[:space:]')))

# Agent identification
AGENT_NAME=$AGENT_NAME
AGENT_MODE=true
EOF

# Create agent workspace directory
mkdir -p ".ai-logs"
mkdir -p ".ai-workspace"

# Create agent-specific README
cat > AGENT_README.md << EOF
# Agent Environment: $AGENT_NAME

This is an isolated development environment for the AI agent **$AGENT_NAME**.

## Setup
Run the following commands to set up the environment:

\`\`\`bash
# Setup the development environment
./scripts/setup-dev-env.sh

# Or manually:
# COMPOSE_PROJECT_NAME=aimtrack_${AGENT_NAME} docker compose -f docker/compose.dev.yml --env-file .env.local up -d
# COMPOSE_PROJECT_NAME=aimtrack_${AGENT_NAME} docker compose -f docker/compose.dev.yml --env-file .env.local exec app composer install
# COMPOSE_PROJECT_NAME=aimtrack_${AGENT_NAME} docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan key:generate
# COMPOSE_PROJECT_NAME=aimtrack_${AGENT_NAME} docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan migrate --seed
\`\`\`

## Access
- Application: http://localhost:\$(grep APP_PORT .env.local | cut -d'=' -f2)
- Database: localhost:\$(grep FORWARD_DB_PORT .env.local | cut -d'=' -f2)

## Agent Workflows
- AI agent logs: `.ai-logs/`
- Agent workspace: `.ai-workspace/`
- Git operations work independently from other environments

## Cleanup
To remove this agent environment:
\`\`\`bash
cd ../..
rm -rf $AGENT_NAME
\`\`\`

Created: $(date)
Agent: $AGENT_NAME
Base Branch: $BASE_BRANCH
EOF

# Initialize git repository for agent work
print_status "Setting up agent git workspace..."
git config user.name "Agent $AGENT_NAME"
git config user.email "agent-$AGENT_NAME@aimtrack.local"

# Create initial commit for agent workspace
git add .
git commit -m "Initial agent workspace setup for $AGENT_NAME" --no-verify || true

print_success "✅ Agent environment created successfully!"
print_status "📁 Location: $AGENT_DIR"
print_status "📖 Read AGENT_README.md for setup instructions"
print_status "🚀 Run 'cd $AGENT_DIR && ./scripts/setup-dev-env.sh' to start"

# Show next steps
echo ""
print_status "Next steps:"
echo "  1. cd $AGENT_DIR"
echo "  2. ./scripts/setup-dev-env.sh"
echo "  3. Start development work"
echo ""
print_status "Environment details:"
echo "  - Agent Name: $AGENT_NAME"
echo "  - Base Branch: $BASE_BRANCH"
echo "  - Project Root: $AGENT_DIR"
echo "  - Config File: $AGENT_DIR/.env.local"
