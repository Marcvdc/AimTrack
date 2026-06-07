# Docker Permissie Probleem Oplossing - AimTrack

## Probleem Beschrijving
De Docker containers hebben permissieproblemen met storage directories (logs, sessions, cache). Dit veroorzaakt "Permission denied" fouten bij het schrijven naar bestanden.

## Root Cause
- Container draait als user ID 1000 maar PHP-FPM draait als www-data
- Storage bestanden hebben soms verkeerde ownership/permissies
- Monolog probeert chmod() uit te voeren wat niet is toegestaan

## Permanente Oplossing

### 1. Dockerfile Aanpassingen
Zorg dat PHP-FPM draait als dezelfde user als de container:

```dockerfile
# Voeg toe aan Dockerfile na base image
COPY docker/php/www-data.conf $PHP_INI_DIR/../php-fpm.d/www-data.conf
```

### 2. PHP-FPM Configuratie
Maak `docker/php/www-data.conf`:

```ini
[www]
user = 1000
group = 1000
listen.owner = 1000
listen.group = 1000
```

### 3. Entrypoint Script
Update `docker/entrypoint.sh`:

```bash
#!/usr/bin/env sh
set -euo pipefail

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Fix storage permissions for container user
chown -R $(id -u):$(id -g) storage/ bootstrap/cache/
chmod 777 storage/logs/ storage/framework/sessions/

# Rest van existing script...
```

### 4. Logging Configuratie
Verwijder `permission` settings uit `config/logging.php` om chmod() errors te voorkomen:

```php
'single' => [
    'driver' => 'single',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'replace_placeholders' => true,
],

'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => env('LOG_DAILY_DAYS', 14),
    'replace_placeholders' => true,
],
```

### 5. Docker Compose Environment
Zorg dat UID/GID correct zijn ingesteld:

```yaml
services:
  app:
    user: "${UID}:${GID}"
    # ... rest van configuratie
```

`.env` file moet bevatten:
```
UID=1000
GID=1000
```

### 6. Implementatie Stappen

1. **Stop containers**: `docker-compose down`
2. **Fix lokale permissies**: `sudo chown -R 1000:1000 storage/`
3. **Bouw containers**: `docker-compose build --no-cache app queue`
4. **Start containers**: `docker-compose up -d`
5. **Test permissies**: 
   ```bash
   docker exec aimtrack_app php -r "file_put_contents('storage/logs/test', 'test'); echo 'OK';"
   docker exec aimtrack_app php -r "file_put_contents('storage/framework/sessions/test', 'test'); echo 'OK';"
   ```

### 7. Database Permissies
Indien nodig database user permissies fixen:

```bash
docker exec aimtrack_db psql -U postgres -d aimtrack -c "
ALTER USER aimtrack_app WITH PASSWORD 'change-me';
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO aimtrack_app;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO aimtrack_app;
GRANT USAGE ON SCHEMA public TO aimtrack_app;
"
```

### 8. Verificatie
Test na implementatie:
- Logging werkt zonder errors
- Sessies kunnen worden geschreven
- Database connectie werkt
- Livewire components functioneren

## Belangrijke Notities
- Gebruik `777` permissies voor logging/sessions directories in development
- Vermijd `permission` settings in logging configuratie
- Zorg dat entrypoint script permissies fixt bij elke start
- Test na elke container rebuild

## Troubleshooting
Als problemen aanhouden:
1. Controleer user ID: `docker exec aimtrack_app id`
2. Controleer permissies: `docker exec aimtrack_app ls -la storage/`
3. Forceer permissie fix: `sudo chmod -R 777 storage/logs/ storage/framework/sessions/`
4. Rebuild containers met `--no-cache`
