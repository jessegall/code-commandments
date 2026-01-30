# Code Commandments

**This is a personal tool I built for my own projects.** It's public in case others find it useful or want to fork it for their own coding standards.

A Laravel package that enforces coding standards through automated validation, designed to work seamlessly with **Claude Code** (Anthropic's AI coding assistant).

## Why This Exists

When working with Claude Code, I needed a way to:
1. **Teach Claude my coding standards** - So it writes code the way I want from the start
2. **Automatically check Claude's output** - Catch violations immediately after Claude makes changes
3. **Provide actionable feedback** - Give Claude clear instructions on what to fix

This package hooks into Claude Code's hook system to create a feedback loop:

```
┌─────────────────────────────────────────────────────────────────┐
│                     Claude Code Session                          │
├─────────────────────────────────────────────────────────────────┤
│  1. Session starts                                               │
│     └─> Hook runs: commandments:scripture --claude               │
│         └─> Claude learns all coding rules                       │
│                                                                  │
│  2. Claude writes/modifies code                                  │
│                                                                  │
│  3. After changes complete                                       │
│     └─> Hook runs: commandments:judge --claude                   │
│         └─> Claude sees any violations                           │
│         └─> Claude fixes them automatically                      │
│                                                                  │
│  4. Repeat until code is "righteous" (no violations)             │
└─────────────────────────────────────────────────────────────────┘
```

The `--claude` flag outputs machine-readable text optimized for AI assistants, without decorative ASCII art or colors.

## Installation

```bash
composer require --dev jessegall/code-commandments
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=commandments-config
```

## Sacred Terminology

| Technical Term | Biblical Term |
|----------------|---------------|
| Violation | **Sin** / **Transgression** |
| Fix/Auto-fix | **Repent** / **Absolution** |
| Warning | **Prophecy** |
| Validator | **Prophet** |
| Validator class | **[Name]Prophet** |
| Validators folder | **Prophets/** |
| Pass | **Righteous** / **Blessed** |
| Fail | **Sinful** / **Fallen** |
| Review | **Confession** |
| Mark as reviewed | **Absolve** |
| Groups | **Scrolls** |

## Commands

### Judge the Codebase

```bash
# Judge all scrolls
php artisan commandments:judge

# Judge a specific scroll
php artisan commandments:judge --scroll=backend

# Judge with a specific prophet
php artisan commandments:judge --prophet=NoRawRequest

# Judge a specific file
php artisan commandments:judge --file=app/Http/Controllers/UserController.php

# Judge multiple specific files (comma-separated)
php artisan commandments:judge --files=app/Models/User.php,app/Services/AuthService.php

# Mark files as absolved after manual review
php artisan commandments:judge --absolve

# Claude-friendly output (for AI assistants)
php artisan commandments:judge --claude
```

### Seek Absolution (Auto-fix)

```bash
# Auto-fix all sins that can be absolved
php artisan commandments:repent

# Preview what would be fixed
php artisan commandments:repent --dry-run

# Fix a specific file
php artisan commandments:repent --file=app/Http/Controllers/UserController.php

# Fix multiple specific files (comma-separated)
php artisan commandments:repent --files=app/Models/User.php,app/Services/AuthService.php

# Claude-friendly output
php artisan commandments:repent --claude
```

### Read the Scripture (List Commandments)

```bash
# List all prophets
php artisan commandments:scripture

# List with detailed descriptions
php artisan commandments:scripture --detailed

# List prophets from a specific scroll
php artisan commandments:scripture --scroll=frontend

# Claude-friendly output
php artisan commandments:scripture --claude
```

### Summon a New Prophet

```bash
# Create a new backend prophet
php artisan make:prophet NoMagicNumbers --scroll=backend

# Create a new frontend prophet
php artisan make:prophet NoInlineStyles --scroll=frontend --type=frontend

# Create a prophet that can auto-fix
php artisan make:prophet NoUnusedImports --repentable

# Create a prophet that requires manual review
php artisan make:prophet ComplexLogicReview --confession
```

### Claude Code Integration

#### The `--claude` Flag

The `--claude` flag provides AI-optimized output that's easier for Claude Code to parse and act upon:

```bash
# Get summary of violations with actionable next steps
php artisan commandments:judge --claude

# See what auto-fixes are available
php artisan commandments:repent --dry-run --claude

# List all rules in a machine-readable format
php artisan commandments:scripture --claude
```

**Claude output features:**
- Concise, structured output without decorative ASCII art
- Groups violations by type with file lists
- Provides specific commands to run for each violation type
- Clearly separates sins (must fix) from warnings (review required)

#### Automated Hooks

Claude Code supports [hooks](https://docs.anthropic.com/en/docs/claude-code/hooks) - shell commands that run at specific points during a session. This package leverages hooks to create an automated feedback loop.

Install the hooks:

```bash
php artisan commandments:install-hooks
```

This adds the following to your `.claude/settings.json`:

```json
{
  "hooks": {
    "PreToolUse": [],
    "PostToolUse": [],
    "Notification": [],
    "Stop": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "php artisan commandments:judge --claude --git"
          }
        ]
      }
    ],
    "SubagentStop": []
  }
}
```

**How it works:**

| Hook | When it runs | What it does |
|------|--------------|--------------|
| `Stop` | After Claude finishes a response | Runs `judge --claude --git` to check changed files |

The `--git` flag ensures only new/modified files are checked, keeping feedback focused and fast.

**Typical workflow:**

1. You ask Claude to implement a feature
2. Claude writes the code
3. Hook automatically runs `commandments:judge --claude --git`
4. If violations found, Claude sees them and fixes automatically
5. Repeat until code passes all checks

## Configuration

Configure your scrolls in `config/commandments.php`:

```php
return [
    'scrolls' => [
        'backend' => [
            'path' => app_path(),
            'extensions' => ['php'],
            'exclude' => ['Console/Kernel.php'],
            'prophets' => [
                // Simple registration (no config needed)
                Backend\NoRawRequestProphet::class,
                Backend\NoJsonResponseProphet::class,

                // With per-prophet configuration
                Backend\ControllerPrivateMethodsProphet::class => [
                    'max_private_methods' => 3,
                    'min_method_lines' => 3,
                ],
            ],
        ],
        'frontend' => [
            'path' => resource_path('js'),
            'extensions' => ['vue', 'ts', 'js'],
            'prophets' => [
                Frontend\NoFetchAxiosProphet::class,
                Frontend\CompositionApiProphet::class,

                // Configure max lines thresholds
                Frontend\LongVueFilesProphet::class => [
                    'max_vue_lines' => 200,
                ],
                Frontend\LongTsFilesProphet::class => [
                    'max_ts_lines' => 200,
                ],

                // Configure allowed Tailwind patterns for style overrides
                Frontend\StyleOverridesProphet::class => [
                    'allowed_patterns' => [
                        // Width/height
                        '/^(min-|max-)?(w|h)-/',
                        '/^size-/',

                        // Flexbox
                        '/^flex$/',
                        '/^inline-flex$/',
                        '/^items-/',
                        '/^justify-/',

                        // Transitions & animations
                        '/^transition/',
                        '/^duration-/',
                        '/^animate-/',

                        // Interactions
                        '/^cursor-/',
                        '/^opacity-/',
                    ],
                ],
            ],
        ],
    ],

    'confession' => [
        'tablet_path' => storage_path('commandments/confessions.json'),
    ],
];
```

### Per-Prophet Configuration

Prophets can be registered in two ways:

1. **Simple registration** - Just the class name when no config is needed:
   ```php
   Backend\NoRawRequestProphet::class,
   ```

2. **With configuration** - Class name as key, config array as value:
   ```php
   Backend\ControllerPrivateMethodsProphet::class => [
       'max_private_methods' => 3,
   ],
   ```

### Configurable Prophets

| Prophet | Config Key | Default | Description |
|---------|------------|---------|-------------|
| `LongVueFilesProphet` | `max_vue_lines` | 200 | Maximum lines in Vue files |
| `LongTsFilesProphet` | `max_ts_lines` | 200 | Maximum lines in script sections |
| `ControllerPrivateMethodsProphet` | `max_private_methods` | 3 | Max private methods in controllers |
| `ControllerPrivateMethodsProphet` | `min_method_lines` | 3 | Min lines for method to count |
| `StyleOverridesProphet` | `allowed_patterns` | [] | Additional Tailwind patterns to allow |

### StyleOverridesProphet Patterns

The `StyleOverridesProphet` flags appearance classes on base components (Button, Card, etc.) but allows layout classes by default. You can extend the allowed patterns:

```php
Frontend\StyleOverridesProphet::class => [
    'allowed_patterns' => [
        // These are in ADDITION to built-in layout patterns
        '/^(min-|max-)?(w|h)-/',  // Width/height
        '/^flex$/',               // Flex display
        '/^items-/',              // Flex alignment
        '/^cursor-/',             // Cursor styles
        '/^transition/',          // Transitions
    ],
],
```

**Built-in allowed patterns** (always allowed):
- Margin/padding: `m-`, `p-`, `space-`, `gap-`
- Grid layout: `col-span-`, `row-span-`, etc.
- Flex behavior: `flex-1`, `grow`, `shrink`, `self-`
- Positioning: `absolute`, `relative`, `top-`, `z-`
- Display: `hidden`, `block`, `inline-block`

## Built-in Prophets

### Backend (PHP)

1. **NoRawRequestProphet** - Thou shalt not access raw request data
2. **NoJsonResponseProphet** - Thou shalt not return raw JSON responses
3. **NoDirectRequestInputProphet** - Thou shalt not access request data directly in controllers
4. **NoEventDispatchProphet** - Thou shalt use event() helper for dispatching
5. **NoRecordThatOutsideAggregateProphet** - Thou shalt not use recordThat() outside aggregates
6. **NoValidatedMethodProphet** - Thou shalt not use validated() method
7. **NoInlineValidationProphet** - Thou shalt not use inline validation
8. **TypeScriptAttributeProphet** - Thou shalt use #[TypeScript] on Resources
9. **ReadonlyDataPropertiesProphet** - Thou shalt not declare readonly properties in Data class body
10. **FormRequestTypedGettersProphet** - Thou shalt use typed getters in FormRequest
11. **HiddenAttributeProphet** - Thou shalt hide sensitive model attributes
12. **NoCustomFromModelProphet** - Thou shalt not use custom fromModel methods
13. **ControllerPrivateMethodsProphet** - Thou shalt not have too many private methods in controllers
14. **KebabCaseRoutesProphet** - Thou shalt use kebab-case for route URIs
15. **ConstructorDependencyInjectionProphet** - Thou shalt inject dependencies via constructor
16. **NoInlineBootLogicProphet** - Thou shalt not inline boot logic
17. **ComputedPropertyMustHookProphet** - Thou shalt hook computed properties
18. **QueryModelsThroughQueryMethodProphet** - Thou shalt query models through ::query()

### Frontend (Vue/TypeScript)

1. **NoFetchAxiosProphet** - Thou shalt not use fetch() or axios directly
2. **TemplateVForProphet** - Thou shalt wrap v-for in template elements
3. **TemplateVIfProphet** - Thou shalt wrap v-if/v-else in template elements
4. **RouterHardcodedUrlsProphet** - Thou shalt not hardcode URLs in router calls
5. **WayfinderRoutesProphet** - Thou shalt not hardcode URLs in href attributes
6. **CompositionApiProphet** - Thou shalt use Composition API
7. **ArrowFunctionAssignmentsProphet** - Thou shalt use named function declarations
8. **SwitchCaseProphet** - Thou shalt not use switch statements
9. **LongVueFilesProphet** - Thou shalt keep Vue files concise
10. **LongTsFilesProphet** - Thou shalt keep TypeScript files concise
11. **RepeatingPatternsProphet** - Thou shalt not repeat template patterns
12. **ScriptFirstProphet** - Thou shalt put script before template
13. **PropsTypeScriptProphet** - Thou shalt type props with TypeScript
14. **EmitsTypeScriptProphet** - Thou shalt type emits with TypeScript
15. **InlineEmitTransformProphet** - Thou shalt not transform data in emit handlers
16. **InlineTypeCastingProphet** - Thou shalt not type cast in templates
17. **WatchIfPatternProphet** - Thou shalt not use watch with if conditions
18. **PageDataAccessProphet** - Thou shalt use typed page props
19. **DeepNestingProphet** - Thou shalt not deeply nest template elements
20. **StyleOverridesProphet** - Thou shalt not override child component styles
21. **ExplicitDefaultSlotProphet** - Thou shalt use explicit default slots
22. **MultipleSlotDefinitionsProphet** - Thou shalt type slots with defineSlots
23. **ConditionalArrayBuildingProphet** - Consider disabled flags pattern for array building
24. **SwitchCheckboxVModelProphet** - Thou shalt use v-model on Switch/Checkbox
25. **LoopsWithIndexedStateProphet** - Review indexed state in loops (requires confession)
26. **ContentLikePropsProphet** - Review content-like props (requires confession)
27. **InlineDialogProphet** - Review inline dialog definitions (requires confession)

## Creating Custom Prophets

### Basic PHP Prophet

```php
<?php

namespace App\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

class NoMagicNumbersProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Thou shalt not use magic numbers';
    }

    public function detailedDescription(): string
    {
        return 'Magic numbers should be extracted to named constants...';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->skip('Unable to parse PHP file');
        }

        // Your judgment logic here...

        return $this->righteous();
    }
}
```

### Using the Pipeline API

The package provides fluent pipeline APIs for both PHP and Vue file analysis.

#### PHP Pipeline (PhpPipeline)

```php
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

public function judge(string $filePath, string $content): Judgment
{
    return PhpPipeline::make($filePath, $content)
        ->onlyControllers()  // Filter to Laravel controllers only
        ->pipe(ExtractMethods::class)
        ->pipe(FilterPrivateMethod::class)
        ->mapToSins(fn ($ctx) => /* create sins */)
        ->judge();
}
```

**Available helper methods:**

| Method | Description |
|--------|-------------|
| `onlyControllers()` | Filter to Laravel controller classes |
| `onlyDataClasses()` | Filter to Laravel Data classes |
| `onlyFormRequestClasses()` | Filter to FormRequest classes |
| `returnRighteousIfNoClass()` | Return righteous if no class found |
| `returnRighteousWhenClassHasAttribute($attr)` | Skip if class has specific attribute |

#### Vue Pipeline (VuePipeline)

```php
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

