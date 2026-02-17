# Copilot Agent Configuration

This directory contains configuration and setup scripts for GitHub Copilot agents working on this repository.

## Files

### setup.sh

This script configures the environment for Copilot agents to work properly with this repository. It:

1. Configures Composer authentication for the FluxUI private repository
2. Uses `FLUX_USERNAME` and `FLUX_LICENSE_KEY` environment variables for authentication

**Usage:**

```bash
bash .copilot/setup.sh
```

**Environment Variables Required:**

- `FLUX_USERNAME` - FluxUI Composer repository username
- `FLUX_LICENSE_KEY` - FluxUI Composer repository license key

These environment variables should be configured as GitHub secrets in the repository settings and will be automatically available to Copilot agents running in GitHub Actions contexts.

## Why This Exists

This project uses private packages from FluxUI (livewire/flux-pro) that require authentication to download. The GitHub Actions workflows use repository secrets to authenticate, and Copilot agents need the same setup to successfully run `composer install`.
