# Changelog
All notable changes to this project will be documented in this file.

## Versions
- [2020-06-25](#2020-06-25)
- [2020-04-14](#2020-04-14)
- [2020-03-27](#2020-03-27)
- [2020-02-27](#2020-02-27)
- [2020-01-31](#2020-01-31)

## Updating
When the Unreleased section becomes a new version, duplicate the Template to create a new Unreleased section.
```
## [Template]
### Added
- new features
### Changed
- changes in existing functionality
### Removed
- removed features
### Deprecated
- soon-to-be removed features
### Fixed
- bugfixes
### Security
- vulnerabilities or security notes
```

## Unreleased
none

## 2020-06-25
- Switched to the v2 userInfo API response format

## 2020-04-14
### Added
- PKCE verification

## 2020-03-27
### Changed
- Updated support email address in Readme
- Use pipenv and commit Pipfile and Pipfile.lock
- Send a nonce in the authorization request and verify it in the ID token. This helps prevent replay attacks

## 2020-02-27
### Added
- Added a changelog file
### Changed
- Updated copyright date across all files
- Added missing copyright headers across the project
### Fixed
- Updated incorrect input ID value in the HTML template
- Removed validation of Userinfo fields, as different mobile carriers return different formats
- Force the Userinfo request to use an HTTP Get, as some carriers don't support POST requests to this endpoint

## 2020-01-31
### Added
- First public release