public function judge(string $filePath, string $content): Judgment
{
    return VuePipeline::make($filePath, $content)
        ->inTemplate()  // Extract template, return righteous if not found
        ->matchAll('/pattern/')
        ->sinsFromMatches('Message', 'Suggestion')
        ->judge();
}
```

**Available helper methods:**

| Method | Description |
|--------|-------------|
| `inTemplate()` | Extract template section, return righteous if not found |
| `inScript()` | Extract script section, return righteous if not found |
| `onlyPageFiles()` | Filter to files in Pages/ directory |
| `onlyComponentFiles()` | Filter to files in Components/ directory |
| `excludePartialFiles()` | Exclude files in Partials/ directory |
| `matchAll($pattern)` | Find all regex matches in current section |
| `sinsFromMatches($msg, $suggestion)` | Convert matches to sins |
| `mapToSins($callback)` | Custom sin mapping |
| `mapToWarnings($callback)` | Custom warning mapping |

### Accessing Configuration

Prophets can access their configuration via `$this->config()`:

```php
class MyProphet extends PhpCommandment
{
    public function judge(string $filePath, string $content): Judgment
    {
        $maxLines = (int) $this->config('max_lines', 100);
        $patterns = $this->config('allowed_patterns', []);

        // Use config values...
    }
}
```

Then configure in `config/commandments.php`:

```php
'prophets' => [
    MyProphet::class => [
        'max_lines' => 150,
        'allowed_patterns' => ['/^custom-/'],
    ],
],
```

## Vue/TypeScript Auto-fixing

For Vue and TypeScript auto-fixing, install the Node.js dependencies:

```bash
cd vendor/jessegall/code-commandments/node
npm install
```

## Testing

```bash
./vendor/bin/phpunit
```

## License

MIT License
