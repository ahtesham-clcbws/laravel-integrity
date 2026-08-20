# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-08-20

### Added
- Restored and modernized 6 legacy checks to properly implement the new `CheckInterface`:
  - `MissingEnvVariableCheck`
  - `DatabaseIndexCheck`
  - `NPlusOneStaticCheck`
  - `UnreferencedPrivateMethodCheck`
  - `BladeComponentStrictTypeCheck`
  - `ModelMassAssignmentCheck` (Moved to Security namespace)
- Configuration-driven exclusions for `OrphanedComponentCheck` to prevent false positives from built-in directives (`slot`, `dynamic-component`) and 3rd-party UI libraries like Mary UI (`mary-`).

### Fixed
- Fixed fatal errors occurring during AST parsing (`namespacedName` property access) by ensuring `NameResolver` automatically runs at the beginning of the `AstParserEngine` pipeline. This permanently fixes issues with `php-parser` v5 compatibility.
- Fixed `LivewireComponentMapper` and `ContainerReflectionEngine` crashing due to AST resolution failures.
- Fixed `ComponentRegistryBridge` to correctly resolve Livewire v3 components using `\Livewire\Mechanisms\ComponentRegistry` instead of the removed `Livewire::getClass()` facade.
- Fixed `SeederClassExistsCheck` using the wrong AST hook (`leaveNode` vs `enterNode`), causing unresolved FQNs during validation.
- Fixed an issue in `IntegrityAuditCommand` where the `--format=json` option was silently overwritten during formatting selection by implementing a proper `match` expression.
