<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\EnumInstanceResolver;
use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use PhpParser\Node;
use PhpParser\NodeFinder;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use ReflectionClass;

/**
 * The CompareSelf set helpers (`equalsAny` / `notEqualsAny` / …) carry TWO call
 * shapes: an instance form anchored on a known case, and a STATIC form whose
 * first argument is the *possibly-null* value under test:
 *
 *     Enum::equalsAny($subject, Enum::A, Enum::B)   // static — $subject may be null
 *     $subject->equalsAny(Enum::A, Enum::B)         // instance — $subject is a real case
 *
 * The static form exists ONLY to tolerate a null/dynamic subject. When the
 * subject is statically a NON-NULLABLE instance of the enum (a typed property or
 * variable that can never be null), the null-tolerance is wasted and the call
 * reads backwards — you hold the case, so anchor on it:
 *
 *     // $descriptor->type is a non-nullable NodeType
 *     NodeType::equalsAny($descriptor->type, NodeType::Pipe, NodeType::Pipeline)
 *  -> $descriptor->type->equalsAny(NodeType::Pipe, NodeType::Pipeline)
 *
 * Only flagged when the subject's type is PROVABLY the same enum and
 * non-nullable (a typed enum param/variable, `$this->prop`, or `$var->prop`
 * resolved via the in-file class or reflection). Anything unresolved or
 * nullable is left alone — the static form is the right shape there.
 *
 *
 *
 *
 * @method-generated-start
 * @method static anyMethods(array $value)
 * @method static trait(string $value)
 * @method-generated-end
 */
#[IntroducedIn('2.56.0')]
class AnchorEnumComparisonProphet extends PhpCommandment implements SinRepenter
{
    private const DEFAULT_TRAIT = 'App\\Support\\Enums\\CompareSelf';

    /** The CompareSelf set helpers whose static form takes a possibly-null subject first. */
    private const ANY_METHODS = ['equalsAny', 'notEqualsAny', 'equalsAnyIgnoreType', 'notEqualsAnyIgnoreType'];

