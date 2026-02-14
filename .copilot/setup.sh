#!/bin/bash
# Copilot Agent Setup Script
# This script configures the environment for Copilot agents to work with this repository

set -e

echo "🤖 Setting up Copilot agent environment..."

# Determine project root (where this script is located)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Authenticate to FluxUI Composer repository
# These credentials are required for composer install to work properly
if [ -n "$FLUX_USERNAME" ] && [ -n "$FLUX_LICENSE_KEY" ]; then
    echo "🔐 Configuring Composer authentication for FluxUI..."
    
    # Create auth.json in the project root to avoid exposing credentials in process listings
    cat > "$PROJECT_ROOT/auth.json" << EOF
{
    "http-basic": {
        "composer.fluxui.dev": {
            "username": "${FLUX_USERNAME}",
            "password": "${FLUX_LICENSE_KEY}"
        }
    }
}
EOF
    
    # Ensure auth.json has proper permissions
    chmod 600 "$PROJECT_ROOT/auth.json"
    
    echo "✅ Composer authentication configured successfully"
else
    echo "⚠️  WARNING: FLUX_USERNAME or FLUX_LICENSE_KEY environment variables not set"
    echo "⚠️  Composer will not be able to install private packages from FluxUI"
    echo "⚠️  Please ensure these secrets are configured in your environment"
fi

echo "✅ Copilot agent setup complete"
