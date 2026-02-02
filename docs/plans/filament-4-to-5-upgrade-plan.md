---
description: Filament 4→5 Upgrade Plan voor AimTrack
---

# Filament 4→5 Upgrade Plan

## Status: PLAN-FIRST Phase

**Issue:** [GitHub #61](https://github.com/Marcvdc/AimTrack/issues/61)
**Current Version:** Filament 4.6.0
**Target Version:** Filament 5.x
**Timeline:** 1-2 weken
**Complexiteit:** COMPLEX

## Huidige Situatie

### AimStack Filament Componenten
- **Pages:** AiCoachPage, ExportSessionsPage
- **Resources:** AmmoTypeResource, AttachmentResource, LocationResource, SessionResource, WeaponResource
- **Widgets:** FailedJobsWidget
- **Relation Managers:** SessionWeaponsRelationManager
- **Dependencies:** Livewire 3.7.6, Laravel 12.48.1

### Geïdentificeerde Breaking Changes (vanuit docs)
- Forms components → Schemas components namespace changes
- Automated upgrade script beschikbaar
- Directory structure migratie (optioneel)

## Upgrade Stappenplan

### Phase 1: Voorbereiding
1. **Backup maken**
   ```bash
   # Database backup
   docker-compose exec db pg_dump aimtrack > backup_$(date +%Y%m%d).sql
   
   # Code backup
   git checkout -b feature/filament-5-upgrade
   ```

2. **Dependencies Checken**
   - Check of alle plugins Filament 5 compatible zijn
   - `livewire/livewire` upgrade nodig naar 3.8+
   - `laravel/prompts` upgrade nodig

### Phase 2: Automated Upgrade
1. **Install upgrade script**
   ```bash
   composer require filament/upgrade:"^4.0" -W --dev
   ```

2. **Run upgrade script**
   ```bash
   vendor/bin/filament-v4
   ```

3. **Apply changes**
   ```bash
   # Commands output door upgrade script (uniek per app)
   composer require filament/filament:"^5.0" -W --no-update
   composer update
   ```

4. **Directory structure migratie (optioneel)**
   ```bash
   # Dry-run eerst
   php artisan filament:upgrade-directory-structure-to-v4 --dry-run
   
   # Als goed: apply
   php artisan filament:upgrade-directory-structure-to-v4
   ```

5. **Cleanup**
   ```bash
   composer remove filament/upgrade --dev
   ```

### Phase 3: Manual Fixes
1. **Namespace updates**
   - `Filament\Forms\Components\Section` → `Filament\Schemas\Components\Section`
   - `Filament\Forms\Components\Grid` → `Filament\Schemas\Components\Grid`
   - `Filament\Forms\Components\Actions` → `Filament\Schemas\Components\Actions`

2. **Component specifieke fixes**
   - AiCoachPage (complexe forms met AI integratie)
   - SessionResource (tables met custom actions)
   - ExportSessionsPage

3. **Test imports en dependencies**
   - PHPStan voor broken references
   - `composer dump-autoload`

### Phase 4: Testing
1. **Unit Tests**
   ```bash
   php artisan test --testsuite=Unit
   ```

2. **Feature Tests**
   ```bash
   php artisan test --testsuite=Feature
   ```

3. **Manual Testing**
   - Pages functionaliteit
   - Resources CRUD operations
   - AI Coach functionaliteit
   - Export functionaliteit
   - Table actions en filters

4. **Integration Tests**
   - Livewire componenten
   - Form submissions
   - Database operations

### Phase 5: Documentation & Deployment
1. **Update documentation**
   - README.md versie nummers
   - Installation instructies
   - Upgrade notes

2. **Production deployment**
   - Staging deployment eerst
   - Rollback plan gereed
   - Monitoring na deployment

## Risico's & Mitigaties

### High Risk
- **AiCoachPage breaking changes** → Complex forms met AI integratie
  - *Mitigatie:* Grondige testing van AI functionaliteit
  
- **SessionResource table actions** → Custom actions mogelijk anders in v5
  - *Mitigatie:* Check breaking changes voor tables/actions

### Medium Risk
- **Plugin compatibility** → Sommige plugins mogelijk niet v5 ready
  - *Mitigatie:* Check voor upgrade, tijdelijk remove indien nodig
  
- **Livewire version conflicts** → Livewire 3.8+ nodig
  - *Mitigatie:* Upgrade Livewire parallel met Filament

### Low Risk
- **Directory structure** → Optioneel, kan oude structuur behouden
  - *Mitigatie:* Huidige structuur behouden indien complex

## Success Criteria

✅ Alle Filament Pages werken zonder errors  
✅ Alle Resources functionaliteit intact  
✅ AI Coach integratie werkt correct  
✅ Export functionaliteit behouden  
✅ Alle tests passeren  
✅ Geen PHP errors in logs  
✅ Performance niet verslechterd  

## Rollback Plan

Als upgrade faalt:
```bash
# Git rollback
git checkout main
git branch -D feature/filament-5-upgrade

# Database rollback (indien nodig)
docker-compose exec db psql -U postgres aimtrack < backup_YYYYMMDD.sql

# Composer rollback
composer update --prefer-stable --no-dev
```

## Next Steps

1. **Start met Phase 1** (Voorbereiding)
2. **Schedule maintenance window** voor deployment
3. **Communicatie** met team over upgrade timeline
4. **Begin automated upgrade process**

---

*Last updated: 2026-02-02*
*Status: Ready for execution*
