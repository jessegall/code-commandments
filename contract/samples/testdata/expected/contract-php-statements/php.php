<?php

// @label money type
final class Money
{
    // @label ctor function
    public function __construct(public int $cents = 0) {}
}

// @label formatFn function
function format(string $label = ''): string
{
    return $label;
}

// @contract fact/parameter/passing = "reference"
function bump(int &$count): void {}

// @contract fact/parameter/collects = "rest"
function gather(string ...$names): void {}

// @contract fact/type-expression/form at 19 = "nullable"
// @contract role/type-expression/element at 19 -> moneyElement
// @contract fact/*/refers on type-expression at 20 -> money
// @label moneyElement type-expression 20
function nullable(?Money $money): void {}

function greet(string $name): string
{
    // @contract kind/interpolation
    // @contract role/interpolation/parts -> [greeting, greetName]
    // @contract kind/text
    // @contract fact/text/value = "Hi "
    // @label greeting text
    // @label greetName reference
    return "Hi $name";
}

function both(bool $open, bool $ready): bool
{
    // @contract kind/logical
    // @contract role/logical/left -> openRef
    // @contract role/logical/right -> readyRef
    // @label openRef reference 12
    // @label readyRef reference 21
    return $open && $ready;
}

function pick(?string $value): string
{
    // @contract kind/fallback
    // @contract role/fallback/subject -> pickValue
    // @contract role/fallback/replacement -> pickDefault
    // @label pickValue reference
    // @label pickDefault literal
    return $value ?? 'none';
}

function map(int $one): array
{
    // @contract kind/entry
    // @contract role/entry/key -> keyLit
    // @contract role/entry/value -> valueRef
    // @label keyLit literal
    // @label valueRef reference
    return ['a' => $one];
}

function count3(): int
{
    $seen = 0;
    // @contract role/loop/initializer -> [initAssign]
    // @contract role/loop/condition -> loopTest
    // @contract role/loop/steps -> [stepAssign]
    // @contract role/loop/body -> loopBody
    // @contract fact/assignment/mode at 26 = "increment"
    // @label initAssign assignment 10
    // @label stepAssign assignment 26
    // @label loopTest comparison
    // @label loopBody block
    for ($i = 0; $i < 3; $i++) {
        $seen += $i;
    }

    return $seen;
}

function run(?Money $money, array $names): void
{
    // @contract kind/construction
    // @contract kind/argument
    // @contract role/construction/type -> madeType
    // @contract role/construction/arguments -> [madeArg]
    // @contract role/argument/value -> madeValue
    // @contract fact/*/target on construction -> ctor
    // @contract fact/*/resolvedType on construction = {"kind":"named","name":"Money"}
    // @contract fact/assignment/mode = "assign"
    // @label madeType type-expression
    // @label madeArg argument
    // @label madeValue literal
    $made = new Money(5);
    // @contract kind/call
    // @contract kind/expression-statement
    // @contract kind/literal
    // @contract kind/reference
    // @contract role/expression-statement/expression -> formatCall
    // @contract role/call/callee -> formatName
    // @contract role/call/arguments -> [formatArg]
    // @contract fact/literal/literalKind = "string"
    // @contract fact/*/refers on reference -> formatFn
    // @label formatCall call
    // @label formatName reference
    // @label formatArg argument
    format('a');
    // @contract fact/argument/label = "label"
    format(label: 'x');
    // @contract fact/argument/spread = "positional"
    format(...$names);
    $total = 0;
    // @contract fact/assignment/mode = "compound"
    // @contract fact/assignment/operator = "add"
    // @contract role/assignment/targets -> [totalRef]
    // @contract role/assignment/values -> [twoLit]
    // @label totalRef reference
    // @label twoLit literal
    $total += 2;
    // @contract fact/access/nullConditional = true
    $other = $money?->cents;

    // @contract kind/try
    // @contract role/try/body -> tryBlock
    // @contract role/try/handlers -> [caught]
    // @label tryBlock block
    try {
        format('b');
    // @contract kind/handler
    // @contract fact/handler/catchForm = "typed"
    // @contract role/handler/caughtTypes -> [caughtType]
    // @contract role/handler/binding -> caughtBinding
    // @contract role/handler/body -> catchBody
    // @label caught handler
    // @label caughtType type-expression
    // @label caughtBinding binding
    // @label catchBody block
    } catch (Exception $e) {
        // @contract kind/throw
        // @contract role/throw/value -> thrown
        // @label thrown construction
        throw new Exception('failed');
    }
}
