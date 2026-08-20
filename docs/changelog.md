# Changelog

All notable changes to **Laravel Integrity** will be documented in this file.

## [v1.3.1] - 2026-08-20
### Fixed
- Synced `CHANGELOG.md` with docs.

## [v1.3.0] - 2026-08-20
### Added
- **Configuration-driven Exclusions**: Added `orphaned_component_exclusions` to config to prevent false positives from built-in directives (`slot`, `dynamic-component`) and 3rd-party UI libraries like Mary UI (`mary-`).

### Fixed
- **Legacy Checks Migration**: Restored and modernized 6 legacy checks (`MissingEnvVariableCheck`, `DatabaseIndexCheck`, `NPlusOneStaticCheck`, `UnreferencedPrivateMethodCheck`, `BladeComponentStrictTypeCheck`, `ModelMassAssignmentCheck`) to properly implement the new `CheckInterface` and `CheckResult` architecture.
- **AST Parser Fatal Errors**: Fixed fatal errors occurring during AST parsing (`namespacedName` property access) by ensuring `NameResolver` automatically runs at the beginning of the `AstParserEngine` pipeline.
- **Livewire v3 Resolution**: Fixed `ComponentRegistryBridge` to correctly resolve Livewire v3 components using `\Livewire\Mechanisms\ComponentRegistry` instead of the removed `Livewire::getClass()` facade.
- **Seeder Namespace Hook**: Fixed `SeederClassExistsCheck` using the wrong AST hook (`leaveNode` vs `enterNode`).
- **Formatter Assignment**: Fixed an issue in `IntegrityAuditCommand` where the `--format=json` option was silently overwritten.

## [v1.2.0] - 2026-08-17
### Added
- **HTML Reports**: Added `--format=html` to generate standalone HTML compliance reports.
- **N+1 Query Detection**: Added `NPlusOneStaticCheck` to statically detect database queries and lazy loading inside loops.
- **Blade Strict Types**: Added `BladeComponentStrictTypeCheck` to verify that required `@props` are passed to Blade components.
- **Automated PR Reviewer**: Added `php artisan integrity:pr-review` to automatically post AST analysis results directly to GitHub PRs as inline comments.

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
