# Transactions

A small PHP library for composing a main callback, exception handlers, and optional cleanup through a fluent API. `run()` returns the main callback's result or the result of the matching exception handler. PHPDoc generics preserve those return types for PHPStan.

The package works independently of Laravel. Database transaction management, exception reporting, and response formatting belong to your callbacks.

## Installation

Requires PHP 8.0 or later.

```bash
composer require kang-babi/transactions
```

## Quick start

Start with `Transaction::start()`, configure the main callback with `try()`, then call `run()` to execute it.

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(fn (): string => 'Hello, World!')
    ->run();

// $result === 'Hello, World!'
```

Configuring callbacks does not execute them. Each call to `run()` executes the workflow again.

## Handling exceptions

Register a handler with the exception's class name and a closure. The handler receives the original exception instance, and its return value becomes the result.

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(function (): string {
        throw new RuntimeException('Operation failed.');
    })
    ->catch(RuntimeException::class, function (RuntimeException $exception): string {
        return 'Handled: ' . $exception->getMessage();
    })
    ->run();

// $result === 'Handled: Operation failed.'
```

You can register multiple handlers, including handlers for concrete `Error` classes:

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(function (): string {
        throw new InvalidArgumentException('Invalid input.');
    })
    ->catch(InvalidArgumentException::class, fn (InvalidArgumentException $exception): string => $exception->getMessage())
    ->catch(RuntimeException::class, fn (RuntimeException $exception): string => $exception->getMessage())
    ->run();

// $result === 'Invalid input.'
```

### Exact class matching

Handlers match the thrown exception's **exact class**, rather than using PHP's native `catch` inheritance rules. For example, a handler for `Exception::class` does not handle `RuntimeException`, and `Throwable::class` does not act as a catch-all handler. Register each concrete exception class you intend to handle.

An exception without an exact handler propagates to the caller. Exceptions thrown inside a handler also propagate; they are not passed to other registered handlers. Registering a handler for the same class again replaces the previous handler.

## Optional cleanup

Use `finally()` for a callback that runs after the main callback succeeds or an exception is handled successfully. It receives no arguments, and its return value is ignored.

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(function (): string {
        throw new RuntimeException('Operation failed.');
    })
    ->catch(RuntimeException::class, fn (RuntimeException $exception): string => 'Recovered')
    ->finally(function (): void {
        echo "Workflow completed.\n";
    })
    ->run();

// Prints: Workflow completed.
// $result === 'Recovered'
```

**Unlike PHP's native `finally` block, this callback does not always run.**

| Outcome | Cleanup runs? | Result of `run()` |
| --- | --- | --- |
| Main callback succeeds | Yes, if configured | Main callback's return value |
| Matching handler succeeds | Yes, if configured | Handler's return value |
| No matching handler | No | Original exception propagates |
| Handler throws | No | Handler's exception propagates |
| Cleanup throws | It starts but fails | Cleanup's exception propagates |

Exceptions from cleanup are not passed to registered handlers. If cleanup must run even when an exception propagates, use a native PHP `try`/`finally` block around `run()`.

## Return types and PHPStan

`run()` has a native `mixed` return type because callbacks can return any value. Its PHPDoc return type is `TTryResult|TCatchResult`, which lets PHPStan infer the union of the main callback's return type and all registered handler return types.

For example, a main callback returning `int` and a handler returning `null` produce an inferred `int|null` result. When both paths return compatible array shapes, the workflow can be returned directly from a method with an explicit array-shape contract:

```php
use KangBabi\Transactions\Transaction;

final class ExampleService
{
    /** @return array{success: bool, message: string, status: string} */
    public static function execute(bool $shouldFail): array
    {
        return Transaction::start()
            ->try(function () use ($shouldFail): array {
                if ($shouldFail) {
                    throw new RuntimeException('Operation failed.');
                }

                return [
                    'success' => true,
                    'message' => 'Operation completed.',
                    'status' => 'success',
                ];
            })
            ->catch(RuntimeException::class, function (RuntimeException $exception): array {
                return [
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'status' => 'error',
                ];
            })
            ->run();
    }
}
```

PHPStan infers the array shapes from these closure bodies. When a callback delegates to another method, document that method's return shape so the information remains available for inference. PHP enforces the native return type at runtime; PHPStan checks the more specific PHPDoc contract.

## API reference

| Method | Behavior |
| --- | --- |
| `Transaction::start()` | Creates a new workflow. |
| `try(Closure $try)` | Sets the main callback. A later call replaces it. Required before `run()`. |
| `catch(string $exception, Closure $catch)` | Registers a handler for an exact `Throwable` class. A later registration for the same class replaces it. |
| `finally(Closure $finally)` | Sets optional cleanup. A later call replaces it. |
| `run()` | Executes the workflow and returns the main callback's or matching handler's result. |

All configuration methods return the same instance for chaining. Calling `run()` before configuring `try()` raises an `Error` because the main callback is unset.

## Development

Install development dependencies and run the test suite:

```bash
composer install
composer pest
```

Check formatting and static analysis:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

Released under the [MIT license](LICENSE).
