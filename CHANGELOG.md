# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 26 November 2026
### Added
- `tax:calculation:create` and `tax:calculation:autoapply` show the planned changes and ask for confirmation (only `y` or `yes` is accepted); `--dry-run` previews without saving, `--force` saves without asking
- `tax:calculation:autoapply` option `--exact` to match tax rate codes exactly instead of by keyword
- Changes to tax rules are logged to `var/log/yireo_tax_commands.log`; a failure to write this log no longer fails an already saved change

### Changed
- BREAKING: `tax:calculation:autoapply` requires `--rule_id` and `--keyword` instead of silently falling back to rule 1 and keyword `standard`
- BREAKING: `tax:calculation:create` and `tax:calculation:autoapply` merge tax classes into the existing rule instead of overwriting its customer and product tax classes
- Dropped PHP 8.1 support, added PHP 8.5 support
- Allow `symfony/console` v5, v6 or v7

### Fixed
- `tax:calculation:create` and `tax:rates:create` validate their input and report save errors instead of failing with an exception
- `tax:rates:create` limits the postcode to 10 characters like core and rejects region IDs that do not belong to the country
- `tax:rates:create` broke because its helper methods conflicted with Symfony `Command::getCode()`
- `tax:class:list` and the tax rate list now show correct column headers and escape cell values before printing them to the terminal
- Declared missing composer dependencies on `ext-pcre` and `monolog`

## [1.0.0] - 20 April 2026
- Initial release
