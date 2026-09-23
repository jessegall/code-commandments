<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class DependencyDirection extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/dependency-direction',
            tier: Tier::KeepInMind,
            order: 43,
        );
    }

    public function title(): string
    {
        return 'Python dependency direction — imports point down the stack';
    }

    public function trigger(): string
    {
        return "Adding an import between two of the project's own Python packages. Read this before `from shop.ui.shared import …` inside `shop.ui.elements`, before an import inside a function added to dodge a circular import, and when a namespace-cycle or namespace-dependency finding points here. A declared layer may only import the layers it said it may use.";
    }

    public function intro(): string
    {
        return "A package is a claim about what depends on what. One import going the wrong way makes the two
        packages really just one package split across two names, so the direction is declared once and every
        import is checked against it.";
    }

    public function summary(): string
    {
        return 'a declared layer may only import the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
`shop.ui.elements` says "these are the primitives"; `shop.ui.shared` says "these are built FROM the
primitives". The claim is worth exactly as much as its imports: one `from shop.ui.shared import Panel` inside
`elements` and the two cannot be understood, tested or reused apart.

Nothing about the arrow shows when you write it. The import runs and the tests pass. Python even hands you
the escape hatch — move the import into the function body and the circular-import error goes away — and
the cycle is still there, only hidden.

### Declare the stack

The direction is declared once, in the project's `.commandments/config.php`, each layer naming the layers
it may use:

```php
$config->configure(fn (\JesseGall\CodeCommandments\Detectors\Python\NamespaceDependencyDetector $d) => $d
    ->layer('shop.ui.elements')                                  // primitives: itself only
    ->layer('shop.ui.shared', mayUse: ['shop.ui.elements'])      // built from the primitives
    ->layer('shop.domain')                                       // knows nothing about the UI
);
```

### What is judged

- **Every import**: `import x`, `from x import y`, a relative `from ..x import y`, and one written inside a
  function or behind `if TYPE_CHECKING:`. Where it sits does not change what it depends on.
- **Only declared packages.** The standard library, a third-party package, or a package the project never
  declared is always allowed.
- **Only from a declared layer.** A layer contains its own sub-packages, so imports within a layer are fine.
- **Cycles, declared or not.** Two of the project's packages importing each other are one package, whatever
  the folders say.

### First: which side is wrong?

A layer finding measures the code against a declaration someone wrote, and a declaration can be stale. Before
changing either, ask whether the declared stack still describes the design this project wants. Usually the
import is the accident; when you conclude the declaration is wrong, say so to the user with your reasoning.
Never quietly edit the declaration to make a finding go away.

### The fix

Move the thing both sides need down into the lower layer, pass it in from above, or invert the dependency
behind a protocol the lower layer owns. An import moved into a function body is not a fix: the arrow is
still there.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\DependencyDirection::class => 'the same discipline over PHP namespaces.',
            FixAtTheSource::class => 'the arrow that points the wrong way is where the fix belongs.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
