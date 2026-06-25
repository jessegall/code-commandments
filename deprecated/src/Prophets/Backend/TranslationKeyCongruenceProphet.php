<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\LangKeyIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a `__('group.key')` / `trans(...)` / `trans_choice(...)` whose key is NOT
 * declared in any lang file — a missing or mistyped translation key. A miss returns
 * the key STRING itself, so `group.key` shows verbatim to the user instead of the
 * translated text.
 *
 * Cross-artifact via {@see LangKeyIndex} (the lang-file key tree). Mirrors
 * {@see ConfigKeyContractProphet}: near-zero-FP by firing only when the key's GROUP
 * is an OWNED lang file (`lang/<locale>/group.php` exists) but the full key is absent
 * — a typo within your own translations. Framework/vendor namespaces (no owned group,
 * `package::` keys) and dynamic keys are left alone. ADVISORY (a WARNING).
 */
#[IntroducedIn('2.21.0')]
class TranslationKeyCongruenceProphet extends PhpCommandment
{
    private const TRANSLATORS = ['__', 'trans', 'trans_choice'];

    public function description(): string
    {
        return 'A __()/trans() key must exist in a lang file — a missing key renders as the key string';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `__(\'group.key\')` / `trans(...)` / `trans_choice(...)` uses a literal '
                . 'dotted key whose GROUP is an OWNED lang file (`lang/<locale>/group.php` '
                . 'exists) but whose full key is not declared in any locale — a typo or a '
                . 'removed key. A miss renders the key string itself to the user.'
            )
            ->leaveWhen(
                'the key is dynamic (`__($var)`); it is a vendor/package namespace '
                . '(`package::group.key`) not published; it is a JSON/string key (no group, '
                . 'the sentence IS the key); or the group is framework-owned with no published '
                . 'lang file to check against.'
            )
            ->whenUnsure(
                'if the key should exist, add it to the lang file; if it is a typo, fix it to '
                . 'the declared key. A translation miss renders the raw key — make the key '
                . 'match the lang tree.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`__('a.b')` / `trans('a.b')` returns the translation at that key, or the KEY STRING
itself when the key does not exist. A mistyped or removed key therefore renders as
`a.b` (or the whole group path) to the user — a visible bug with no error.

Bad — a key no lang file declares (lang/en/common.php has no `equals`, or no common.php):
    __('common.equlas')   // renders "common.equlas"

Good — the declared key:
    __('common.equals')

WHAT FIRES — a `__`/`trans`/`trans_choice` with a LITERAL dotted key whose first
segment is an OWNED lang group (a `lang/<locale>/<group>.php` exists) but whose full
key is declared in no locale.

WHAT DOES NOT — a dynamic key, a `package::` vendor namespace, a JSON/string key (no
group), or a framework group with no published lang file. Advisory (a WARNING); not
auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $index = LangKeyIndex::forFile($filePath);

        if ($index->isEmpty()) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name
                || ! in_array(strtolower($call->name->toString()), self::TRANSLATORS, true)
            ) {
                continue;
            }

            $arg = $call->getArgs()[0]->value ?? null;

            if (! $arg instanceof Node\Scalar\String_) {
                continue; // dynamic key
            }

            $key = $arg->value;

            if (! str_contains($key, '.') || str_contains($key, '::')) {
                continue; // JSON/string key or a vendor namespace
            }

            $group = strtok($key, '.');

            if ($group === false || ! $index->ownsGroup($group) || $index->hasKey($key)) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    "__('%s') uses a translation key NOT declared in any lang file. The `%s` lang group exists (lang/<locale>/%s.php), but `%s` is not a declared key — a typo or a removed key, and a translation miss renders the key STRING itself to the user. Fix the key or add it to the `%s` lang file.",
                    $key,
                    $group,
                    $group,
                    $key,
                    $group,
                ),
                null,
                'translation-key-congruence:' . $key,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }
}
