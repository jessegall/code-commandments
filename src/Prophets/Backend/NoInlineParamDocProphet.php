<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag an inline `/** @var T *\/` type doc on a parameter (common on a promoted
 * constructor property). PHPStan reads it, but the documented, conventional home
 * for a parameter's type is a `@param T $name` line on the FUNCTION's docblock —
 * which keeps the parameter list uncluttered and gathers the types in one place.
 */
#[IntroducedIn('1.144.0')]
class NoInlineParamDocProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Declare a parameter type with @param on the function docblock, not an inline /** @var */';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A parameter (often a promoted constructor property) carries an inline `/** @var T */` type docblock. The conventional home for a parameter\'s type is a `@param T $name` line on the function\'s docblock.')
            ->leaveWhen('the inline comment is not a type annotation (a plain explanatory note that genuinely belongs beside that one parameter), or the function already declares the type via `@param` and the inline doc is redundant noise to simply delete.')
            ->whenUnsure('move the type to a `@param T $name` line on the function docblock — PHPStan reads it identically (it applies to the promoted property too), and the signature stays clean.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
An inline `/** @var T */` above a parameter works — PHPStan reads it — but it
clutters the parameter list and scatters the type information. The documented,
conventional place for a parameter's type is a `@param` line on the function's
docblock; for a promoted constructor property PHPStan applies that `@param` to
the property too, so nothing is lost.

Bad — inline @var on a promoted param:
    public function __construct(
        public string $name,
        /** @var list<string>|null */
        public array | null $options = null,
        public bool $many = false,
    ) {}

Good — @param on the constructor docblock:
    /**
     * @param list<string>|null $options
     */
    public function __construct(
        public string $name,
        public array | null $options = null,
        public bool $many = false,
    ) {}

WHAT FIRES — a parameter whose attached docblock is an inline `/** @var ... */`
type annotation, on any function/method/closure.

WHAT DOES NOT — a parameter with no docblock, or a plain explanatory comment that
is not a `@var` type. Advisory: this is a readability/convention nudge, not a
correctness bug.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        $functionLikes = array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Expr\Closure::class),
        );

        foreach ($functionLikes as $function) {
            $owner = $function instanceof Node\Stmt\ClassMethod && $function->name->toString() === '__construct'
                ? 'constructor'
                : 'function';

            foreach ($function->params as $param) {
                $doc = $param->getDocComment();

                if ($doc === null) {
                    continue;
                }

                $type = $this->varType($doc->getText());

                if ($type === null) {
                    continue; // not a `@var` type annotation — leave plain notes alone
                }

                $name = $param->var instanceof Expr\Variable && is_string($param->var->name)
                    ? $param->var->name
                    : null;

                if ($name === null) {
                    continue;
                }

                $warnings[] = $this->warningAt(
                    $param->getStartLine(),
                    sprintf(
                        'Parameter $%s carries an inline `/** @var %s */` — declare it as `@param %s $%s` on the %s\'s docblock instead, so the parameter list stays clean and the types read together (PHPStan reads it identically).',
                        $name,
                        $type,
                        $type,
                        $name,
                        $owner,
                    ),
                    $this->lineAt($content, $param->getStartLine()),
                    'inline-param-doc:' . $name,
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The type expression of an inline `@var T` docblock (single line or within a
     * block comment), or null when the comment is not a `@var` type annotation.
     */
    private function varType(string $doc): ?string
    {
        if (preg_match('/@var\s+(.+?)(?:\s*\*\/|\R|$)/', $doc, $m) !== 1) {
            return null;
        }

        $type = trim($m[1]);

        return $type === '' ? null : $type;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
