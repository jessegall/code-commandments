<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\ConfigMapIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag an enum whose case set DUPLICATES a set the project already REGISTERS, as
 * data, in a config file — and nudge a config-driven registry.
 *
 * Detection is a cross-artifact CONGRUENCE via {@see ConfigMapIndex}: a config
 * file declares a data-driven map (`providers => ['anthropic' => …, 'openai' =>
 * …]`, the registered set), and an enum's cases ARE that same set (`AiProvider`:
 * `Anthropic = 'anthropic'`, `OpenAi = 'openai'`). The set then lives in two
 * places at once, so adding a member is shotgun surgery (config + enum + every
 * match/dispatch over it) even though config alone "should" register it.
 *
 * ADVISORY (a WARNING, manually checked): whether the set is genuinely meant to be
 * config-extensible is a judgment call — but the config↔enum key-set congruence is
 * a strong, low-coincidence anchor that's worth surfacing for review. It is NOT a
 * sin and never blocks. GENERIC: no framework, no provider/key name lists — purely
 * the config-array shape and the enum's own cases.
 */
#[IntroducedIn('2.11.0')]
class PreferConfigDrivenRegistryProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'An enum whose cases mirror a config-registered set should be driven by a config registry';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'An enum\'s case set (its backed values, or case names) is EXACTLY the '
                . 'key set of a data-driven map declared in a config file — the same '
                . 'registered set lives both in config (as data) and in the enum (as '
                . 'hardcoded cases), so adding a member is shotgun surgery across both '
                . 'plus every match/dispatch over the enum.'
            )
            ->leaveWhen(
                'ONLY when the config map is NOT registering these members — it '
                . 'coincidentally shares their keys for a different purpose (e.g. per-member '
                . 'display labels / settings keyed BY an enum that is the genuine source of '
                . 'truth). NOTE: "each member needs an implementation class / some code to '
                . 'add" is NOT a leave-reason — that per-member code (the case, the match '
                . 'arm, the per-member method) IS the hardcoded wiring the registry removes; '
                . 'the class stays, the duplication goes. Needing code != a fixed set.'
            )
            ->whenUnsure(
                'default to DOING it (this is advisory only because intent cannot always be '
                . 'proven from the code — not because it is optional): drive the set from a '
                . 'config-backed REGISTRY — iterate the config map, register a factory/value '
                . 'per key, and resolve through the registry instead of a hardcoded enum + '
                . 'match. Leave it only if the config map is genuinely not registering these '
                . 'members (a coincidental key overlap).'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A config file that declares a data-driven map IS a registry of a set — "add a
provider here and give it a key". When an enum then hardcodes that exact same set
as its cases, the set lives in two places: adding one member means editing the
config AND the enum AND every `match`/dispatch over the enum (a per-provider
getter, a `forModel` branch, …). The config map stops being the source of truth.

Bad — the set is declared twice (config data + enum cases):
    // config/services.php
    'providers' => ['anthropic' => [...], 'openai' => [...]],   // the registered set
    // src/…/AiProvider.php
    enum AiProvider: string { case Anthropic = 'anthropic'; case OpenAi = 'openai'; }
    // + match(AiProvider) { Anthropic => new …, OpenAi => new … } scattered around

Good — config registers, code resolves through a registry:
    // iterate config('…providers') once, register a factory per key into a
    // keyed registry; resolve by the config key. Adding a provider = a config
    // entry + one registration; no enum, no match to edit.

WHAT FIRES — an enum (in the scanned file) with >= 2 cases whose token set (backed
values, else case names; case-insensitive) EXACTLY equals the key set of a
`>= 2`-key map declared in a `config/*.php` file of the same project.

WHAT DOES NOT — an enum whose case set does not match any config map; a config map
of < 2 keys; a set with no config declaration at all. This is a WARNING (advisory)
— it surfaces the config↔enum duplication for a human to judge; it never blocks
and is not auto-fixable (extracting a registry changes structure).
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];
        $index = null;

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Enum_::class) as $enum) {
            if ($enum->name === null) {
                continue;
            }

            $tokens = $this->caseTokens($enum);

            if (count($tokens) < 2) {
                continue;
            }

            $index ??= ConfigMapIndex::forFile($filePath);
            $matches = $index->mapsMatching($tokens);

            if ($matches === []) {
                continue;
            }

            $map = $matches[0];

            $warnings[] = $this->warningAt(
                $enum->getStartLine(),
                sprintf(
                    "CONFIG↔CODE DUPLICATION — the same set is declared in TWO places:\n"
                    . "  • config registers it as DATA at `%s` (keys: %s)\n"
                    . "  • enum `%s` (this file) hardcodes it as cases: %s\n"
                    . "These are the SAME set. Adding a member therefore means editing BOTH the config AND this enum, plus every match/dispatch over the enum. Default stance: this SHOULD be a config-driven registry — iterate the config map and register a factory/value per key, then resolve through the registry instead of the hardcoded enum + match.\n"
                    . "WHY this is flagged (not the agent's call to wave off): the config file IS the registry of this set; the enum re-declares it, so the two drift independently. NOTE: \"each member needs its own class / some code to add\" is NOT a reason to leave it — that per-member code (the enum case, the match arm, the per-member method) IS the duplicated wiring a registry removes; the implementation class stays, the duplication goes. Leave it ONLY if the config map is not actually registering these members (a coincidental key overlap, e.g. per-member display labels keyed by a fixed enum).",
                    $map['path'],
                    implode(', ', $map['keys']),
                    $enum->name->toString(),
                    $this->caseMapping($enum),
                ),
                null,
                'config-mirrored-enum:' . $enum->name->toString(),
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The enum's case TOKENS — each case's backed string value when present, else
     * its case name. (Non-string backings fall back to the name.)
     *
     * @return list<string>
     */
    private function caseTokens(Node\Stmt\Enum_ $enum): array
    {
        $tokens = [];

        foreach ($enum->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\EnumCase) {
                continue;
            }

            $tokens[] = $stmt->expr instanceof Node\Scalar\String_
                ? $stmt->expr->value
                : $stmt->name->toString();
        }

        return $tokens;
    }

    /**
     * A readable display of the enum's cases for the message — `Name = 'value'` for a
     * backed case, else `Name` — so the warning shows the code side of the duplication.
     */
    private function caseMapping(Node\Stmt\Enum_ $enum): string
    {
        $parts = [];

        foreach ($enum->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\EnumCase) {
                continue;
            }

            $parts[] = $stmt->expr instanceof Node\Scalar\String_
                ? sprintf("%s = '%s'", $stmt->name->toString(), $stmt->expr->value)
                : $stmt->name->toString();
        }

        return implode(', ', $parts);
    }
}
