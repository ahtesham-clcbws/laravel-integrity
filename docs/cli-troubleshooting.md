# CLI Reference & Troubleshooting

This guide details common command configurations, exit codes, and database isolation troubleshooting.

---

## 1. Options & Flags

### `--full` (Include Expensive Checks)
- **Behavior**: Runs database mapping, route serialization caching, and Blade compile sandbox tests.
- **When to use**: During pre-deployment hooks or post-commit checks.
- **Default**: Excluded from default runs to optimize local speed.

### `--dirty` (Staged Scoping)
- **Behavior**: Scans only files currently modified or staged in Git.
- **Scope**: App and view paths only.

### `--strict` (CI/CD Gates)
- **Behavior**: Commands return a non-zero exit code (`Command::FAILURE`) if any check fails, halting pipelines.

---

## 2. Troubleshooting Database Connection Issues

Several checks (`pending-migration`, `model-table-mapping`, `policy-mapping`, `event-listener-mapping`) require active DB and Laravel container bindings:

### Symptom: `Database connection failed during pending migration check`
- **Root Cause**: The audit is running in a container or CI pipeline where `.env` database parameters are offline.
- **Resolution**:
  1. In static environment pipelines, omit the `--full` option.
  2. For deployment pipelines, ensure DB configuration variables are active in the build container.

---

## 3. Resolving Syntax Compile Failures

### Symptom: `Syntax error inside compiled Blade template`
- **Root Cause**: The Blade token compiler successfully parsed structural markup, but the output PHP code failed standard PHP lint evaluation.
- **Resolution**: Search the referenced file for unclosed PHP blocks (e.g., mismatching `@if` / `@endif` blocks or unclosed parentheses inside directives like `@can` or `@include`).
