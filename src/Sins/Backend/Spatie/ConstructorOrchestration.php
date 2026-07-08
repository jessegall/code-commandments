<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Scaffold;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\PageObjects;

final class ConstructorOrchestration extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'constructor-orchestration',
            skill: PageObjects::class,
            description: "A page object fills a public slot imperatively in the constructor (`\$this->x = \$this->projector->…()`) where a `#[Computed]` property hook would describe it in place",
            rule: "Project each self-contained page-object slot in a `#[Computed]` get-hook, not an imperative constructor assignment.",
            suggestion: "Replace `\$this->x = expr;` with `#[Computed] public T \$x { get => expr; }`. Pin a deliberately-eager slot (one that must capture request-scoped state at build time) with `#[Eager]` — the scaffolded escape hatch."
        );
    }

    /**
     * The `#[Eager]` opt-out attribute the fix names — scaffolded into the consumer's own namespace so a
     * production Data class can mark a slot eager without importing this dev-only tool.
     */
    public function scaffolds(): array
    {
        return [
            new Scaffold('Support/Eager.php', 'Eager.php.stub'),
        ];
    }
}
