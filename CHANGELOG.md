# Changelog

## [1.1.0]

### Added

- PHPDoc generics that let PHPStan infer `run()`'s return type from the main callback and registered exception handlers, including unions and array shapes.
- Generic exception typing that connects each handler's parameter to its registered exception class.
- Tests for callback return values, exact exception matching, error handling, exception propagation, cleanup order, callback replacement, repeated execution, and execution without a main callback.

### Changed

- Updated class documentation with callback signatures, return values, replacement behavior, and exception propagation rules.
- Expanded the README with installation instructions, usage examples, generic return types, an API reference, and development commands.
- Changed the native return types of `start()`, `try()`, and `catch()` from `static` to `self` to support PHPStan inference. Fluent usage remains compatible because `Transaction` is final.

### Fixed

- Corrected documentation that described `finally()` as always running. It runs only after the main callback or a matching exception handler completes successfully.

Runtime exception matching and cleanup behavior are unchanged. Database transaction management, exception reporting, and response formatting remain the responsibility of the callbacks.
