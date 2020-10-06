# Changelog
All notable changes to this project will be documented in this file.

## Versions
- [2020-09-06](#2020-09-06)
- [2020-08-06](#2020-08-06)
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

## 2020-08-06

### Fixed
- Fixed a PKCE bug where the code_verifier was not long enough to satisfy requirements

## 2020-06-25
### Changed
- Switched to the v2 userInfo API response format
### Security
- Sanitize inputs, including GET and POST parameters and ENV variables
- Remove unnecessary logging that may expose sensitive information
- Encode special characters in HTML output
- Use http_build_query instead of string concatenation when building URLs
- Use Curl instead of file_get_contents when making API requests, to avoid accessing the local filesystem
- Add X-Frame-Options headers to enhance security
- Add HSTS headers to enhance security

## 2020-04-14
### Added
- PKCE verification

## 2020-03-27
### Added
- Verify the ID token and access token
- Send a nonce in the authorization request and verify it in the ID token. This helps prevent replay attacks
### Changed
- Updated support email address in Readme
### Security
- Sanitized message query parameter to prevent XSS

## 2020-02-27
### Added
- Added a changelog file
- Added an example of a post-login ZenKey authorize flow
- Wrote additional PHPDocs 
- Added additional comments to explain the ZenKey authorize flow
### Changed
- Updated copyright date across all files
- Added missing copyright headers across the project
- Refactored the codebase to enable the post-login authorization flow and improve readabiliity
  - Added AuthorizeFlowHandler.php to handle the ZenKey post-login authorization flow
  - Added SessionService.php to handle caching data in the session
  - Added ZenKeyOIDCService.php to abstract the ZenKey OIDC calls
- Linting tweaks
### Fixed
- Clear the session state when an error occurs so that the "state" parameter doesn't get reused

## 2020-01-31
### Added
- First public release
