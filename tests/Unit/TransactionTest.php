<?php

declare(strict_types=1);

namespace KangBabi\Transactions\Tests;

use KangBabi\Transactions\Transaction;
use RuntimeException;

it('executes try block successfully', function () {
  $result = Transaction::start()
    ->try(fn() => 42)
    ->run();

  expect($result)->toBe(42);
});

it('handles exception in catch block', function () {
  $result = Transaction::start()
    ->try(function () {
      throw new RuntimeException('Something went wrong');
    })
    ->catch(RuntimeException::class, fn() => 'Handled exception')
    ->run();

  expect($result)->toBe('Handled exception');
});

it('throws unhandled exception', function () {
  expect(
    fn() => Transaction::start()
      ->try(function () {
        throw new RuntimeException('Unhandled exception');
      })
      ->run()
  )->toThrow(RuntimeException::class, 'Unhandled exception');
});

it('executes finally block regardless of outcome', function () {
  $finallyExecuted = false;

  Transaction::start()
    ->try(fn() => 42)
    ->finally(function () use (&$finallyExecuted) {
      $finallyExecuted = true;
    })
    ->run();

  expect($finallyExecuted)->toBeTrue();
});

it('passes exception instance to catch block', function () {
  $exceptionMessage = '';

  Transaction::start()
    ->try(function () {
      throw new RuntimeException('Test exception');
    })
    ->catch(RuntimeException::class, function (RuntimeException $e) use (&$exceptionMessage) {
      $exceptionMessage = $e->getMessage();
    })
    ->run();

  expect($exceptionMessage)->toBe('Test exception');
});
