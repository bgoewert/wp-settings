# Contributing

Thanks for your interest in contributing. This guide explains how to get set up, follow project conventions, and submit changes.

## How to Contribute
1. Fork the repo and create a feature branch from `main`.
2. Make focused, incremental changes.
3. Add or update tests when behavior changes.
4. Run tests locally.
5. Open a pull request with a clear description of the change and any testing notes.

## Branch Naming
Use one of the following prefixes, and optionally include an issue number:
- `feature/description`
- `fix/description`
- `chore/description`

Examples with issue numbers:
- `feature/123-add-setting-group`
- `fix/456-handle-null-values`

## Commit Message Format
Use Conventional Commits (imperative mood):
- `feat: add new option type`
- `fix: handle missing field value`
- `refactor: simplify validation flow`
- `docs: update usage examples`

For breaking changes, add `!` after any type:
- `feat!: change setting registration signature`
- `fix!: remove deprecated behavior`
- `refactor!: change public method signature`

Include issue numbers when applicable: `fix: resolve #123`.

## Pull Request Process
- Keep PRs small and focused.
- Describe the why and what, not just the diff.
- Include test results and any manual verification steps.
- Expect at least one review before merging.

## Testing Requirements
Install dependencies, then run the test suite:

```bash
composer install
composer test
```

You can also run PHPUnit directly:

```bash
./vendor/bin/phpunit --testdox
```

## Code Review Process
Reviews focus on correctness, security, and WordPress coding standards. Be ready to adjust code or tests based on feedback.
