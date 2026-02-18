# Minecraft Account Verification API

This document describes the API endpoint for completing Minecraft account verification. This endpoint should be called by your Minecraft server plugin when a player runs the `/verify <code>` command in-game.

## Overview

The verification flow works as follows:

1. User generates a verification code on the website (via `/settings/minecraft-accounts`)
2. User is temporarily whitelisted on the server with a 30-minute grace period
3. User joins the server and runs `/verify <code>` in-game
4. Your Minecraft plugin calls this API endpoint with the code and player details
5. The website validates the code and completes the account linking

## Endpoint

```
POST /api/minecraft/verify
```

## Authentication

This endpoint uses a shared secret token authentication. You must include the server token in the request body.

The token is configured in your `.env` file:
```env
MINECRAFT_VERIFICATION_TOKEN=your-secure-random-token-here
```

Generate a secure random token using:
```bash
php artisan tinker
>>> Str::random(64)
```

## Rate Limiting

- **30 requests per minute** per IP address
- Returns HTTP 429 (Too Many Requests) when limit exceeded

## Request Format

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Body Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `server_token` | string | Yes | Shared secret token for server authentication |
| `code` | string | Yes | 6-character verification code (case-insensitive) |
| `minecraft_username` | string | Yes | Player's Minecraft username (3-16 characters) |
| `minecraft_uuid` | string | Yes | Player's Minecraft UUID (with or without dashes) |

### Example Request

```bash
curl -X POST https://your-domain.com/api/minecraft/verify \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "server_token": "your-secure-random-token-here",
    "code": "ABC123",
    "minecraft_username": "Notch",
    "minecraft_uuid": "069a79f4-44e9-4726-a5be-fca90e38aaf5"
  }'
```

### UUID Format

The API accepts UUIDs in both formats:
- With dashes: `069a79f4-44e9-4726-a5be-fca90e38aaf5`
- Without dashes: `069a79f444e94726a5befca90e38aaf5`

## Response Format

### Success Response (200 OK)

```json
{
  "message": "Minecraft account verified successfully!",
  "success": true,
  "data": {
    "username": "Notch",
    "uuid": "069a79f4-44e9-4726-a5be-fca90e38aaf5",
    "account_type": "java"
  }
}
```

### Error Responses

#### Invalid Server Token (401 Unauthorized)
```json
{
  "message": "Invalid server token.",
  "success": false
}
```

#### Validation Errors (422 Unprocessable Entity)
```json
{
  "message": "The server token field is required. (and 2 more errors)",
  "errors": {
    "server_token": [
      "The server token field is required."
    ],
    "code": [
      "The code field is required."
    ],
    "minecraft_username": [
      "The minecraft username field is required."
    ]
  }
}
```

#### Verification Not Found (404 Not Found)
```json
{
  "message": "Verification code not found or already used.",
  "success": false
}
```

#### Code Expired (410 Gone)
```json
{
  "message": "Verification code has expired.",
  "success": false
}
```

#### UUID Already Linked (409 Conflict)
```json
{
  "message": "This Minecraft account is already linked to a website account.",
  "success": false
}
```

#### Server Error (500 Internal Server Error)
```json
{
  "message": "An error occurred while processing the verification.",
  "success": false
}
```

#### Rate Limit Exceeded (429 Too Many Requests)
```json
{
  "message": "Too Many Attempts.",
  "success": false
}
```

## Implementation Notes

### For Plugin Developers

1. **Store the server token securely** in your plugin configuration file
2. **Validate user input** before calling the API (ensure code is 6 alphanumeric characters)
3. **Handle all error responses** gracefully and provide feedback to the player
4. **Implement retry logic** with exponential backoff for transient failures
5. **Log all verification attempts** for debugging purposes
6. **Use HTTPS** in production to protect the server token

### Example Java Plugin Code

```java
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;

public void verifyPlayer(Player player, String code) {
    String serverToken = getConfig().getString("verification-token");
    String apiUrl = getConfig().getString("api-url") + "/api/minecraft/verify";
    
    JsonObject payload = new JsonObject();
    payload.addProperty("server_token", serverToken);
    payload.addProperty("code", code.toUpperCase());
    payload.addProperty("minecraft_username", player.getName());
    payload.addProperty("minecraft_uuid", player.getUniqueId().toString());
    
    HttpClient client = HttpClient.newHttpClient();
    HttpRequest request = HttpRequest.newBuilder()
        .uri(URI.create(apiUrl))
        .header("Content-Type", "application/json")
        .header("Accept", "application/json")
        .POST(HttpRequest.BodyPublishers.ofString(payload.toString()))
        .build();
    
    client.sendAsync(request, HttpResponse.BodyHandlers.ofString())
        .thenApply(HttpResponse::body)
        .thenAccept(responseBody -> {
            JsonObject response = JsonParser.parseString(responseBody).getAsJsonObject();
            
            if (response.has("success") && response.get("success").getAsBoolean()) {
                player.sendMessage("§aYour Minecraft account has been verified successfully!");
            } else {
                String message = response.get("message").getAsString();
                player.sendMessage("§cVerification failed: " + message);
            }
        })
        .exceptionally(ex -> {
            player.sendMessage("§cAn error occurred during verification. Please try again later.");
            getLogger().severe("Verification API error: " + ex.getMessage());
            return null;
        });
}
```

