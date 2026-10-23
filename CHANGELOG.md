# Changelog

All notable changes to `givanov95/laravel-activity-log` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-10-23

### Added
- `ActivityLog::record()` builder: explicit actor (`Actor::user`, `model`, `guest`, `system`) with role and stored label, scope, subject with stored label, related models, changes and properties. Written synchronously on the caller's connection, so it joins the caller's transaction.
- Configurable actor resolver for entries saved without `->by()` (default: the signed-in user; system when nobody is signed in).
- `Changes`: `fromModel()` after save, `created()`, `deleted()`, `make()`, and `snapshot()->diff()` for attributes and relation sets; masking of hidden and configured attributes, `only`/`except`/`mask`/`merge`.
- Append-only `ActivityLogEntry` model with `inScope`, `forSubject`, `involving`, `byActor`, `byRole`, `action` and `between` scopes; batch uuid per request or job.
- On Laravel 11 `Changes::fromModel()` throws a `LogicException` (it needs `Model::getPrevious()` from Laravel 12); use `snapshot()->diff()` there.
- Encrypted casts are masked like hidden attributes; unchanged hidden fields are not reported; values compare the way Eloquent does.
- `ActivityLog::scrub()` for GDPR anonymization (only the entries about the model lose its fields; its IP goes from the entries it made), `ActivityLog::forgetScope()`, and the `activity-log:prune` command (`retention_days`, off by default).
