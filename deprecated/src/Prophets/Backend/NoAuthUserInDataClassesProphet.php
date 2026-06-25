<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindAuthUserCalls;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: No auth()->user() or Auth::user() in Data classes - Use #[FromAuthenticatedUser] attribute instead.
 */
class NoAuthUserInDataClassesProphet extends PhpCommandment
{
    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Use #[FromAuthenticatedUser] attribute instead of auth()->user() in Data classes';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Do not use auth()->user(), Auth::user(), auth()->id(), or Auth::id() directly
inside Spatie Laravel Data classes. Instead, inject the authenticated user
via the constructor using the #[FromAuthenticatedUser] attribute.

This approach:
- Follows dependency injection principles with explicit dependencies
- Makes testing easier by allowing user injection without global auth state
- Follows Spatie Laravel Data conventions
- Makes the user dependency visible in the class signature

Bad:
    class KitchenIndexPage extends Data
    {
        public function __construct(
            #[FromContainer(SomeService::class)]
            public readonly SomeService $service,
        ) {}

        private function resolveProducts(): Collection
        {
            $user = auth()->user(); // SIN: Use #[FromAuthenticatedUser] instead

            return $user !== null
                ? $this->service->forUser($user)
                : Product::query()->get();
        }
    }

Good:
    class KitchenIndexPage extends Data
    {
        public function __construct(
            #[FromContainer(SomeService::class)]
            public readonly SomeService $service,

            #[Hidden]
            #[FromAuthenticatedUser]
            public readonly User|null $user,
        ) {}

        private function resolveProducts(): Collection
        {
            return $this->user !== null
                ? $this->service->forUser($this->user)
                : Product::query()->get();
        }
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->onlyDataClasses()
            ->pipe(new FindAuthUserCalls)
            ->sinsFromMatches(
                fn ($match) => sprintf(
                    'Using %s directly in Data class',
                    $match->name,
                ),
                fn ($match) => <<<'SUGGESTION'
Add to constructor:
    #[Hidden]
    #[FromAuthenticatedUser]
    public readonly User|null $user,

Then use $this->user instead of auth()->user()
SUGGESTION,
            )
            ->judge();
    }
}