    public function description(): string
    {
        return 'Anchor a CompareSelf set comparison on the non-null enum instance instead of the static form';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): ?Advisory
    {
        return Advisory::make()
            ->applyWhen('The first argument is a non-nullable instance of the same enum (a typed property/variable that can never be null) — anchor on it: `$subject->equalsAny(...)`.')
            ->leaveWhen('The subject can legitimately be null, or is a dynamic/mixed value — the static form is the null-safe shape for exactly that case.')
            ->whenUnsure('If you cannot tell whether the subject is non-null, leave the static form.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
The CompareSelf trait exposes its set helpers (`equalsAny`, `notEqualsAny`,
`equalsAnyIgnoreType`, `notEqualsAnyIgnoreType`) in two shapes:

    $case->equalsAny(Enum::A, Enum::B)          // instance — anchored on a real case
    Enum::equalsAny($value, Enum::A, Enum::B)   // static    — $value MAY be null

The static form's first argument is the *possibly-null value under test*; it
exists purely so a null/dynamic subject never crashes the comparison. When the
subject is statically a NON-NULLABLE instance of that same enum, that
null-tolerance is dead weight and the call reads backwards — you already hold the
case, so anchor on it:

    // $descriptor->type : NodeType (non-nullable)
    NodeType::equalsAny($descriptor->type, NodeType::Pipe, NodeType::Pipeline)
      ->  $descriptor->type->equalsAny(NodeType::Pipe, NodeType::Pipeline)

This prophet flags an existing static `*Any` call ONLY when the subject is
PROVABLY a non-nullable instance of the called enum:

  - a typed enum parameter/variable        `fn (NodeType $t) => NodeType::equalsAny($t, …)`
  - `$this->prop` typed as the enum         (non-nullable property in the same class)
  - `$obj->prop` typed as the enum          (resolved via the in-file class or reflection)

A nullable subject (`?Enum`, `Enum|null`), a `mixed`/scalar subject, a subject of
a DIFFERENT type, or anything it cannot resolve is left untouched — the static
form is correct there. The rewrite is mechanical and [AUTO-FIXABLE]: drop the
subject from the argument list and call the instance method on it.

Configuration:

    AnchorEnumComparisonProphet::class => [
        'trait' => App\Support\Enums\CompareSelf::class,
        'any_methods' => ['equalsAny', 'notEqualsAny', 'equalsAnyIgnoreType', 'notEqualsAnyIgnoreType'],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $warnings = [];

        foreach ($this->collect($content) as $finding) {
            $warnings[] = $this->warningAt(
                $finding['line'],
                $finding['message'],
                $finding['snippet'],
                $finding['symbol'],
                autoFixable: true,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $findings = $this->collect($content);

        if ($findings === []) {
            return RepentanceResult::unchanged();
        }

        // Apply right-to-left so earlier offsets stay valid.
        usort($findings, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        $penance = [];

        foreach ($findings as $finding) {
            $original = substr($content, $finding['start'], $finding['end'] - $finding['start'] + 1);
            $content = substr($content, 0, $finding['start']) . $finding['replacement'] . substr($content, $finding['end'] + 1);
            $penance[] = "Anchored `{$original}` on the instance: `{$finding['replacement']}`";
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * Collect every anchorable static set comparison in the file.
     *
     * @return list<array{start:int,end:int,line:int,message:string,snippet:string,symbol:string,replacement:string}>
     */
    private function collect(string $content): array
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return [];
        }

        $finder = new NodeFinder;
        $uses = FileImports::of($ast);
        $namespace = $this->extractNamespace($ast, $finder);
        $methods = $this->anyMethods();
        $traitFqcn = ltrim((string) $this->config('trait', self::DEFAULT_TRAIT), '\\');

        $findings = [];

        /** @var array<Node\Expr\StaticCall> $calls */
        $calls = $finder->findInstanceOf($ast, Node\Expr\StaticCall::class);

        foreach ($calls as $call) {
            if (! $call->name instanceof Node\Identifier) {
                continue;
            }

            $method = $call->name->toString();

            if (! in_array($method, $methods, true)) {
                continue;
            }

            $enumFqcn = $this->resolveClassFqcn($call, $ast, $finder, $uses, $namespace);

            if ($enumFqcn === null || ! $this->enumSupportsStaticSet($enumFqcn, $method, $traitFqcn, $ast, $finder)) {
                continue;
            }

            // Need a subject + at least one case argument, none unpacked.
            $args = $call->args;

            if (count($args) < 2) {
                continue;
            }

            foreach ($args as $arg) {
                if (! $arg instanceof Node\Arg || $arg->unpack) {
                    continue 2;
                }
            }

            $subject = $args[0]->value;

            if (! $subject instanceof Node\Expr\Variable && ! $subject instanceof Node\Expr\PropertyFetch) {
                continue;
            }

            $type = EnumInstanceResolver::resolve($subject, (int) $call->getStartFilePos(), $ast, $finder, $uses, $namespace);

            if ($type === null || $type[1] === true || $type[0] !== $enumFqcn) {
                // unresolved, nullable, or a different type — leave the static form.
                continue;
            }

            $subjectText = substr($content, (int) $subject->getStartFilePos(), (int) $subject->getEndFilePos() - (int) $subject->getStartFilePos() + 1);
            $caseTexts = [];

            foreach (array_slice($args, 1) as $arg) {
                $caseTexts[] = substr($content, (int) $arg->getStartFilePos(), (int) $arg->getEndFilePos() - (int) $arg->getStartFilePos() + 1);
            }

            $replacement = sprintf('%s->%s(%s)', $subjectText, $method, implode(', ', $caseTexts));
            $short = $this->shortName($enumFqcn);
            $start = (int) $call->getStartFilePos();
            $end = (int) $call->getEndFilePos();

            $findings[] = [
                'start' => $start,
                'end' => $end,
                'line' => $call->getStartLine(),
                'snippet' => substr($content, $start, $end - $start + 1),
                'symbol' => $short . '::' . $method . '@' . $subjectText,
                'message' => sprintf(
                    'Static `%s::%s($subject, …)` with a non-nullable %s subject — the static form is for a possibly-null subject. Anchor on the instance: `%s`.',
                    $short,
                    $method,
                    $short,
                    $replacement,
                ),
                'replacement' => $replacement,
            ];
        }

        return $findings;
    }

    /**
     * The enum FQCN named by a static call's class operand (resolving `self`/`static`
     * to the enclosing enum), or null when it is not a plain class name.
     *
     * @param  array<Node>  $ast
     * @param  array<string,string>  $uses
     */
    private function resolveClassFqcn(Node\Expr\StaticCall $call, array $ast, NodeFinder $finder, array $uses, ?string $namespace): ?string
    {
        if (! $call->class instanceof Node\Name) {
            return null;
        }

        $name = $call->class->toString();

        if (in_array(strtolower($name), ['self', 'static'], true)) {
            $enum = $this->enclosingEnum((int) $call->getStartFilePos(), $ast, $finder);

            return $enum;
        }

        return ltrim(NameResolver::resolve($name, $uses, $namespace), '\\');
    }

    /**
     * Whether $enumFqcn supports the static set call `$method` — i.e. it uses the
     * configured CompareSelf trait, OR uses ANY trait that declares the CompareSelf
     * contract for `$method` (`@method static … $method(mixed $value, …)`). The
     * structural fallback means a consumer never has to duplicate the `trait`
     * config just because its CompareSelf lives in a different namespace.
     *
     * @param  array<Node>  $ast
     */
    private function enumSupportsStaticSet(string $enumFqcn, string $method, string $traitFqcn, array $ast, NodeFinder $finder): bool
    {
        $node = $this->classLikeNodeFor($enumFqcn, $ast, $finder);

        if ($node !== null) {
            $uses = FileImports::of($ast);
            $namespace = $this->extractNamespace($ast, $finder);

            foreach ($node->getTraitUses() as $traitUse) {
                foreach ($traitUse->traits as $trait) {
                    $resolved = ltrim(NameResolver::resolve($trait->toString(), $uses, $namespace), '\\');

                    if ($resolved === $traitFqcn || $this->traitDeclaresStaticSet($resolved, $method, $ast, $finder)) {
                        return true;
                    }
                }
            }

            return false;
        }

        if (! enum_exists($enumFqcn, autoload: true) && ! class_exists($enumFqcn, autoload: true)) {
            return false;
        }

        try {
            foreach ((new ReflectionClass($enumFqcn))->getTraitNames() as $used) {
                $used = ltrim($used, '\\');

                if ($used === $traitFqcn || $this->traitDeclaresStaticSet($used, $method, $ast, $finder)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Whether a trait declares the CompareSelf static-set contract for $method:
     * `@method static <ret> <method>(mixed $value, …)`. Read from the in-file AST
     * node when the trait is declared here, else from reflection.
     *
     * @param  array<Node>  $ast
     */
    private function traitDeclaresStaticSet(string $traitFqcn, string $method, array $ast, NodeFinder $finder): bool
    {
        $node = $this->classLikeNodeFor($traitFqcn, $ast, $finder);

        if ($node instanceof Node\Stmt\Trait_) {
            return $this->docDeclaresStaticSet($node->getDocComment()?->getText(), $method);
        }

        if (! trait_exists($traitFqcn, autoload: true)) {
            return false;
        }

        try {
            $doc = (new ReflectionClass($traitFqcn))->getDocComment();
        } catch (\Throwable) {
            return false;
        }

        return $this->docDeclaresStaticSet($doc === false ? null : $doc, $method);
    }

    private function docDeclaresStaticSet(?string $doc, string $method): bool
    {
        if ($doc === null || $doc === '') {
            return false;
        }

        // @method static bool equalsAny(mixed $value, \UnitEnum ...$cases)
        return preg_match('/@method\s+static\s+\S+\s+' . preg_quote($method, '/') . '\s*\(\s*mixed\s+\$/i', $doc) === 1;
    }

    /**
     * Locate the class/enum AST node declaring $fqcn in this file, or null.
     *
     * @param  array<Node>  $ast
     */
    private function classLikeNodeFor(string $fqcn, array $ast, NodeFinder $finder): ?Node\Stmt\ClassLike
    {
        $namespace = $this->extractNamespace($ast, $finder);

        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $node) {
            if ($node->name === null) {
                continue;
            }

            $declared = $namespace !== null && $namespace !== '' ? $namespace . '\\' . $node->name->toString() : $node->name->toString();

            if (ltrim($declared, '\\') === ltrim($fqcn, '\\')) {
                return $node;
            }
        }

        return null;
    }

    private function enclosingEnum(int $pos, array $ast, NodeFinder $finder): ?string
    {
        $namespace = $this->extractNamespace($ast, $finder);

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Enum_::class) as $enum) {
            if ($enum->name !== null && (int) $enum->getStartFilePos() <= $pos && (int) $enum->getEndFilePos() >= $pos) {
                return $namespace !== null && $namespace !== '' ? $namespace . '\\' . $enum->name->toString() : $enum->name->toString();
            }
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string,string>  alias => FQCN
     */
    /**
     * @param  array<Node>  $ast
     */
    private function extractNamespace(array $ast, NodeFinder $finder): ?string
    {
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Namespace_::class) as $ns) {
            return $ns->name?->toString();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function anyMethods(): array
    {
        $configured = $this->config('any_methods', self::ANY_METHODS);

        if (! is_array($configured) || $configured === []) {
            return self::ANY_METHODS;
        }

        return array_values(array_map(static fn ($m): string => (string) $m, $configured));
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
