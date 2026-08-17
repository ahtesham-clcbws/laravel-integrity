# Changelog

All notable changes to **Laravel Integrity** will be documented in this file.

## [v1.2.0] - Upcoming
### Added
- **HTML Reports**: Added  to generate standalone HTML compliance reports.
- **N+1 Query Detection**: Added  to statically detect database queries and lazy loading inside loops.
- **Blade Strict Types**: Added  to verify that required  are passed to Blade components.
- **Automated PR Reviewer**: Added  to automatically post AST analysis results directly to GitHub PRs as inline comments.

## [v1.1.0] - 2024-10-24
### Added
- **AI Agent Integration (MCP Server)**: Added `php artisan integrity:mcp` command to expose local reflection tools to AI Agents like Cursor via Model Context Protocol.
- **Security Audits**: Added `ModelMassAssignmentCheck` to statically detect models using `protected $guarded = [];`.
- **Database Hygiene**: Added `DatabaseIndexCheck` to scan the actual database schema via DBAL and flag missing indexes on foreign key columns (e.g., `*_id`).
- **Dead Code Elimination**: Added `UnreferencedPrivateMethodCheck` to detect `private` methods that are never called locally (`$this->method()`) within their class.
- **Env Variable Contracts**: Added `MissingEnvVariableCheck` to ensure all `env()` calls exist within `.env.example`.

## [v1.0.0] - Initial Release
### Added
- Initial stable release.
- Added comprehensive AST parsing via `nikic/php-parser`.
- Added baseline generation (`.integrity-baseline.json`).
- Added extensible `Check` contract pipeline.
- Fully documented Getting Started, Configuration, and Extensibility guides.
- Expanded PHP version constraint to `>=8.2` for full PHP 8.4 & 8.5 compatibility.
