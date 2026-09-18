
## Purpose
Lets a consumer place the settings page under any admin menu and gate it on any capability without reimplementing registration, because the library's own registration is what keeps the asset enqueue and the screen checks alive.

## ADDED Requirements

### Requirement: The page's parent and capability are configurable
The library SHALL register the settings page under a configurable parent menu and a configurable capability, supplied as part of the plugin data. Absent either, it SHALL default to the platform's Settings menu and the site-options capability. A value that is not a non-empty string SHALL be ignored in favor of the default. The configured capability SHALL gate the page render as well as the menu entry.

#### Scenario: A consumer places the page under a post type's menu
- **WHEN** the plugin data names a parent menu and a capability
- **THEN** the page is registered under that menu, requires that capability, and the library's own registration — including the page hook and the load action — is what performed it

#### Scenario: A consumer supplies neither
- **WHEN** the plugin data names no parent and no capability
- **THEN** the page is registered under Settings and requires the site-options capability

#### Scenario: A supplied value is empty
- **WHEN** the plugin data carries an empty or non-string parent or capability
- **THEN** the default is used, rather than registering a page nobody can reach

#### Scenario: The slug is already registered under the configured parent
- **WHEN** a submenu with the same slug already exists under that parent
- **THEN** registration is skipped, as it is under the default parent

### Requirement: A page registered outside the library is reported
The library SHALL report, once per request through the platform's developer-misuse channel, that its page hook is empty when the admin enqueue runs, naming the loss of the stylesheet, the scripts and the screen checks. It SHALL NOT report when it declined to register because the slug was already taken.

#### Scenario: A consumer overrides the registration
- **WHEN** a consumer registers the page itself and the admin enqueue runs
- **THEN** a developer-misuse notice names the page and what is silently disabled

#### Scenario: The library registered the page
- **WHEN** registration went through the library
- **THEN** nothing is reported

#### Scenario: Registration was skipped as a duplicate
- **WHEN** the library declined to register because the slug already existed
- **THEN** nothing is reported, because the page belongs to the copy that registered it
