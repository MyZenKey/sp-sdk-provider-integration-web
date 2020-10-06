# Changelog
All notable changes to this project will be documented in this file.

## Versions
- [2020-09-06](#2020-09-06)
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

## 2020-09-06
### Changed
- Updated documentation links in the Readme file
### Fixed
- Fixed bug where the "context" param in the auth request was being double-URL encoded

## 2020-06-25
### Changed
- Switched to the v2 userInfo API response format
- Consolidated Authorization Flow session functionality in the SessionService
### Security
- Sanitized inputs, including GET and POST parameters, using the Validator library
- Upgraded the Helmet library that provides security headers
- Added a note explaining why cookie session storage is not very secure

## 2020-04-14
### Added
- PKCE verification
### Security
- Bumped ESLint package version to mitigate security vulnerabilities in dependencies

## 2020-03-27
### Changed
- Updated support email address in Readme
- Commit package-lock.json
- Send a nonce in the authorization request and verify it in the ID token. This helps prevent replay attacks

## 2020-02-27
### Added
- Added a changelog file
### Changed
- Updated copyright date across all files
- Added missing copyright headers across the project

## 2020-01-31
### Added
- First public release
