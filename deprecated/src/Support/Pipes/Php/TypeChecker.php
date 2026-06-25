<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use ReflectionClass;

/**
 * Utility class for checking PHP type characteristics.
 */
final class TypeChecker
{
    /**
     * Check if the type is a Laravel Request type.
     */
    public static function isRequestType(string $fqcn): bool
    {
        if (! class_exists($fqcn)) {
            // Fall back to name-based check if class doesn't exist
            $shortName = self::getShortClassName($fqcn);

            return str_ends_with($shortName, 'Request');
        }

        try {
            $reflection = new ReflectionClass($fqcn);

            return $reflection->isSubclassOf('Illuminate\\Http\\Request')
                || $reflection->getName() === 'Illuminate\\Http\\Request';
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Check if the type is an Eloquent Model.
     */
    public static function isModelType(string $fqcn): bool
    {
        if (! class_exists($fqcn)) {
            return str_contains($fqcn, '\\Models\\')
                || str_contains($fqcn, '\\Projections\\');
        }

        try {
            $reflection = new ReflectionClass($fqcn);

            return $fqcn === 'Illuminate\\Database\\Eloquent\\Model'
                || $reflection->isSubclassOf('Illuminate\\Database\\Eloquent\\Model');
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Check if the type is an enum.
     */
    public static function isEnumType(string $fqcn): bool
    {
        return enum_exists($fqcn);
    }

    /**
     * Check if type is allowed for method injection (Request, Model, or Enum).
     */
    public static function isAllowedForMethodInjection(string $fqcn): bool
    {
        return self::isRequestType($fqcn)
            || self::isModelType($fqcn)
            || self::isEnumType($fqcn);
    }

    /**
     * Check if the type is a service (not allowed for method injection).
     */
    public static function isServiceType(string $fqcn): bool
    {
        return ! self::isAllowedForMethodInjection($fqcn);
    }

    /**
     * Check if the type is a FormRequest (subclass of Illuminate\Foundation\Http\FormRequest).
     */
    public static function isFormRequestType(string $fqcn): bool
    {
        if (! class_exists($fqcn)) {
            // Fall back to name-based check: any *Request except bare "Request"
            $shortName = self::getShortClassName($fqcn);

            return str_ends_with($shortName, 'Request')
                && $shortName !== 'Request'
                && $fqcn !== 'Illuminate\\Http\\Request';
        }

        try {
            $reflection = new ReflectionClass($fqcn);

            return $reflection->isSubclassOf('Illuminate\\Foundation\\Http\\FormRequest');
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Get short class name from FQCN.
     */
    public static function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
