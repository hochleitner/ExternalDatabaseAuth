# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2019-04-12
### Fixed

- The tasks in postAuthentication() are now only performed if ExternalDatabaseAuth was the provider
  that actually performed the successful login.

## [0.1.0] - 2019-04-06
### Added
- Initial version inspired by [ExtAuthDB](https://www.mediawiki.org/wiki/Extension:ExtAuthDB).
- Modern MediaWiki extension approach using AuthManager as an AuthenticationProvider.
- Connection to an external MySQL/MariaDB database.
- Retrieval of user information from the external database based on the username that was entered.
- Password verification either using password_verify() (for bcrypt, argon2i or argon2id) or hash() for other hashing
  algorithms supported by PHP (such as sha256).
- Automatic local wiki user creation as facilitated by AuthManager upon successful authentication.
- No absolute failure on wrong credentials which allows for other providers to run.
- Adherence to MediaWiki's coding standards (testable via composer script).

[Unreleased]: https://github.com/hochleitner/ExternalDatabaseAuth/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/hochleitner/ExternalDatabaseAuth/releases/tag/v0.1.1
[0.1.0]: https://github.com/hochleitner/ExternalDatabaseAuth/releases/tag/v0.1.0
