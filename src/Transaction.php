<?php

declare(strict_types=1);

namespace KangBabi\Transactions;

use Closure;
use Throwable;

final class Transaction
{
    private Closure $try;

    /**
     * @var array<string, Closure> $catch - Collection of catchable exceptions.
     */
    private array $catch = [];

    private ?Closure $finally = null;

    /**
     * Result of the evaluation of the try block.
     */
    private mixed $evaluation;

    /**
     * Start a transaction.
     */
    public static function start(): static
    {
        return new self();
    }

    /**
     * The main code to be executed.
     */
    public function try(Closure $try): static
    {
        $this->try = $try;

        return $this;
    }

    /**
     * Catch an exception type.
     *
     * @param class-string $exception
     */
    public function catch(string $exception, Closure $catch): static
    {
        $this->catch[$exception] = $catch;

        return $this;
    }

    /**
     * Add statements that will run regardless of outcome.
     */
    public function finally(Closure $finally): static
    {
        $this->finally = $finally;

        return $this;
    }

    /**
     * Execute the transaction.
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
