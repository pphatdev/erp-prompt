<?php

declare(strict_types=1);

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Symmetric-encrypt on write, decrypt on read — but FALL BACK to returning the
 * raw stored value when decryption fails instead of throwing.
 *
 * Why this exists: the Phase 1 encryption migration converts plaintext salary
 * + bank columns to ciphertext. Rows inserted via raw DB writes (seeders,
 * manual SQL, factory states that bypass the model) end up holding plaintext
 * in columns the model now expects to be ciphertext. Laravel's stock
 * `'encrypted'` cast throws `DecryptException` on read in that case, which
 * 500s the entire list endpoint.
 *
 * The fallback keeps the surface usable in mixed-data environments and lets
 * an out-of-band re-encryption job clean things up incrementally.
 */
class EncryptedWithFallback implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // Treat the raw stored value as plaintext. Callers see the
            // expected payload; an admin can rewrite the row via Update to
            // promote it to ciphertext.
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        return [$key => Crypt::encryptString((string) $value)];
    }
}
