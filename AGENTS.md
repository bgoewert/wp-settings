# AGENTS.md

## Purpose
This document provides AI coding assistants with essential project context, standards, and workflows to ensure consistent, high-quality contributions.

## Project Overview
**Project Name:** WordPress Settings Library
**Description:** Simple, reusable WordPress settings library with support for collapsible field groups.
**Tech Stack:** WordPress, PHP, Composer
**Primary Language(s):** PHP

## Development Workflow

### Git Branching Model (TBD)
We follow the **Trunk-Based Development (TBD)** model:

- **Main Branch (`main`)**: Production-ready code. All commits must be deployable.
- **Feature Branches**: Branches off `main` for new features or fixes
  - Naming convention: `feature/description`, `fix/description`, `chore/description`
  - Merge directly to `main` after testing
  - Branches remain active as long as needed
- **Small Team Workflow**: Direct commits to `main` acceptable for minor changes; use feature branches for larger work

### Git Worktree for Parallel Work
When multiple agent sessions or developers need to work on different branches simultaneously on the same machine, use `git worktree`:

```bash
# Create a new worktree for a feature branch
git worktree add ../project-feature-name feature/feature-name

# Or create worktree and new branch in one command
git worktree add -b feature/new-feature ../project-new-feature

# List all worktrees
git worktree list

# Remove a worktree when done
git worktree remove ../project-feature-name
```

**Benefits:**
- Work on multiple branches without switching contexts or stashing changes
- Each worktree has its own working directory but shares the same `.git` repository
- Avoid conflicts when multiple AI agents or developers work simultaneously
- Test different features side-by-side

### Commit Guidelines
- Use clear, descriptive commit messages following conventional commits format:
  - `feat: add user authentication` (becomes "Added:" in changelog)
  - `fix: resolve database connection timeout` (becomes "Fixed:" in changelog)
  - `refactor: simplify validation logic` (becomes "Changed:" in changelog)
  - `docs: update API documentation` (not typically included in changelog)
- For breaking changes, append `!` after any commit type:
  - `feat!: remove deprecated API` (becomes "**Breaking:** Remove..." in changelog)
  - `refactor!: change function signature` (becomes "**Breaking:** Change..." in changelog)
- Keep commits atomic and focused on a single concern
- Reference issue numbers when applicable: `fix: resolve #123`
- Use imperative mood (e.g., "add" not "adds" or "added")

### CRITICAL: Git Safety for AI Agents

**⚠️ NEVER use these commands without explicit user approval:**
- `git reset --hard` - **DESTROYS uncommitted work** - FORBIDDEN without user confirmation
- `git clean -fd` - Deletes untracked files - FORBIDDEN without user confirmation
- `git checkout -- .` - Discards changes - FORBIDDEN without user confirmation

