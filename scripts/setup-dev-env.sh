#!/bin/bash

# AimTrack Development Environment Setup Script
# This script sets up a complete development environment for AI agents

set -e

echo "🎯 Setting up AimTrack Development Environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
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

# Check if we're in the right directory
if [ ! -f "composer.json" ] || [ ! -f "artisan" ]; then
    print_error "Please run this script from the AimTrack project root directory"
    exit 1
fi

# Get current user ID and group ID for Docker permissions
USER_ID=$(id -u)
GROUP_ID=$(id -g)

print_status "User ID: $USER_ID, Group ID: $GROUP_ID"

# Create .env.local if it doesn't exist
if [ ! -f ".env.local" ]; then
    print_status "Creating .env.local from template..."
    cp .env.local.example .env.local
    
    # Set user/group IDs in .env.local
    sed -i "s/UID=1000/UID=$USER_ID/" .env.local
    sed -i "s/GID=1000/GID=$GROUP_ID/" .env.local
    
    print_success "Created .env.local with your user permissions"
else
    print_warning ".env.local already exists, skipping creation"
fi

# Create .env if it doesn't exist
if [ ! -f ".env" ]; then
    print_status "Creating .env from template..."
    cp .env.example .env
    print_success "Created .env"
else
    print_warning ".env already exists, skipping creation"
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker first."
    exit 1
fi

print_success "Docker is running"

# Build and start the development stack
print_status "Building and starting development containers..."
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local up -d --build

# Wait for containers to be ready
print_status "Waiting for containers to be ready..."
sleep 10

# Install dependencies
print_status "Installing PHP dependencies..."
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local exec app composer install

# Generate application key
print_status "Generating application key..."
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan key:generate

# Run migrations
print_status "Running database migrations..."
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan migrate --force

# Seed database (if needed)
print_status "Seeding database..."
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan db:seed --class=DatabaseSeeder --force

# Run tests to verify setup
print_status "Running tests to verify setup..."
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local exec app vendor/bin/pest --compact

# Check code style
print_status "Checking code style..."
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local exec app vendor/bin/pint --test

print_success "✅ Development environment setup complete!"
print_status "🌐 Application is available at: http://localhost:8080"
print_status "📚 More info in docs/ directory"
print_status "🤖 AI agents can now use this environment for development"

# Show container status
print_status "Container status:"
COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local ps

echo ""
print_status "Useful commands:"
echo "  📦 Install dependencies: docker compose -f docker/compose.dev.yml --env-file .env.local exec app composer install"
echo "  🗄️ Run migrations: docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan migrate"
echo "  🧪 Run tests: docker compose -f docker/compose.dev.yml --env-file .env.local exec app vendor/bin/pest"
echo "  🎨 Fix code style: docker compose -f docker/compose.dev.yml --env-file .env.local exec app vendor/bin/pint"
echo "  🛑 Stop containers: COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local down"
echo "  📋 View logs: COMPOSE_PROJECT_NAME=aimtrack_dev docker compose -f docker/compose.dev.yml --env-file .env.local logs -f"
