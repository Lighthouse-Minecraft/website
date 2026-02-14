#!/bin/bash
# Copilot Agent Setup Script
# This script configures the environment for Copilot agents to work with this repository

set -e

echo "🤖 Setting up Copilot agent environment..."

# Authenticate to FluxUI Composer repository
# These credentials are required for composer install to work properly
if [ -n "$FLUX_USERNAME" ] && [ -n "$FLUX_LICENSE_KEY" ]; then
    echo "🔐 Configuring Composer authentication for FluxUI..."
    composer config --global --auth http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"
    echo "✅ Composer authentication configured successfully"
else
    echo "⚠️  WARNING: FLUX_USERNAME or FLUX_LICENSE_KEY environment variables not set"
    echo "⚠️  Composer will not be able to install private packages from FluxUI"
    echo "⚠️  Please ensure these secrets are configured in your environment"
fi

echo "✅ Copilot agent setup complete"
