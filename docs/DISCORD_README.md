# Discord Integration — Setup Guide

This guide walks you through obtaining the Discord credentials needed to run the Discord integration.

---

## 1. Create a Discord Application

1. Go to the [Discord Developer Portal](https://discord.com/developers/applications)
2. Click **"New Application"** → name it (e.g., "Lighthouse Website") → Create
3. On the **General Information** page, copy the **Application ID** — this is your `DISCORD_CLIENT_ID`

## 2. Get the Client Secret

1. Go to the **OAuth2** section in the left sidebar
2. Under **Client Information**, click **"Reset Secret"** and confirm
3. Copy the secret — this is your `DISCORD_CLIENT_SECRET`

## 3. Set the Redirect URI

1. Still in **OAuth2**, scroll to **Redirects**
2. Add your callback URL:
   - Local dev: `http://localhost:8000/auth/discord/callback`
   - Production: `https://yourdomain.com/auth/discord/callback`
3. Save

## 4. Create the Bot

1. Go to the **Bot** section in the left sidebar
2. Click **"Reset Token"** and confirm
3. Copy the token — this is your `DISCORD_BOT_TOKEN`
4. Under **Privileged Gateway Intents**, enable **Server Members Intent** (needed for role management)

## 5. Invite the Bot to Your Server

1. Go to **OAuth2 → URL Generator**
2. Select scopes: `bot`
3. Select bot permissions: `Manage Roles`, `Send Messages`, `Create Instant Invite`
4. Copy the generated URL, open it in your browser, and select your Discord server

## 6. Get Your Guild ID

1. In Discord (the app), go to **Settings → Advanced → Enable Developer Mode**
2. Right-click your server name → **Copy Server ID**
3. This is your `DISCORD_GUILD_ID`

## 7. Get Role IDs

1. In your Discord server, go to **Server Settings → Roles**
2. Create roles for each mapping (or use existing ones):
   - **Membership:** Traveler, Resident, Citizen, Verified
   - **Staff Departments:** Command, Chaplain, Engineer, Quartermaster, Steward
   - **Staff Ranks:** Jr. Crew, Crew Member, Officer
3. With Developer Mode on, right-click each role → **Copy Role ID**
4. **Important:** The bot's role must be **above** all managed roles in the role list, or it won't be able to assign them

## 8. Fill in Your `.env`

```env
# Discord OAuth2 + Bot Credentials
DISCORD_CLIENT_ID=your_application_id
DISCORD_CLIENT_SECRET=your_client_secret
DISCORD_BOT_TOKEN=your_bot_token
DISCORD_GUILD_ID=your_server_id
DISCORD_REDIRECT_URI="${APP_URL}/auth/discord/callback"
MAX_DISCORD_ACCOUNTS=1

# Membership Role IDs
DISCORD_ROLE_TRAVELER=
DISCORD_ROLE_RESIDENT=
DISCORD_ROLE_CITIZEN=

# Staff Department Role IDs
DISCORD_ROLE_STAFF_COMMAND=
DISCORD_ROLE_STAFF_CHAPLAIN=
DISCORD_ROLE_STAFF_ENGINEER=
DISCORD_ROLE_STAFF_QUARTERMASTER=
DISCORD_ROLE_STAFF_STEWARD=

# Staff Rank Role IDs
DISCORD_ROLE_RANK_JR_CREW=
DISCORD_ROLE_RANK_CREW_MEMBER=
DISCORD_ROLE_RANK_OFFICER=

# Special Role IDs
DISCORD_ROLE_VERIFIED=
```

## Local Development Notes

- The only part that contacts Discord's servers is the **OAuth2 link flow** (redirect + callback). This requires real `DISCORD_CLIENT_ID` and `DISCORD_CLIENT_SECRET` values.
- All role management and DM sending is handled by `FakeDiscordApiService` locally, so **role IDs can be left empty** if you just want to test the linking UI.
- Fake API calls are logged to `storage/logs/laravel.log` with the `[FakeDiscord]` prefix.

---

For the full feature specification, see [DISCORD_INTEGRATION.md](DISCORD_INTEGRATION.md).
