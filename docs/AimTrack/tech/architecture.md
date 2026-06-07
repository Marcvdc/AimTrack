# Architecture Type
Type: C: Hybrid
Detected: 2026-01-07
Last Review: 2026-01-07

## Models Inventory
### Eloquent Models
- User (app/Models/User.php) - @eloquent
- Session (app/Models/Session.php) - @eloquent
- Shot (app/Models/Shot.php) - @eloquent

### API Models
- ExternalScore (app/Models/Api/ExternalScore.php) - @api

## Legacy Modules
- Admin Dashboard (app/Http/Controllers/Admin/*) - legacy, werkt, geen refactor nodig
- Reports (app/Services/ReportService.php) - legacy, performance OK

## Refactor Candidates
- [Nog invullen na analyse]

## Tooling & Integraties
- Laravel Boost (dev-only MCP server) voor AI-assisted development en IDE integraties (Windsurf). Geen productieconnecties toegestaan.
- Windsurf MCP-configuratie vereist WSL toegang. Gebruik `~/.codeium/windsurf/mcp_config.json` met onderstaande snippet zodat Boost consistent start vanuit de Linux-distro `Ubuntu`:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "wsl.exe",
            "args": [
                "-d",
                "Ubuntu",
                "-e",
                "bash",
                "-lc",
                "cd /home/brandnetel/CascadeProjects/AimTrack && /usr/bin/php8.4 artisan boost:mcp --verbose"
            ],
            "timeoutMs": 40000
        }
    }
}
```
