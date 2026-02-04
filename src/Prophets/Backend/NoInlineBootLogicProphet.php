<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: No inline business logic in model boot hooks - Use events and listeners.
 */
class NoInlineBootLogicProphet extends PhpCommandment
{
    private const LIFECYCLE_HOOKS = [
        'creating',
        'created',
        'updating',
        'updated',
        'saving',
        'saved',
        'deleting',
        'deleted',
        'restoring',
        'restored',
        'replicating',
        'forceDeleting',
        'forceDeleted',
    ];

    public function description(): string
    {
        return 'Model boot hooks should only dispatch events, not contain business logic';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Model lifecycle hooks (static::creating, static::deleting, etc.) in boot() or booted()
methods should ONLY dispatch events. Business logic belongs in listeners.

This promotes separation of concerns and makes business logic testable and maintainable.

Bad:
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Shop $shop) {
            if (! $shop->config) {
                $shop->config = ShopConfigData::from(['type' => $shop->type]);
            }
        });

        static::deleting(function (Shop $shop) {
            $shop->orders()->delete();
            app(ShopResourceRepository::class)->deleteByShopIdAndType($shop->id, ResourceType::ORDER);
        });
    }

Good:
    protected static function booted(): void
    {
        static::created(function (Shop $shop) {
            event(new ShopCreated($shop));
        });

        static::deleting(function (Shop $shop) {
            event(new ShopDeleting($shop));
        });
    }

    // In a Listener
    class SetShopDefaults
    {
        public function handle(ShopCreated $event): void
        {
            if (! $event->shop->config) {
                $event->shop->config = ShopConfigData::from(['type' => $event->shop->type]);
                $event->shop->save();
            }
        }
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->returnRighteousWhen(fn (PhpContext $ctx) => ! $this->isModelFile($ctx->filePath, $ctx->content))
            ->pipe(fn (PhpContext $ctx) => $this->findInlineBootLogic($ctx))
            ->sinsFromMatches(
                fn (MatchResult $m) => sprintf('Inline business logic in static::%s() hook', $m->groups['hook']),
                'Dispatch an event instead and move business logic to a listener'
            )
            ->judge();
    }

    private function isModelFile(string $filePath, string $content): bool
    {
        // Check if file is in Models directory or extends Model
        if (str_contains($filePath, '/Models/')) {
            return true;
        }

        // Check if class extends Eloquent Model
        if (preg_match('/extends\s+(Model|Eloquent|Authenticatable|Pivot)/', $content)) {
            return true;
        }

        return false;
    }

    private function findInlineBootLogic(PhpContext $ctx): PhpContext
    {
        $matches = [];

        // Find boot() or booted() methods and extract their content
        $bootMethodPattern = '/(?:protected|public)\s+static\s+function\s+(?:boot|booted)\s*\(\s*\)(?:\s*:\s*void)?\s*\{/';

        if (! preg_match($bootMethodPattern, $ctx->content)) {
            return $ctx->with(matches: []);
        }

        // Find all lifecycle hook calls
        $hookPattern = implode('|', self::LIFECYCLE_HOOKS);
        // Match optional return type declarations (void, ?string, string|null, Foo&Bar, \Namespace\Class)
        $pattern = '/static::(' . $hookPattern . ')\s*\(\s*function\s*\([^)]*\)(?:\s*:\s*[?\w|&\\\\]+)?\s*\{/';

        preg_match_all($pattern, $ctx->content, $hookMatches, PREG_OFFSET_CAPTURE);

        if (empty($hookMatches[0])) {
            return $ctx->with(matches: []);
        }

        foreach ($hookMatches[0] as $index => $hookMatch) {
            $hookName = $hookMatches[1][$index][0];
            $startOffset = $hookMatch[1];
            $lineNumber = substr_count(substr($ctx->content, 0, $startOffset), "\n") + 1;

            // Extract the callback body
            $callbackBody = $this->extractCallbackBody($ctx->content, $startOffset);

            if ($callbackBody === null) {
                continue;
            }

            // Check if the callback only contains event() calls
            if (! $this->isEventOnlyCallback($callbackBody)) {
                $matches[] = new MatchResult(
                    name: 'inline_boot_logic',
                    pattern: $pattern,
                    match: $hookMatch[0],
                    line: $lineNumber,
                    offset: $startOffset,
                    content: trim($callbackBody),
                    groups: ['hook' => $hookName],
                );
            }
        }

        return $ctx->with(matches: $matches);
    }

    private function extractCallbackBody(string $content, int $startOffset): ?string
    {
        // Find the opening brace of the callback function
        $functionStart = strpos($content, '{', $startOffset);
        if ($functionStart === false) {
            return null;
        }

        // Track brace depth to find matching closing brace
        $depth = 1;
        $pos = $functionStart + 1;
        $length = strlen($content);

        while ($pos < $length && $depth > 0) {
            $char = $content[$pos];

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }

            $pos++;
        }

        if ($depth !== 0) {
            return null;
        }

        // Extract the body (excluding the braces)
        return substr($content, $functionStart + 1, $pos - $functionStart - 2);
    }

    private function isEventOnlyCallback(string $body): bool
    {
        // Remove whitespace and comments for analysis
        $cleanBody = preg_replace('/\/\/.*$/m', '', $body);
        $cleanBody = preg_replace('/\/\*.*?\*\//s', '', $cleanBody);
        $cleanBody = trim($cleanBody);

        // Empty callback is fine
        if (empty($cleanBody)) {
            return true;
        }

        // Split into statements (roughly by semicolons, but be careful with closures)
        // For simplicity, check if the body ONLY contains event() calls

        // Pattern for valid event dispatch: event(new SomeEvent(...)); or event(...);
        $eventPattern = '/^\s*event\s*\(\s*(?:new\s+)?[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:\s*\([^)]*\))?\s*\)\s*;?\s*$/s';

        // Check if entire body matches one or more event() calls
        $statements = array_filter(
            array_map('trim', preg_split('/;(?=\s*(?:event|$))/s', $cleanBody)),
            fn ($s) => ! empty($s)
        );

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }

            // Must be an event() call
            if (! preg_match('/^event\s*\(/', $statement)) {
                return false;
            }
        }

        return true;
    }
}
