<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Agents;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Discovery;

/**
 * Every {@see Agent} that ships, discovered from this folder — the agent twin of the sin, detector
 * and skill catalogs. Agents enrol themselves like SKILLS do, not like detectors: an assistant a
 * project happens to use is not a rule it opted into, and a project that never opens one loses
 * nothing by the folder existing. Turning one off is `$config->disable(...)`, the same verb as
 * everything else.
 */
final class Catalog
{
    /**
     * @return list<Agent>
     */
    public static function all(): array
    {
        $agents = [];

        // The `Agent` suffix keeps this folder's other classes out of discovery; the subclass check
        // is what keeps the abstract base itself out, which would otherwise fatal on instantiation.
        foreach (Discovery::classes(__DIR__, __NAMESPACE__, 'Agent') as $class) {
            if (is_subclass_of($class, Agent::class)) {
                $agents[] = new $class;
            }
        }

        usort($agents, static fn (Agent $a, Agent $b): int => $a::class <=> $b::class);

        return $agents;
    }

    /**
     * The agents to wire for the project at $root — the shipped ones plus any it registered or wrote
     * itself, minus any it silenced.
     *
     * @return list<Agent>
     */
    public static function forProject(string $root): array
    {
        $config = Config::load($root);
        $registered = array_map(static fn (string $class): Agent => new $class, $config->agents());

        return $config->enabled([...self::all(), ...$registered, ...Custom::agents($root)]);
    }
}
