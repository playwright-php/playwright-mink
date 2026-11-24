# CHANGELOG

## [Unreleased]

### Added
- `executablePath` launch option (#10)
- `channel` launch option (#9)

### Fixed
- `visit()` shows the 401 page instead of failing with `net::ERR_INVALID_AUTH_CREDENTIALS` on full Chromium (`channel`, `executablePath` or headed) (#13)
