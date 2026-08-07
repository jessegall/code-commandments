<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

use JesseGall\CodeCommandments\Agents\Agent;
use JesseGall\CodeCommandments\Agents\Catalog;
use JesseGall\CodeCommandments\Workspace;

/**
 * The README's agents table, projected from the {@see Agent} classes themselves — so an agent
 * cannot be added without appearing in the docs, and cannot claim a folder there that it does not
 * actually read.
 */
final class AgentCatalog
{
    public static function table(): string
    {
        $rows = ['| Agent | Skills | Instructions | Enforced by hooks | What it gets |', '|---|---|---|---|---|'];

        foreach (Catalog::all() as $agent) {
            $rows[] = '| ' . implode(' | ', [
                "**{$agent->name()}**",
                self::code($agent->skillsDir() ?? Workspace::LIBRARY) . ($agent->skillsDir() === null ? ' _(read directly)_' : ' _(links)_'),
                self::code($agent->instructionsFile() ?? 'AGENTS.md'),
                $agent->enforces() ? 'yes' : 'no',
                $agent->summary(),
            ]) . ' |';
        }

        return implode("\n", $rows) . "\n";
    }

    private static function code(string $value): string
    {
        return "`{$value}`";
    }
}
