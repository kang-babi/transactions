# Transactions

The `Transaction` class provides a fluent interface for handling try-catch-finally blocks in a clean and structured way. It allows you to define a `try` block, multiple `catch` blocks for specific exceptions, and a `finally` block that will always execute.

## Usage

### 1. Basic Usage with Try Block

You can define a `try` block to execute your main logic.

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(fn() => "Hello, World!")
    ->run();

echo $result; // Outputs: Hello, World!
```

### 2. Adding a Catch Block

You can handle specific exceptions using the catch method.

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(function () {
        throw new RuntimeException("An error occurred!");
    })
    ->catch(RuntimeException::class, fn($e) => "Caught exception: " . $e->getMessage())
    ->run();

echo $result; // Outputs: Caught exception: An error occurred!
```

### 3. Using a Finally Block

The finally block will always execute, regardless of whether an exception was thrown or not.

```php
use KangBabi\Transactions\Transaction;

Transaction::start()
    ->try(function () {
        echo "Trying...\n";
    })
    ->finally(function () {
        echo "Finally block executed.\n";
    })
    ->run();

// Outputs:
// Trying...
// Finally block executed.
```

### 4. Combining Try, Catch, and Finally

You can combine all three methods for a complete transaction.

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(function () {
        throw new InvalidArgumentException("Invalid argument!");
    })
    ->catch(InvalidArgumentException::class, fn($e) => "Handled: " . $e->getMessage())
    ->finally(function () {
        echo "Cleaning up resources.\n";
    })
    ->run();

echo $result;
// Outputs:
// Cleaning up resources.
// Handled: Invalid argument!
```

### 5. Handling Multiple Exception Types

You can handle multiple exception types by chaining catch calls.

```php
use KangBabi\Transactions\Transaction;

$result = Transaction::start()
    ->try(function () {
        throw new LogicException("Logic error!");
    })
    ->catch(InvalidArgumentException::class, fn($e) => "Caught InvalidArgumentException")
    ->catch(LogicException::class, fn($e) => "Caught LogicException: " . $e->getMessage())
    ->run();

echo $result; // Outputs: Caught LogicException: Logic error!
```

## Key Methods

- `Transaction::start()`: Creates a new transaction instance.
- `try(Closure $try)`: Defines the main logic to execute.
- `catch(string $exception, Closure $catch)`: Handles specific exception types.
- `finally(Closure $finally)`: Defines a block that always executes after the try block.
- `run()`: Executes the transaction and returns the result.

## Notes

- If an exception is thrown and no matching catch block is defined, the exception will propagate.
- The finally block is optional but will always execute if defined.
