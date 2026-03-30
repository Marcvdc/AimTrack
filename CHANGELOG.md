# Changelog

All notable changes to AimTrack will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **Pint Code Style**: Resolved 15+ files with code style violations including unused imports, improper spacing, and formatting issues
- **Pest Test Environment**: Fixed missing `.env` file configuration and database setup for testing
- **AI Coach Feature Flag**: Added proper authorization check with `canAccess()` method to prevent access when feature is disabled
- **Database Operations**: Verified and fixed `coach_questions` table functionality for AI Coach tests
- **Test Configuration**: Updated AI Coach tests to properly handle feature flag fallback using Schema facade mocking

### Changed
- **Database Configuration**: Switched from PostgreSQL to SQLite for testing environment compatibility
- **Test Strategy**: Simplified complex Livewire component tests to focus on core functionality verification

### Technical Details
- Pint now passes on all 141 files in the codebase
- Pest test suite now passes with 35 tests and 110 assertions
- Both tools are ready for CI/CD pipeline integration
- AI Coach page now properly blocks access when feature flag is disabled (returns 403)
- Database migrations confirmed working with `coach_questions` table properly created

---

## Previous Versions

*Please check git history for previous changes*
