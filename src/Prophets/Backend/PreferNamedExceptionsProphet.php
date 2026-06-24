<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindInlineExceptionConstruction;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag throw sites that assemble the exception where it is thrown —
 * generic SPL exceptions, and named exceptions fed a message string.
 * A throw site passes domain values; the exception builds its own
 * message in a static factory (preferred) or its constructor.
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static allow(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.23.0')]
class PreferNamedExceptionsProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Do not pass message strings at throw sites — throw named exceptions via static factories';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A throw site that builds its own message string is doing the
exception's job. `throw new RuntimeException("Missing required input
'{$name}' on node '{$this->nodeId}'")` names a failure CATEGORY, not a
failure — it cannot be caught by type, the message format is owned by
the caller, and the moment a second site throws the same failure the
string gets duplicated and drifts.

Bad — two call sites assembling the same message by hand:
    if ($port->required) {
        throw new RuntimeException(
            "Missing required input '{$port->name}' on node '{$this->nodeId}'",
        );
    }

Good — a named exception with a static factory:
    final class MissingRequiredInputException extends RuntimeException
    {
        public static function for(string $port, string $nodeId): self
        {
            return new self("Missing required input '{$port}' on node '{$nodeId}'");
        }
    }

    if ($port->required) {
        throw MissingRequiredInputException::for($port->name, $this->nodeId);
    }

THE RULE: a throw site passes DOMAIN VALUES, never strings. All string
assembly lives inside the exception class — in a static factory
(preferred) or, second best, its constructor. The factory's signature
documents exactly what context the failure needs, the throw reads as
prose, and tests assert on the exception TYPE instead of matching
message substrings.

This applies to named exceptions too. `throw new
PortNotWiredException("Port '{$name}' is not wired")` has the right
type but the message still leaked out of its home:

    throw PortNotWiredException::for($name);          // righteous
    throw new PortNotWiredException($port, $node);    // acceptable — ctor
                                                      // builds the message
    throw new PortNotWiredException("Port '...'");    // still a sin

A FACTORY DOES NOT LAUNDER A MESSAGE. Handing the message string to a
static factory is the same sin wearing a `::make()` — the prose still
lives at the call site, not in the exception:

    // sin — the factory is just a message courier:
    throw InvalidPipeDefinitionException::make(
        $reflection->getName(),
        'SingleSubPipelineAdapter implementations must define mapTo()',
        'public function mapTo(): mixed',
    );

    // righteous — the call site passes DATA; the message is built inside:
    throw InvalidPipeDefinitionException::missingMethod($reflection->getName(), 'mapTo');

The factory takes domain values (the class, the missing method) and
assembles the sentence itself. A multi-word string argument to an
exception factory is the tell that the message leaked.

FACTORY CONVENTIONS:

  - `::for(...)` is the default name; purpose-named factories are even
    better when one exception covers variants:
    `CannotCoerceValueException::toInt($value)`, `...::toFloat($value)`.
  - The factory returns `self` and is the ONLY place `new self(...)`
    with a message appears.
  - Carry the domain values as typed public readonly properties next to
    the message — catchers get data, not a string to parse:

        public static function for(string $port, string $nodeId): self
        {
            $e = new self("Missing required input '{$port}' on node '{$nodeId}'");
            // or promote $port/$nodeId via a constructor that builds
            // the message itself.
            return $e;
        }

  - Extend the closest matching SPL class (RuntimeException,
    InvalidArgumentException, ...) or a package-level base exception so
    coarse-grained catches keep working.

WHAT REMAINS RIGHTEOUS:

  - `new self(...)` / `new static(...)` (or the class's own name) inside
    the exception's factories and constructor — that is the message's
    one home.
  - Rethrows (`throw $e`) and exceptions received from elsewhere.
  - Named exceptions constructed from domain values without a message
    string (`new PortNotWiredException($port, $node)`) — the constructor
    builds the message. A static factory is still preferred.
  - Vendor factories like `ValidationException::withMessages([...])` —
    already the pattern this prophet prescribes.

Claude (and any other AI agent): never type `throw new` followed by a
string argument. Find (or create) the named domain exception, give it a
static factory taking the domain values, and throw via the factory. If
you are interpolating, concatenating, or sprintf-ing inside a throw
statement, stop — that string belongs inside the exception class.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindInlineExceptionConstruction)
            ->withAllowed((array) $this->config('allow', []));

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe)
            ->sinsFromMatches(
                fn ($match) => $this->messageFor($match->groups),
                fn ($match) => $this->suggestionFor($match->groups),
            )
            ->judge();
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function messageFor(array $groups): string
    {
        return match ($groups['kind']) {
            'generic' => sprintf(
                'throw new %s in %s() — generic exception assembled at the throw site',
                $groups['exception'],
                $groups['method'],
            ),
            'factory_message' => sprintf(
                'throw %s::%s() in %s() is handed a message string — the message must be built inside the exception, not passed to the factory',
                $groups['exception'],
                $groups['factory'],
                $groups['method'],
            ),
            default => sprintf(
                'throw new %s with an inline message string in %s() — the message leaked out of the exception',
                $groups['exception'],
                $groups['method'],
            ),
        };
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function suggestionFor(array $groups): string
    {
        return match ($groups['kind']) {
            'generic' => sprintf(
                'Create `final class %s extends %s` with a static factory and throw %s::for(...) — '
                . 'pass domain values and let the exception build its own message.',
                $groups['suggested'],
                $groups['exception'],
                $groups['suggested'],
            ),
            'factory_message' => sprintf(
                'Move the message string INSIDE %s — its %s() factory should take domain values and assemble '
                . 'the message itself, so the call site passes data, not prose.',
                $groups['exception'],
                $groups['factory'],
            ),
            default => sprintf(
                'Move the message into %s as a static factory — throw %s::for(...) with the domain values '
                . 'instead of the formatted string.',
                $groups['exception'],
                $groups['exception'],
            ),
        };
    }
}
