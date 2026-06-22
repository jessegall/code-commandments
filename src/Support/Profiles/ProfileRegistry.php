<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * The package-owned catalogue of available profiles. Owned by the PACKAGE (not
 * consumer config) so a newly-shipped profile is usable the moment the package
 * updates — the consumer only stores the *selection* (`.commandments/profile`).
 *
 * Adding a profile = write the class and add one line here.
 */
final class ProfileRegistry
{
    public const DEFAULT = 'disabled';

    /** @var array<string, class-string<Profile>> */
    private const PROFILES = [
        'disabled' => DisabledProfile::class,
        'grind' => GrindProfile::class,
        'phased' => PhasedProfile::class,
        'sins-only' => SinsOnlyProfile::class,
    ];

    /**
     * @return array<string, Profile> name => instance, in declaration order
     */
    public static function all(): array
    {
        $profiles = [];

        foreach (self::PROFILES as $name => $class) {
            $profiles[$name] = new $class();
        }

        return $profiles;
    }

    public static function get(string $name): ?Profile
    {
        $class = self::PROFILES[$name] ?? null;

        return $class === null ? null : new $class();
    }

    public static function has(string $name): bool
    {
        return isset(self::PROFILES[$name]);
    }

    public static function default(): Profile
    {
        return self::get(self::DEFAULT);
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::PROFILES);
    }
}
