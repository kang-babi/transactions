<?php

declare(strict_types=1);

namespace KangBabi\Transactions;

use Closure;
use Throwable;

use function call_user_func;

/**
 * Execute callbacks through a fluent try/catch workflow with optional cleanup.
 *
 * Configure the main callback with try() before calling run(). Catch handlers
 * match exact exception classes; parent classes and interfaces do not match.
 * The result is the return value of the main callback or its matching handler.
 *
 * This class does not manage database transactions, report exceptions, or
 * normalize responses. Those responsibilities belong to the callbacks.
 */
final class Transaction
{
    /** @var Closure(): mixed */
    private Closure $try;

    /**
     * @var array<class-string<Throwable>, Closure(Throwable): mixed>
     */
    private array $catch = [];

    /** @var (Closure(): void)|null */
    private ?Closure $finally = null;

    /**
     * Return value of the main callback or the matching catch handler.
     */
    private mixed $evaluation;

    /**
     * Create a new workflow without executing any callbacks.
     */
    public static function start(): static
    {
        return new self();
    }

    /**
     * Set the main callback, replacing any previously configured callback.
     *
     * The callback receives no arguments and its return value becomes the result.
     *
     * @param Closure(): mixed $try
     */
    public function try(Closure $try): static
    {
        $this->try = $try;

        return $this;
    }

    /**
     * Register a handler for an exact exception class.
     *
     * The handler receives the thrown instance and its return value becomes the
     * result. Registering the same class again replaces its previous handler.
     * Exceptions thrown by the handler propagate without invoking other handlers.
     *
     * @param class-string<Throwable> $exception
     * @param Closure(Throwable): mixed $catch
     */
    public function catch(string $exception, Closure $catch): static
    {
        $this->catch[$exception] = $catch;

        return $this;
    }

    /**
     * Set the cleanup callback, replacing any previously configured callback.
     *
     * Runs only after the main callback or a matching catch handler completes
     * successfully. Unlike PHP's finally block, it is skipped when an unhandled
     * exception or a handler exception propagates. Its return value is ignored.
     *
     * @param Closure(): void $finally
     */
    public function finally(Closure $finally): static
    {
        $this->finally = $finally;

        return $this;
    }

    /**
     * Execute the configured callbacks and return the main or handled result.
     *
     * A main callback must be configured with try() first. Each call executes
     * the workflow again. Cleanup runs before the result is returned.
     *
     * @return mixed The main callback's or matching catch handler's return value.
     *
     * @throws Throwable When an exception has no exact handler, a handler or
     *                   cleanup callback throws, or the main callback is unset.
     */
    public function run(): mixed
    {
        try {
            $this->evaluation = call_user_func($this->try);
        } catch (Throwable $exception) {
            if (array_key_exists($exception::class, $this->catch)) {
                $this->evaluation = call_user_func($this->catch[$exception::class], $exception);
            } else {
                throw $exception;
            }
        }

        if ($this->finally instanceof Closure) {
            call_user_func($this->finally);
        }

        return $this->evaluation;
    }
}
