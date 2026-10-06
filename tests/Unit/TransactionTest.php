<?php

declare(strict_types=1);

namespace KangBabi\Transactions\Tests;

use KangBabi\Transactions\Transaction;
use Error;
use Exception;
use LogicException;
use RuntimeException;
use stdClass;
use Throwable;

it('executes try block successfully', function (): void {
    $result = Transaction::start()
        ->try(fn () => 42)
        ->run();

    expect($result)->toBe(42);
});

it('handles exception in catch block', function (): void {
    $result = Transaction::start()
        ->try(function (): void {
            throw new RuntimeException('Something went wrong');
        })
        ->catch(RuntimeException::class, fn () => 'Handled exception')
        ->run();

    expect($result)->toBe('Handled exception');
});

it('throws unhandled exception', function (): void {
    expect(
        fn () => Transaction::start()
            ->try(function (): void {
                throw new RuntimeException('Unhandled exception');
            })
            ->run()
    )->toThrow(RuntimeException::class, 'Unhandled exception');
});

it('executes finally block after a successful try block', function (): void {
    $finallyExecuted = false;

    Transaction::start()
        ->try(fn () => 42)
        ->finally(function () use (&$finallyExecuted): void {
            $finallyExecuted = true;
        })
        ->run();

    expect($finallyExecuted)->toBeTrue();
});

it('passes exception instance to catch block', function (): void {
    $exceptionMessage = '';

    Transaction::start()
        ->try(function (): void {
            throw new RuntimeException('Test exception');
        })
        ->catch(RuntimeException::class, function (RuntimeException $e) use (&$exceptionMessage): void {
            $exceptionMessage = $e->getMessage();
        })
        ->run();

    expect($exceptionMessage)->toBe('Test exception');
});

it('preserves callback return values', function (mixed $value): void {
    expect(Transaction::start()->try(fn (): mixed => $value)->run())->toBe($value);

    $result = Transaction::start()
        ->try(fn () => throw new RuntimeException('Failed'))
        ->catch(RuntimeException::class, fn (): mixed => $value)
        ->run();

    expect($result)->toBe($value);
})->with([
    'null' => [null],
    'boolean' => [false],
    'integer' => [0],
    'string' => ['result'],
    'array' => [['success' => true]],
    'object' => [new stdClass()],
]);

it('does not execute callbacks during configuration', function (): void {
    $called = false;
    $callback = function () use (&$called): void {
        $called = true;
    };

    Transaction::start()
        ->try($callback)
        ->catch(RuntimeException::class, $callback)
        ->finally($callback);

    expect($called)->toBeFalse();
});

it('skips catch handlers when the main callback succeeds', function (): void {
    $result = Transaction::start()
        ->try(fn (): string => 'success')
        ->catch(RuntimeException::class, fn () => throw new LogicException('Unexpected handler'))
        ->run();

    expect($result)->toBe('success');
});

it('selects the exact handler among multiple exception classes', function (): void {
    $result = Transaction::start()
        ->try(fn () => throw new RuntimeException('Failed'))
        ->catch(Exception::class, fn (): string => 'parent')
        ->catch(LogicException::class, fn (): string => 'unrelated')
        ->catch(RuntimeException::class, fn (): string => 'exact')
        ->run();

    expect($result)->toBe('exact');
});

it('does not match parent exception classes or interfaces', function (string $exceptionClass): void {
    $exception = new RuntimeException('Unhandled');
    $transaction = Transaction::start()
        ->try(fn () => throw $exception)
        ->catch($exceptionClass, fn (): string => 'handled');

    expect(fn () => $transaction->run())->toThrow($exception);
})->with([Exception::class, Throwable::class]);

it('handles errors and passes the original throwable instance', function (): void {
    $error = new Error('Failed');
    $result = Transaction::start()
        ->try(fn () => throw $error)
        ->catch(Error::class, function (Error $caught) use ($error): string {
            expect($caught)->toBe($error);

            return 'handled';
        })
        ->run();

    expect($result)->toBe('handled');
});

it('runs cleanup after the matching handler', function (): void {
    $events = [];
    $result = Transaction::start()
        ->try(function () use (&$events): void {
            $events[] = 'try';

            throw new RuntimeException('Failed');
        })
        ->catch(RuntimeException::class, function () use (&$events): string {
            $events[] = 'catch';

            return 'handled';
        })
        ->finally(function () use (&$events): void {
            $events[] = 'finally';
        })
        ->run();

    expect($events)->toBe(['try', 'catch', 'finally'])
        ->and($result)->toBe('handled');
});

it('skips cleanup when an exception is unhandled', function (): void {
    $called = false;
    $exception = new RuntimeException('Unhandled');
    $transaction = Transaction::start()
        ->try(fn () => throw $exception)
        ->finally(function () use (&$called): void {
            $called = true;
        });

    expect(fn () => $transaction->run())->toThrow($exception)
        ->and($called)->toBeFalse();
});

it('propagates handler exceptions without running other handlers or cleanup', function (): void {
    $called = false;
    $exception = new LogicException('Handler failed');
    $transaction = Transaction::start()
        ->try(fn () => throw new RuntimeException('Failed'))
        ->catch(RuntimeException::class, fn () => throw $exception)
        ->catch(LogicException::class, function () use (&$called): void {
            $called = true;
        })
        ->finally(function () use (&$called): void {
            $called = true;
        });

    expect(fn () => $transaction->run())->toThrow($exception)
        ->and($called)->toBeFalse();
});

it('propagates cleanup exceptions without invoking catch handlers', function (): void {
    $called = false;
    $exception = new RuntimeException('Cleanup failed');
    $transaction = Transaction::start()
        ->try(fn (): string => 'success')
        ->catch(RuntimeException::class, function () use (&$called): void {
            $called = true;
        })
        ->finally(fn () => throw $exception);

    expect(fn () => $transaction->run())->toThrow($exception)
        ->and($called)->toBeFalse();
});

it('replaces the previously configured main callback', function (): void {
    $result = Transaction::start()
        ->try(fn () => throw new LogicException('Replaced callback'))
        ->try(fn (): string => 'replacement')
        ->run();

    expect($result)->toBe('replacement');
});

it('replaces the handler registered for the same exception class', function (): void {
    $result = Transaction::start()
        ->try(fn () => throw new RuntimeException('Failed'))
        ->catch(RuntimeException::class, fn () => throw new LogicException('Replaced handler'))
        ->catch(RuntimeException::class, fn (): string => 'replacement')
        ->run();

    expect($result)->toBe('replacement');
});

it('replaces the previously configured cleanup callback', function (): void {
    $called = false;
    Transaction::start()
        ->try(fn (): int => 42)
        ->finally(fn () => throw new LogicException('Replaced cleanup'))
        ->finally(function () use (&$called): void {
            $called = true;
        })
        ->run();

    expect($called)->toBeTrue();
});

it('executes the workflow again on each run', function (): void {
    $runs = 0;
    $cleanups = 0;
    $transaction = Transaction::start()
        ->try(function () use (&$runs): int {
            return ++$runs;
        })
        ->finally(function () use (&$cleanups): void {
            $cleanups++;
        });

    expect($transaction->run())->toBe(1)
        ->and($transaction->run())->toBe(2)
        ->and($cleanups)->toBe(2);
});

it('requires a main callback before execution', function (): void {
    expect(fn () => Transaction::start()->run())->toThrow(Error::class);
});
