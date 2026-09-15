## Purpose

Makes a second unscoped copy of the library on the same site visible to the site owner, because the shared static text domain silently gives one consumer's settings to another.

## ADDED Requirements

### Requirement: More than one unscoped copy is reported
The library SHALL detect when the library's namespace is registered from more than one directory on the same site, and SHALL report it once per request through the platform's developer-misuse channel. The report SHALL name the directory and version of the copy actually in use, and of every copy that is not. It SHALL NOT alter which copy is used, and SHALL NOT stop the request.

#### Scenario: Two plugins vendor the library unscoped
- **WHEN** two directories register the library's namespace and the settings page initializes
- **THEN** a developer-misuse notice names the copy in use and the copy that lost, each with its directory and version

#### Scenario: Only one copy is present
- **WHEN** exactly one directory registers the namespace
- **THEN** nothing is reported

#### Scenario: A consumer scoped its copy
- **WHEN** a second consumer's copy is published under a different namespace
- **THEN** it is not a duplicate, and nothing is reported

#### Scenario: A version cannot be determined
- **WHEN** a copy's version is not readable
- **THEN** the notice still names the directory, reporting the version as unknown

#### Scenario: The same copy is registered twice
- **WHEN** one directory is registered by more than one autoloader
- **THEN** nothing is reported, because there is only one copy