### Example Spigot/Paper Command

```java
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;

public class VerifyCommand implements CommandExecutor {
    
    @Override
    public boolean onCommand(CommandSender sender, Command command, String label, String[] args) {
        if (!(sender instanceof Player)) {
            sender.sendMessage("§cThis command can only be used by players.");
            return true;
        }
        
        Player player = (Player) sender;
        
        if (args.length != 1) {
            player.sendMessage("§cUsage: /verify <code>");
            return true;
        }
        
        String code = args[0];
        
        if (!code.matches("^[A-Z0-9]{6}$")) {
            player.sendMessage("§cInvalid verification code format. Code must be 6 characters.");
            return true;
        }
        
        player.sendMessage("§eVerifying your account...");
        verifyPlayer(player, code);
        
        return true;
    }
}
```

## Configuration

### Website Configuration

Add these values to your `.env` file:

```env
# Minecraft Server RCON Configuration
MINECRAFT_RCON_HOST=127.0.0.1
MINECRAFT_RCON_PORT=25575
MINECRAFT_RCON_PASSWORD=your-rcon-password

# Minecraft Verification Token (shared with plugin)
MINECRAFT_VERIFICATION_TOKEN=your-secure-random-token-here
```

### Minecraft Server Configuration

Add to your `server.properties`:

```properties
enable-rcon=true
rcon.port=25575
rcon.password=your-rcon-password
```

### Application Settings

Configure in `config/lighthouse.php`:

```php
'max_minecraft_accounts' => env('MAX_MINECRAFT_ACCOUNTS', 2),
'minecraft_verification_grace_period_minutes' => env('MINECRAFT_VERIFICATION_GRACE_PERIOD_MINUTES', 30),
'minecraft_verification_rate_limit_per_hour' => env('MINECRAFT_VERIFICATION_RATE_LIMIT_PER_HOUR', 10),
```

## Security Considerations

1. **Always use HTTPS** in production to protect the server token
2. **Keep the server token secret** - never expose it in client-side code or logs
3. **Rotate the server token periodically** (recommended: every 90 days)
4. **Monitor verification attempts** for suspicious patterns
5. **Implement IP whitelisting** if your server has a static IP
6. **Use environment variables** for all sensitive configuration
7. **Enable rate limiting** to prevent abuse (already configured: 30 req/min)

## Testing

### Test Verification Flow

1. Generate a verification code on the website
2. Use this curl command to simulate the plugin:

```bash
curl -X POST http://localhost/api/minecraft/verify \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "server_token": "your-token-here",
    "code": "ABC123",
    "minecraft_username": "TestPlayer",
    "minecraft_uuid": "00000000-0000-0000-0000-000000000000"
  }'
```

### Test Rate Limiting

Run the above curl command 31 times rapidly to trigger rate limiting:

```bash
for i in {1..31}; do
  curl -X POST http://localhost/api/minecraft/verify \
    -H "Content-Type: application/json" \
    -d '{"server_token":"test","code":"TEST12","minecraft_username":"Test","minecraft_uuid":"00000000-0000-0000-0000-000000000000"}'
  echo ""
done
```

## Troubleshooting

### "Invalid server token" Error

- Verify the token in your `.env` file matches the token in your plugin config
- Check for extra whitespace or line breaks in the token
- Ensure the token is properly quoted in your plugin configuration

### "Verification code not found" Error

- Code may have already been used
- Code may have expired (30-minute grace period by default)
- User may have entered the wrong code
- Check the `minecraft_verifications` table for the verification status

### "This Minecraft account is already linked" Error

- The UUID is already associated with another website account
- User must unlink from the other account first
- Check the `minecraft_accounts` table for existing links

### Rate Limiting Issues

- Default limit is 30 requests per minute per IP
- If legitimate traffic is being blocked, adjust the throttle in `routes/web.php`
- Monitor with: `tail -f storage/logs/laravel.log | grep "throttle"`

### RCON Connection Failures

- Verify RCON is enabled in `server.properties`
- Check firewall rules allow connections to the RCON port
- Ensure the RCON password matches between `.env` and `server.properties`
- Test RCON manually: `telnet localhost 25575`

## Support

For issues or questions:

1. Check the application logs: `storage/logs/laravel.log`
2. Review command logs in the database: `minecraft_command_logs` table
3. Enable debug mode temporarily: `APP_DEBUG=true` (production: use with caution)
4. Check queue worker status: `php artisan queue:work --verbose`

## Changelog

### Version 1.0 (2026-02-17)
- Initial API implementation
- Support for both Java and Bedrock accounts
- 30 requests/minute rate limiting
- Shared token authentication
- Comprehensive error handling
