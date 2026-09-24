<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * A kind of code construct, and the plain-English words a reader uses for it — so "loop over the orders"
 * measures against a loop and "fail when empty" against a throw, in any language. Each engine maps its own
 * nodes onto a construct and adds the keyword its language spells it with.
 */
enum Construct
{
    case Loop;
    case ConditionalLoop;
    case Condition;
    case Otherwise;
    case Return;
    case Break;
    case Continue;
    case Branch;
    case Attempt;
    case Recovery;
    case Failure;
    case Removal;
    case Output;
    case Type;
    case Contract;
    case Method;
    case Field;
    case Constant;
    case Creation;
    case Assignment;
    case Accumulation;
    case Import;
    case Scope;

    /**
     * The words a reader uses for this construct.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return match ($this) {
            self::Loop => ['loop', 'iterate', 'every', 'each'],
            self::ConditionalLoop => ['loop', 'until', 'repeat'],
            self::Condition => ['if', 'when', 'check', 'whether', 'otherwise'],
            self::Otherwise => ['else', 'otherwise'],
            self::Return => ['return', 'give', 'yield', 'result'],
            self::Break => ['stop', 'leave'],
            self::Continue => ['skip', 'next'],
            self::Branch => ['match', 'case', 'branch'],
            self::Attempt => ['try', 'catch', 'handle'],
            self::Recovery => ['catch', 'handle', 'error'],
            self::Failure => ['throw', 'raise', 'fail', 'error'],
            self::Removal => ['remove', 'drop', 'clear'],
            self::Output => ['print', 'output'],
            self::Type => ['class', 'type'],
            self::Contract => ['interface', 'contract'],
            self::Method => ['method', 'function'],
            self::Field => ['property', 'field'],
            self::Constant => ['const', 'constant'],
            self::Creation => ['new', 'create', 'make', 'build'],
            self::Assignment => ['set', 'assign', 'store'],
            self::Accumulation => ['add', 'increase', 'update'],
            self::Import => ['import'],
            self::Scope => ['with', 'open'],
        };
    }

    /**
     * The words a node of $class says as a construct, read from an engine's map of its node classes onto
     * constructs and their keywords — its keywords, and what a reader calls it; none when the map leaves it out.
     *
     * @param  array<string, array{self, list<string>}>  $constructs
     * @return list<string>
     */
    public static function wordsOf(array $constructs, string $class): array
    {
        if (! array_key_exists($class, $constructs)) {
            return [];
        }

        [$construct, $keywords] = $constructs[$class];

        return [...$keywords, ...$construct->words()];
    }
}
