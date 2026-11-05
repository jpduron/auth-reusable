<?php

declare(strict_types=1);

namespace App\Utils;

class Value {

    /**
     * =========================================
     * NULLABLE INT
     * =========================================
     * Convierte valores vacíos a null.
     *
     * Ejemplos:
     *
     * ""      => null
     * null    => null
     * "5"     => 5
     * 10      => 10
     */
    public static function nullableInt($value): ?int {

        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        return (int) $value;
    }

    /**
     * =========================================
     * NULLABLE FLOAT
     * =========================================
     */
    public static function nullableFloat($value): ?float {

        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        return (float) $value;
    }

    /**
     * =========================================
     * NULLABLE STRING
     * =========================================
     */
    public static function nullableString($value): ?string {

        if (
            $value === null ||
            trim((string) $value) === ''
        ) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * =========================================
     * NULLABLE BOOL
     * =========================================
     */
    public static function nullableBool($value): ?bool {

        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
    }
}