**✅ REQUIRED workflow when making changes:**
1. Make changes incrementally (don't try to do everything at once)
2. **COMMIT AFTER EACH LOGICAL UNIT OF WORK** - Do not batch multiple changes into one commit
3. Test after each commit
4. If something breaks, you can safely reset to last commit
5. If you need to try different approaches, use `git stash` or create a temporary branch

**Example of CORRECT workflow:**
```bash
# 1. Rename files
git mv old.py new.py
git commit -m "refactor: Rename old.py to new.py"

# 2. Update code
# ... make changes to new.py ...
git add new.py
git commit -m "feat: Add new functionality to new.py"

# 3. Update docs
# ... make changes to README.md ...
git add README.md
git commit -m "docs: Update README with new script name"
```

**Example of INCORRECT workflow (causes data loss):**
```bash
# DO NOT DO THIS!
git mv old.py new.py
# ... make extensive changes to multiple files ...
# ... encounter an issue ...
git reset --hard HEAD  # ❌ DESTROYS ALL UNCOMMITTED WORK!
```

**If you need to experiment or try different approaches:**
- Create a feature branch: `git checkout -b feature/experiment`
- Or use git stash: `git stash push -m "WIP: trying different approach"`
- Later retrieve: `git stash pop`

**Commit frequency:**
- Commit EARLY and commit OFTEN
- Each commit should be one logical change
- Better to have 10 small commits than 1 giant commit
- If you're editing 3+ files for different purposes, that's 3 commits minimum

## Documentation Standards

### README.md
Maintain a comprehensive README.md with the following sections:

- **Project Overview**: Brief description and purpose
- **Prerequisites**: Required software, versions, and system requirements
- **Installation**: Step-by-step setup instructions
- **Configuration**: Environment variables and configuration options
- **Usage**: How to run the project locally and in production
- **Architecture**: High-level system architecture (if applicable)

Update README.md whenever:
- Adding new features that affect setup or usage
- Changing prerequisites or dependencies
- Modifying configuration requirements
- Adding new commands or scripts

### CONTRIBUTING.md
Create a CONTRIBUTING.md file with guidelines for contributors:

- **Code of Conduct**: Expected behavior and community standards
- **How to Contribute**: Steps for submitting contributions
- **Branch Naming**: Feature branch conventions
- **Commit Message Format**: Conventional commits format and examples
- **Pull Request Process**: If applicable for the project
- **Testing Requirements**: How to run tests and coverage expectations
- **Code Review Process**: What to expect during review

### LICENSE
Include a LICENSE file in the project root:

- Choose an appropriate license for your project (MIT, Apache 2.0, GPL, proprietary, etc.)
- Include full license text in the LICENSE file
- Reference the license in README.md

### CHANGELOG.md
We maintain a changelog following [Common Changelog](https://common-changelog.org/#2-format) format.

**Categories (in order):**
1. **Changed**: Changes to existing functionality
2. **Added**: New features
3. **Removed**: Removed features
4. **Fixed**: Bug fixes

**Prefixes:**
- **Breaking:** for breaking changes (used in major version updates)

**Process:**
- Changelog is generated from git commit history when releasing versions
- Commit messages should use conventional commit prefixes (see Commit Guidelines above)
- Use imperative mood with present-tense verbs (e.g., "Add feature" not "Added feature")
- Keep each change to a single line for easy scanning
- Use semantic versioning (MAJOR.MINOR.PATCH) as documented at [semver.org](https://semver.org)
- Release format: `## [X.Y.Z] - YYYY-MM-DD`
- Each release must include category headings like `### Added`/`### Fixed` to satisfy Common Changelog and the GitHub release action parser
- Breaking changes use `**Breaking:**` prefix in bold

### Code Documentation
- Document all public APIs, functions, and complex logic
- Keep inline comments clear and purposeful
- Use JSDoc, PHPDoc, docstrings, or language-appropriate documentation formats
- Update documentation when adding features or changing functionality

## Code Standards

### Style Guide
- **Linter/Formatter:** PHP_CodeSniffer with WordPress Coding Standards (if configured)
- **Configuration:** See `phpcs.xml` or `composer.json` for coding standards
- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

### Testing Requirements
- Run tests before committing: `composer test` or `./vendor/bin/phpunit` (if configured)
- Test in WordPress environment when possible

## Security Standards

All code must follow OWASP security principles and guidelines. Review these resources during development:

### Core Security References
- **[OWASP Top 10](https://owasp.org/www-project-top-ten/)**: Critical security risks to web applications
- **[OWASP Proactive Controls](https://top10proactive.owasp.org/)**: Essential security techniques for developers
- **[Secure Product Design](https://cheatsheetseries.owasp.org/cheatsheets/Secure_Product_Design_Cheat_Sheet.html)**: Design principles including least privilege and defense-in-depth
- **[Infrastructure as Code Security](https://cheatsheetseries.owasp.org/cheatsheets/Infrastructure_as_Code_Security_Cheat_Sheet.html)**: Security practices for IaC
- **[Error Handling](https://cheatsheetseries.owasp.org/cheatsheets/Error_Handling_Cheat_Sheet.html)**: Secure error handling practices
- **[Secure Coding Practices](https://devguide.owasp.org/en/12-appendices/01-implementation-dos-donts/02-secure-coding)**: Implementation dos and don'ts
- **[Cryptographic Practices](https://devguide.owasp.org/en/12-appendices/01-implementation-dos-donts/03-cryptographic-practices/)**: Proper use of cryptography

### Security Practices

**Dependency Management:**
- Check for package vulnerabilities regularly using tools appropriate for your stack:
  - **Node.js**: `npm audit`, `yarn audit`, or Snyk
  - **PHP**: `composer audit` (Composer 2.4+) or roave/security-advisories
  - **Python**: `pip-audit`, Safety, or Snyk
  - **Java**: OWASP Dependency-Check, Snyk
  - **.NET**: `dotnet list package --vulnerable`
- Address critical and high-severity vulnerabilities immediately
- Keep dependencies up to date with security patches
- Review dependency licenses and maintenance status

**Code Security:**
- Apply principle of least privilege for all access controls
- Implement defense-in-depth with multiple security layers
- Validate and sanitize all input data
- Use parameterized queries to prevent SQL injection
- Implement proper authentication and authorization
- Never hardcode secrets, credentials, or API keys
- Use environment variables or secure secret management systems
- Handle errors securely without exposing sensitive information
- Log security events appropriately (see OWASP Error Handling guide)

**Infrastructure & Deployment:**
- Use secure defaults for all configurations
- Encrypt data in transit (HTTPS/TLS) and at rest
- Implement proper session management
- Configure security headers (CSP, HSTS, X-Frame-Options, etc.)
- Disable unnecessary services and features
- Keep all systems and containers updated with security patches
- Scan container images for vulnerabilities before deployment
- Implement rate limiting and DDoS protection where appropriate

**Development Process:**
- Perform threat modeling for new features and architecture changes
- Conduct security reviews for code handling authentication, authorization, or sensitive data
- Use static analysis security testing (SAST) tools in your development workflow
- Test for OWASP Top 10 vulnerabilities
- Never commit sensitive data to version control
- Use `.gitignore` to exclude secrets, credentials, and environment files

## Environment Setup

### Installation
```bash
composer require bgoewert/wp-settings
```

### Usage
Include in your WordPress plugin or theme:
```php
use BGoewert\WP_Settings\WP_Settings;
use BGoewert\WP_Settings\WP_Setting;
```

### Development Setup
```bash
git clone [repository-url]
cd wp-settings
composer install
```

## AI Assistant Guidelines

When working on this project, AI assistants should follow these practices:

**Testing & Validation:**
- Build and run all tests to catch errors before committing
- Ensure automated testing covers security best practices
- Run the project locally to identify runtime errors
- Test integration points when changes affect multiple components
- If you cannot test locally, clearly document what needs to be tested

**Development Approach:**
- Ask for clarification when requirements are ambiguous or incomplete
- Use standardized project structure and naming conventions
- Follow semantic versioning (MAJOR.MINOR.PATCH) as documented at [semver.org](https://semver.org)
- If uncommitted changes become large (500+ lines or multiple files), ask about breaking into smaller, atomic commits

**Code Quality:**
- Write clear, self-documenting code with meaningful variable and function names
- Add comments only when the "why" isn't obvious from the code itself
- Ensure code is consistent with existing project patterns and style

## Project Constraints & Standards

**Browser/Platform Compatibility:**
- Support all modern, evergreen browsers (Chrome, Firefox, Safari, Edge)
- Reference [Can I Use](https://caniuse.com/usage-table) for browser usage statistics and feature support
- Specify any legacy browser requirements in project-specific documentation

**Accessibility:**
- Adhere to WCAG 2.1 Level AA standards for web applications where applicable
- Use semantic HTML, proper ARIA labels, and keyboard navigation
- Test with screen readers when implementing UI components

**Performance & Scalability:**
- [Document project-specific performance requirements and SLAs here]
- [Document known technical debt here, or reference separate technical debt log]

**Database Migrations:**
- [Document database migration workflow - to be determined during project initialization]

## Decision Documentation

**Architecture Decision Records (ADR):**
For significant architectural decisions, consider documenting them using ADRs following the format at [Architecture Decision Record](https://github.com/joelparkerhenderson/architecture-decision-record). This is recommended for:
- Major technology choices (frameworks, databases, services)
- Significant changes to system architecture or design patterns
- Security-related decisions
- Integration approaches with external systems

ADRs are optional and most valuable for medium-to-large projects or teams. Store ADRs in an `adr/` directory with descriptive names like `choose-database.md` or `implement-authentication.md`.

**Technical Debt:**
- [Document how technical debt is tracked - project-specific]
- [Options: GitHub Issues, Jira tickets, TECH_DEBT.md file, or comment tags in code]

**Task Management:**
- Reference issue/ticket numbers in commit messages when available (e.g., `fix: resolve login timeout (#123)`)
- If no project management software is in use:
  - Maintain a `TODO.md` file with checkboxes, priorities, and tags
  - Format: `- [ ] **[Priority]** Description (tags: #tag1, #tag2)`
  - Example: `- [ ] **[High]** Add input validation to contact form (tags: #security, #frontend)`

## Resources
- **Repository:** See git remote for URL
- **Documentation:** See README.md
- **WordPress Settings API:** https://developer.wordpress.org/plugins/settings/settings-api/

## Contact
- **Maintainer:** See composer.json or git log for author information
