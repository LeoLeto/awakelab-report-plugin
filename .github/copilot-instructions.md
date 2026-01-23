# GitHub Copilot Instructions for Report QuestionBank Plugin

## Version Management

**CRITICAL**: Every time you make ANY change to the codebase, you MUST update the version information in `version.php`:

1. **Increment the version number** (`$plugin->version`):
   - Format: `YYYYMMDDXX` where XX is a counter for the day
   - Example: `2025122200` → `2025122201` (for same day changes)
   - Or use current date: `2026012300` (for new day)

2. **Update the release version** (`$plugin->release`):
   - Follow semantic versioning: `v{MAJOR}.{MINOR}.{PATCH}`
   - For bug fixes: increment PATCH (e.g., `v1.0` → `v1.0.1`)
   - For new features: increment MINOR (e.g., `v1.0.1` → `v1.1.0`)
   - For breaking changes: increment MAJOR (e.g., `v1.1.0` → `v2.0.0`)

## Example version.php Update

```php
$plugin->version   = 2026012300;        // ALWAYS UPDATE THIS
$plugin->requires  = 2022041900;
$plugin->component = 'report_questionbank';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = 'v1.0.1';          // ALWAYS UPDATE THIS
```

## Rules

- **NEVER** make a code change without updating `version.php`
- **ALWAYS** mention the version update in your response to the user
- **ALWAYS** use the current date for the version number
- **ALWAYS** follow semantic versioning for the release string

## Additional Guidelines

- Test changes before committing
- Document significant changes in comments
- Maintain backward compatibility when possible
- Follow Moodle coding standards
