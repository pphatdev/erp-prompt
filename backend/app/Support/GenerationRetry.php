<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use Throwable;

/**
 * Retry helper for sequence-generator inserts where the next code is
 * computed via `MAX(suffix) + 1` and concurrent callers race.
 *
 * Wraps a closure that issues the insert. If it throws either
 *   - `Illuminate\Database\UniqueConstraintViolationException` (Laravel 11+
 *     normalised exception), or
 *   - a raw `PDOException` whose SQLSTATE is `23505` (Postgres unique
 *     violation),
 * the closure is retried up to `$attempts` times. Each retry should
 * regenerate the candidate code (the supplied closure is invoked fresh
 * every attempt, so callers that re-read the generator inside their
 * closure get the new MAX automatically).
 *
 * Any other exception type propagates immediately on the first attempt.
 *
 * Example:
 *   GenerationRetry::handle(function () use ($recruitment, $data) {
 *       $data['employee_id'] = $recruitment->generateNextEmployeeId();
 *       return Employee::create($data);
 *   });
 */
class GenerationRetry
{
    public const DEFAULT_ATTEMPTS = 5;

    /**
     * @template T
     * @param  callable(): T  $fn
     * @return T
     */
    public static function handle(callable $fn, int $attempts = self::DEFAULT_ATTEMPTS): mixed
    {
        if ($attempts < 1) {
            throw new \InvalidArgumentException('GenerationRetry attempts must be >= 1.');
        }

        $remaining = $attempts;
        $lastException = null;

        while ($remaining-- > 0) {
            try {
                return $fn();
            } catch (UniqueConstraintViolationException $e) {
                $lastException = $e;
            } catch (PDOException $e) {
                // Direct PDOException variant - normalize on SQLSTATE 23505.
                if (($e->errorInfo[0] ?? null) !== '23505' && $e->getCode() !== '23505') {
                    throw $e;
                }
                $lastException = $e;
            } catch (Throwable $e) {
                throw $e;
            }
        }

        // Exhausted - re-throw the last collision so the caller can surface
        // a sensible error rather than silently looping forever.
        throw $lastException ?? new \RuntimeException('GenerationRetry exhausted without a captured exception.');
    }
}
