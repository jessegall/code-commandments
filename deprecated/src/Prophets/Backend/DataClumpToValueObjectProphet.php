<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\ArgumentGroupCensus;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a group of >= 3 values that repeatedly travel TOGETHER as the same argument
 * set across many call sites — a data clump that wants to be one value object.
 * Passing the same trio everywhere couples the callers to the shape and scatters the
 * invariants that should live on a single type.
 *
 * Cross-call via {@see ArgumentGroupCensus}: the same sorted set of simple arguments
 * (`$var` / `$this->prop`) at >= N (default 3) sites across >= 2 files. Near-zero-FP:
 * requires cross-FILE co-travel (not one method called in a loop) and excludes
 * framework pipeline signatures ($next/$handler). ADVISORY (a WARNING). GENERIC.
 */
#[IntroducedIn('2.22.0')]
class DataClumpToValueObjectProphet extends PhpCommandment
{
    private const MIN_SITES = 3;

    private const MIN_FILES = 2;

    /** Tokens that mark a framework pipeline/callback signature, not a data clump. */
    private const FRAMEWORK_TOKENS = ['$next', '$handler', '$callback', '$closure'];

    public function description(): string
    {
        return '3+ values that always travel together across calls should be a value object';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'The same set of >= 3 simple values (`$var` / `$this->prop`) is passed '
                . 'together as arguments at >= ' . self::MIN_SITES . ' call sites spanning '
                . '>= ' . self::MIN_FILES . ' files. They are a data clump — wrap them in '
                . 'one value object so the shape and its invariants live in one place.'
            )
            ->leaveWhen(
                'the values are unrelated primitives that only happen to be passed together; '
                . 'the group is a framework signature ($request, $next); they already form a '
                . 'VO/DTO elsewhere; or the co-travel is incidental (one method called in a loop).'
            )
            ->whenUnsure(
                'if the trio represents a concept (a date range, a money amount, an address), '
                . 'introduce a small value object and pass that — callers stop re-threading the '
                . 'parts and the invariants get a home. If they are genuinely unrelated, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When the same three-or-more values are passed together again and again, they are a
concept missing a type. Threading the loose parts everywhere couples every caller to
the shape and leaves the invariants (start <= end, currency matches amount) homeless.

Bad — ($start, $end, $tz) threaded through many calls:
    $this->report($start, $end, $tz);
    $this->export($start, $end, $tz);
    $this->chart($start, $end, $tz);

Good — one value object:
    $range = new DateRange($start, $end, $tz);
    $this->report($range); $this->export($range); $this->chart($range);

WHAT FIRES — the same SORTED SET of >= 3 simple argument tokens at >= 3 call sites
across >= 2 files.

WHAT DOES NOT — fewer than 3 values, a tuple that appears in only one file, a
framework pipeline signature ($next/$handler), or non-simple arguments. Advisory (a
WARNING); not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $census = ArgumentGroupCensus::forFile($filePath);

        if ($census->isEmpty()) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];
        $seen = [];

        foreach ([Node\Expr\MethodCall::class, Node\Expr\StaticCall::class, Node\Expr\FuncCall::class] as $type) {
            foreach ($finder->findInstanceOf($ast, $type) as $call) {
                if (method_exists($call, 'isFirstClassCallable') && $call->isFirstClassCallable()) {
                    continue;
                }

                $key = ArgumentGroupCensus::keyForArgs($call->getArgs());

                if ($key === null || isset($seen[$key]) || $this->isFrameworkSignature($key)) {
                    continue;
                }

                if (! $census->isClump($key, self::MIN_SITES, self::MIN_FILES)) {
                    continue;
                }

                $seen[$key] = true;
                $members = $census->membersOf($key);

                $warnings[] = $this->warningAt(
                    $call->getStartLine(),
                    sprintf(
                        'The values (%s) travel together as the same argument set at %d call sites across %d files — a data clump. Wrap them in one value object so the shape and its invariants live in one place, and callers pass the object instead of re-threading %d loose parts.',
                        implode(', ', $members),
                        $census->siteCount($key),
                        $census->fileCount($key),
                        count($members),
                    ),
                    null,
                    'data-clump:' . $key,
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function isFrameworkSignature(string $key): bool
    {
        foreach (self::FRAMEWORK_TOKENS as $token) {
            if (in_array($token, explode(',', $key), true)) {
                return true;
            }
        }

        return false;
    }
}
