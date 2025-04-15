<?php

declare(strict_types=1);

namespace KangBabi\Transactions\Tests;

use KangBabi\Transactions\Transaction;
use RuntimeException;

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

it('executes finally block regardless of outcome', function (): void {
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
