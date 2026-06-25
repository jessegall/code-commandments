# Code Commandments

**This is a personal tool I built for my own projects.** It's public in case others find it useful or want to fork it for their own coding standards.

A PHP package that enforces coding standards through automated validation, designed specifically for **Claude Code** (Anthropic's AI coding assistant). Works as a standalone CLI tool or as a Laravel package.

## Why This Exists

When working with Claude Code, I needed a way to:
1. **Teach Claude my coding standards** - So it writes code the way I want from the start
2. **Automatically check Claude's output** - Catch violations immediately after Claude makes changes
3. **Provide actionable feedback** - Give Claude clear instructions on what to fix

This package hooks into Claude Code's hook system to create a feedback loop:

```
┌─────────────────────────────────────────────────────────────────┐
│                     Claude Code Session                         │
├─────────────────────────────────────────────────────────────────┤
│  1. Session starts                                              │
│     └─> Hook runs: commandments:scripture                       │
│         └─> Claude learns all coding rules                      │
│                                                                 │
│  2. Claude writes/modifies code                                 │
│                                                                 │
│  3. After changes complete                                      │
│     └─> Hook runs: commandments:judge --git                     │
│         └─> Claude sees any violations                          │
│         └─> Claude fixes them automatically                     │
│                                                                 │
│  4. Repeat until code is "righteous" (no violations)            │
└─────────────────────────────────────────────────────────────────┘
```

All output is plain text optimized for AI assistants - concise, actionable, no decorative ASCII art.

## Installation

```bash
composer require --dev jessegall/code-commandments
```

### Laravel

Publish the configuration file:

```bash
php artisan vendor:publish --tag=commandments-config
```

### Standalone (non-Laravel)

Run the init command to set up everything at once (config file, Claude Code hooks, CLAUDE.md):

```bash
vendor/bin/commandments init
```

Then edit the generated `commandments.php` to define your scrolls using `__DIR__`-based paths:

```php
return [
    'scrolls' => [
        'backend' => [
            'path' => __DIR__ . '/src',
            'extensions' => ['php'],
            'prophets' => [
                \JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet::class,
            ],
        ],
    ],
];
```

#### Auto-detect mode

Use `--auto-detect` to automatically scan your project structure and generate a working config:

```bash
vendor/bin/commandments init --auto-detect
```

This scans the current directory and its immediate subdirectories, detects project types (PHP and/or frontend), and generates `commandments.php` with appropriate scrolls, paths, and all available prophets pre-configured.

Detection rules:
- **PHP**: directory has `composer.json` and an `app/` or `src/` folder containing `.php` files
- **Frontend**: directory has `package.json` and contains `.vue`, `.ts`, or `.tsx` files

For monorepos with multiple subprojects, each detected project gets its own scrolls (e.g. `api-backend`, `dashboard-frontend`).

Run commands via the `commandments` binary:

```bash
vendor/bin/commandments judge
vendor/bin/commandments repent
vendor/bin/commandments scripture
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

Every command is available via both Laravel artisan
(`php artisan commandments:<cmd>`) and the standalone CLI
(`vendor/bin/commandments <cmd>`). The standalone CLI also accepts
`--config=<path>` to point at a custom config file.

<!-- The tables below are AUTO-GENERATED from the console command definitions by
     `composer readme` — do not edit by hand. -->

<!-- AUTOGEN:COMMANDS:START -->

| Command | Purpose |
|---|---|
| [`abandon`](#abandon) | Leave the current pilgrimage early (judge/repent return; the push gate still enforces sins) |
| [`absolve`](#absolve) | Absolve a single finding (warning OR sin) by fingerprint/location, with a required reason |
| [`autofix`](#autofix) | Auto-fix the CURRENT pilgrimage prophet ([AUTO-FIXABLE] findings only), in place |
| [`feature-request`](#feature-request) | Propose a NEW rule / enhancement as a GitHub issue (no finding needed; allowed mid-pilgrimage) |
| [`init`](#init) | Initialize code commandments for a standalone project |
| [`install-skills`](#install-skills) | Install the Code Commandments skills into .claude/skills/ |
| [`install-sync-hook`](#install-sync-hook) | Install a git post-merge hook that auto-runs `sync --after=previous` when composer.lock changes |
| [`judge`](#judge) | Judge the codebase for sins against the commandments |
| [`migrate-config`](#migrate-config) | Convert a legacy array-style prophets list to the fluent ProphetClass::make()-&gt;… form |
| [`next`](#next) | Advance the pilgrimage to the next finding (forward-only) |
| [`pilgrimage`](#pilgrimage) | Begin the forward-only doctrine walk (resets state; `next` advances it) |
| [`profile`](#profile) | Show, list, or switch the active code-commandments profile (disabled/grind/phased/sins-only) |
| [`repent`](#repent) | Auto-fix findings that can be automatically resolved — sins and [AUTO-FIXABLE] warnings (no severity bump needed) |
| [`report`](#report) | Report a prophet false-positive / wrong rule as a GitHub issue (to PROPOSE a new rule, use `commandments feature-request`) |
| [`reports`](#reports) | Show the status of prophet reports this project filed (resolved upstream yet?) |
| [`scaffold`](#scaffold) | Generate recommended support classes (Option, FromArrayOnly, …) into your namespace |
| [`scripture`](#scripture) | List all commandments and their descriptions |
| [`skills`](#skills) | List the available Code Commandments skills (what they teach + where to read) |
| [`sync`](#sync) | Add newly available prophets to your config file |
| [`todo`](#todo) | List the still-unresolved file:line locations for the current pilgrimage prophet (compact; does not advance) |
| [`update`](#update) | Stay current: wire the composer lifecycle scripts, then sync prophets / scaffold / skills / hooks |

### `abandon`

Leave the current pilgrimage early (judge/repent return; the push gate still enforces sins).

### `absolve`

Absolve a single finding (warning OR sin) by fingerprint/location, with a required reason.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--fingerprint` | `<value>` | The finding fingerprint shown by judge --next |
| `--at` | `<value>` | Target a finding by location instead of a fingerprint — path:line (or path:from-to), exactly as judge prints it; combine with --prophet to disambiguate ties |
| `--reason` | `<value>` | Why the rule does not apply / is consciously accepted here (required; sins included) |
| `--all` | — | Baseline the queue: absolve every current advisory finding at once (sins still block) |
| `--warnings` | — | Batch-absolve every WARNING in scope under one --reason; hard-refuses if any sin is in scope (absolves nothing) |
| `--scope` | `<value>` | Limit --warnings to changed files: "git" (vs tracked state) or "staged" (the index) |
| `--prophet` | `<value>` | Limit --warnings to one prophet (partial name match), e.g. --prophet=DuplicateCode — one scan, not one-per-finding |
| `--clear` | — | Remove every ordinary absolution (post-commit reset so nothing stays hidden); report-linked absolutions persist until their issue is answered |

### `autofix`

Auto-fix the CURRENT pilgrimage prophet ([AUTO-FIXABLE] findings only), in place.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--scroll` | `<value>` | Scroll to walk |
| `--dry-run` | — | Show what would be fixed without making changes |

### `feature-request`

Propose a NEW rule / enhancement as a GitHub issue (no finding needed; allowed mid-pilgrimage).

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--stdin` | — | Read the proposal body from STDIN (robust for multi-paragraph text — no shell-quoting) |
| `--reason-file` | `<value>` | Read the proposal body from a file (alternative to the positional text / --stdin) |
| `--title` | `<value>` | Short issue title; defaults to a summary of the proposal |
| `--proposed-prophet` | `<value>` | Proposed name for the new prophet you are suggesting |
| `--rubric` | `<value>` | Proposed APPLY/LEAVE rubric for the suggested rule |
| `--repo` | `<value>` | GitHub repo (owner/name) to file the issue on |

### `init`

Initialize code commandments for a standalone project.

| Flag | Argument | Description |
|---|---|---|
| `--force` | — | Overwrite existing files |
| `--auto-detect` | — | Auto-detect projects and generate config |

### `install-skills`

Install the Code Commandments skills into .claude/skills/.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--force` | — | Overwrite existing skill files |
| `--auto` | — | Refresh only when skills.auto_refresh is enabled (session-start hook); otherwise do nothing |

### `install-sync-hook`

Install a git post-merge hook that auto-runs `sync --after=previous` when composer.lock changes.

| Flag | Argument | Description |
|---|---|---|
| `--force` | — | Overwrite an existing post-merge hook |

### `judge`

Judge the codebase for sins against the commandments.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--scroll` | `<value>` | Filter by specific scroll (group) |
| `--prophet` | `<value>` | Summon a specific prophet by name |
| `--file` | `<value>` | Judge a specific file |
| `--files` | `<value>` | Judge specific files (comma-separated) |
| `--path` | `<value>` | Override the scroll path and target a specific directory (bypasses all excludes — use to scan subtrees regardless of config) |
| `--git` | — | Only judge files that are new or changed in git |
| `--staged` | — | Only judge files staged for commit (what the pre-commit gate uses) |
| `--branch` | — | Judge everything changed since the branch base, INCLUDING committed work (survives intermediate commits — the grind reckoning) |
| `--no-profile` | — | Ignore the active profile for this run: scan the WHOLE scroll and show warnings, regardless of the profile (audit the full codebase) |
| `--absolve` | — | Mark files as absolved after confession |
| `--no-cache` | — | Force a fresh judge — never read the findings cache (the pre-commit gate uses this to stay authoritative) |
| `--no-parallel` | — | Judge sequentially (no forked workers) — use on a platform without pcntl or to debug |
| `--next` | — | Show exactly one finding at a time (fix or absolve to advance) |
| `--plan` | — | Print the remediation roadmap: every finding ordered root-cause-first as a numbered checklist (the penance plan) |
| `--gate-probe` | — | INTERNAL: run a fresh scan only for its exit code (used by the pre-push / Stop gates). Bypasses the pilgrimage lock but suppresses the findings report, so it is no use for browsing |

### `migrate-config`

Convert a legacy array-style prophets list to the fluent ProphetClass::make()-&gt;… form.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to commandments.php config file |
| `--write` | — | Rewrite the config file IN PLACE (a .bak backup is kept); otherwise only print + write a reference file |
| `--out` | `<value>` | Where to write the reference file (default: alongside the config) |

### `next`

Advance the pilgrimage to the next finding (forward-only).

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to commandments.php config file |
| `--scroll` | `<value>` | Scroll to walk |

### `pilgrimage`

Begin the forward-only doctrine walk (resets state; `next` advances it).

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to commandments.php config file |
| `--scroll` | `<value>` | Scroll to walk |
| `--is-complete` | — | INTERNAL: exit 0 only if THIS session has genuinely walked the whole pilgrimage (the pre-push gate uses this to grant a completed walk one push). Recomputed from the cursor — a hand-written complete flag does not pass |
| `--clear` | — | INTERNAL: discard the pilgrimage state (the pre-push gate consumes a completed walk so the next push re-arms the gate) |

### `profile`

Show, list, or switch the active code-commandments profile (disabled/grind/phased/sins-only).

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--brief` | — | Print the active profile briefing (session-start hook) |
| `--drift-check` | — | Re-brief only when the profile changed (per-turn hook) |

### `repent`

Auto-fix findings that can be automatically resolved — sins and [AUTO-FIXABLE] warnings (no severity bump needed).

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--scroll` | `<value>` | Filter by specific scroll (group) |
| `--prophet` | `<value>` | Use a specific prophet for repentance |
| `--file` | `<value>` | Repent sins in a specific file |
| `--files` | `<value>` | Repent sins in specific files (comma-separated) |
| `--git` | — | Force working-tree scope. Bare repent otherwise adopts the active profile scope (like judge) |
| `--input` | `<value>` | Input for a parameterized fixer, repeatable: --input key=value |
| `--dry-run` | — | Show what would be fixed without making changes |

### `report`

Report a prophet false-positive / wrong rule as a GitHub issue (to PROPOSE a new rule, use `commandments feature-request`).

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--prophet` | `<value>` | The prophet that misbehaved (name or class) |
| `--reason` | `<value>` | What is wrong (false positive / wrong rule / unclear) — or, with --feature-request, what to build and why |
| `--file` | `<value>` | File where it was flagged |
| `--line` | `<value>` | Line number |
| `--fingerprint` | `<value>` | The finding fingerprint from `judge --next` — records a report-linked absolution so the finding stays quiet until the issue is answered |
| `--at` | `<value>` | Target the finding by location instead of a fingerprint — path:line (or path:from-to), exactly as judge prints it; records the report-linked absolution and infers --prophet/--file/--line. Combine with --prophet to disambiguate ties |
| `--feature-request` | — | DEPRECATED — moved to `commandments feature-request "&lt;text&gt;"`. Still works for one release, then removed |
| `--title` | `<value>` | (deprecated feature-request) Short issue title; defaults to a summary of --reason |
| `--proposed-prophet` | `<value>` | (deprecated feature-request) Proposed name for a new prophet you are suggesting |
| `--rubric` | `<value>` | (deprecated feature-request) Proposed APPLY/LEAVE rubric for the suggested rule |
| `--repo` | `<value>` | GitHub repo (owner/name) to file the issue on |

### `reports`

Show the status of prophet reports this project filed (resolved upstream yet?).

| Flag | Argument | Description |
|---|---|---|
| `--check` | — | Quiet hook mode: print only newly-resolved reports |

### `scaffold`

Generate recommended support classes (Option, FromArrayOnly, …) into your namespace.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--force` | — | Overwrite existing support classes |
| `--auto` | — | Refresh only when scaffold.auto_refresh is enabled (session-start hook); otherwise do nothing |

### `scripture`

List all commandments and their descriptions.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to config file |
| `--scroll` | `<value>` | Filter by specific scroll (group) |
| `--prophet` | `<value>` | Show details for a specific prophet |
| `--detailed` | — | Show full descriptions with examples |

### `skills`

List the available Code Commandments skills (what they teach + where to read).

### `sync`

Add newly available prophets to your config file.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to commandments.php config file |
| `--after` | `<value>` | Override the floor: only add prophets introduced after this version (e.g. 1.4.0), or `previous` for the last synced version. |
| `--all` | — | OPT OUT of removal-respecting sync: add EVERY available prophet missing from the config (initial setup / deliberate full re-sync). Without this, sync NEVER re-adds a prophet you removed. |
| `--dry-run` | — | Show what would be added without modifying the file |

### `todo`

List the still-unresolved file:line locations for the current pilgrimage prophet (compact; does not advance).

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to commandments.php config file |
| `--scroll` | `<value>` | Scroll to walk |

### `update`

Stay current: wire the composer lifecycle scripts, then sync prophets / scaffold / skills / hooks.

| Flag | Argument | Description |
|---|---|---|
| `--config`, `-c` | `<value>` | Path to commandments.php config file |

<!-- AUTOGEN:COMMANDS:END -->

### Summon a New Prophet (Laravel only)

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

#### Automated Hooks

Claude Code supports [hooks](https://docs.anthropic.com/en/docs/claude-code/hooks) - shell commands that run at specific points during a session. This package leverages hooks to create an automated feedback loop.

**Laravel projects:**

```bash
php artisan commandments:install-hooks
```

**Standalone projects** (included in `init`, or run separately):

```bash
vendor/bin/commandments init
```

Both commands create `.claude/settings.json` with hooks and a `CLAUDE.md` file. The standalone version uses `vendor/bin/commandments` instead of `php artisan`.

Example hooks configuration:

```json
{
  "hooks": {
    "SessionStart": [
      {
        "hooks": [
          {
            "type": "command",
            "command": "vendor/bin/commandments scripture 2>/dev/null || true"
          }
        ]
      }
    ],
    "Stop": [
      {
        "hooks": [
          {
            "type": "command",
            "command": "vendor/bin/commandments judge --git 2>/dev/null; exit 0"
          }
        ]
      }
    ]
  }
}
```

**How it works:**

| Hook | When it runs | What it does |
|------|--------------|--------------|
| `SessionStart` | When Claude Code session begins | Shows all commandments so Claude knows the rules |
| `Stop` | After Claude finishes a response | Runs `judge --git` to check changed files |

The `--git` flag ensures only new/modified files are checked, keeping feedback focused and fast.

**Typical workflow:**

1. You ask Claude to implement a feature
2. Claude writes the code
3. Hook automatically runs `commandments:judge --git`
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

### Per-Prophet Exclusions

You can exclude specific paths for individual prophets. This is useful when a rule shouldn't apply to certain files:

```php
'prophets' => [
    // Exclude legacy controllers from this rule
    Backend\ConstructorDependencyInjectionProphet::class => [
        'exclude' => ['Http/Controllers/Legacy', 'Http/Controllers/Api/V1'],
    ],

    // Combine exclusions with other config options
    Backend\ControllerPrivateMethodsProphet::class => [
        'max_private_methods' => 3,
        'exclude' => ['Http/Controllers/ReportController.php'],
    ],
],
```

The exclusion uses substring matching - if the path contains any of the excluded strings, that prophet will skip the file.

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

<!-- The tables below are AUTO-GENERATED from each prophet's `description()` by
     `composer readme` — do not edit by hand. Add a prophet and it appears here. -->

<!-- AUTOGEN:PROPHETS:START -->

### Backend (PHP)

_106 prophets._

| Prophet | Auto-fix | What it enforces |
|---|---|---|
| `AnchorEnumComparisonProphet` | Yes | Anchor a CompareSelf set comparison on the non-null enum instance instead of the static form |
| `BehaviouralEnumDispatchProphet` | — | Extract a wide behavioural per-enum-case dispatch into strategy objects + a registration map |
| `ComputedPropertyMustHookProphet` | — | Computed properties must use property hooks instead of constructor assignment |
| `ConfigKeyContractProphet` | — | A config() read must target a declared config key — a missing path is a silent null |
| `ConstantsAndPropertiesFirstProphet` | Yes | Declare all constants and properties at the top of the class, before any methods |
| `ConstructorDependencyInjectionProphet` | — | Move service dependencies from controller methods to constructor |
| `ControllerPrivateMethodsProphet` | — | Extract private methods to service classes when controller exceeds limit |
| `DataClassFromArrayOnlyProphet` | Yes | Every Data class must use the FromArrayOnly trait |
| `DataClumpToValueObjectProphet` | — | 3+ values that always travel together across calls should be a value object |
| `DeadProducerProphet` | — | A private method that returns a value nobody uses should be void (or its callers should use the result) |
| `DemeterEndpointReachProphet` | — | Ask the owner with its intent method instead of reaching through $x-&gt;endpoint-&gt;field to branch (Law of Demeter) |
| `DuplicateCodeProphet` | — | Extract duplicated code fragments instead of copy-pasting a method body |
| `EagerRegistryProphet` | — | A registry is eagerly hydrated + read-only — lookups must not lazily build or populate-on-miss |
| `EncapsulateModelMutationProphet` | — | Flag direct model attribute writes followed by save() — encapsulate the change as a named behaviour method on the model |
| `EnumCaseMustBeDocumentedProphet` | — | Every enum case must have a descriptive doc comment above it explaining what the case is for |
| `ExplicitDataFactoryProphet` | Yes | Keep Data construction explicit — from() takes an array; map objects in named forX() factories |
| `FeatureEnvyProphet` | — | Move a query over another object's internals onto that object (tell-don't-ask) |
| `FormRequestTypedGettersProphet` | — | Add explicit return types to FormRequest getter methods |
| `HardcodedLiteralShouldBeConfigProphet` | — | A literal hardcoded into a consumer that reads it from config elsewhere should read from config too |
| `KebabCaseRoutesProphet` | — | Route URIs must use kebab-case |
| `LongDocblockProphet` | — | Keep docblocks to one short narrative sentence above the @-tag block |
| `LongMethodProphet` | — | Keep methods short and focused on a single responsibility |
| `MigrationModelDriftProphet` | — | A typed migration column (json/bool/datetime/decimal) must have a matching model cast |
| `MixedConfigValueUsedTypedProphet` | — | An env()-backed config value strict-compared to a number is always false — cast it first |
| `NoArrayBagProphet` | — | Do not pass array&lt;string, mixed&gt; bags around — give the bag a Fluent value class |
| `NoArrayStringIndexingProphet` | — | Prefer typed DTOs over string-indexed arrays for structured data |
| `NoAuthUserInDataClassesProphet` | — | Use #[FromAuthenticatedUser] attribute instead of auth()-&gt;user() in Data classes |
| `NoCoalesceOnNonNullableProphet` | — | Do not `??`-coalesce a value that is never null — the default is dead |
| `NoCompactProphet` | — | Do not bridge variables and arrays by name with compact()/extract() |
| `NoConditionalArraySpreadProphet` | — | Assemble conditional array shapes with a builder, not a spread of a ternary with an empty arm |
| `NoContainerResolutionProphet` | — | Prefer constructor injection over container resolution (app(), resolve(), App::make()) |
| `NoDirectRequestInputProphet` | — | Use typed FormRequest getters instead of direct request data access |
| `NoExternalDataFromProphet` | Yes | Keep Spatie ::from() inside the Data class — no custom from* factories, no external ::from() |
| `NoFacadesInServicesProphet` | — | Do not call Laravel facades in services — inject the underlying contract via the constructor |
| `NoInlineBootLogicProphet` | — | Model boot hooks should only dispatch events, not contain business logic |
| `NoInlineParamDocProphet` | Yes | Declare a parameter type with @param on the function docblock, not an inline /** @var */ |
| `NoInlineValidationProphet` | — | Move validation to FormRequest instead of inline $request-&gt;validate() |
| `NoJsonResponseProphet` | — | Use Inertia responses instead of JSON in web controllers |
| `NoManualHydrationProphet` | — | Do not hand-roll array-to-object hydration — extend Spatie Data and use ::from() |
| `NoNullCoalesceToNullProphet` | Yes | Drop the no-op `?? null` — it returns the left side unchanged |
| `NoOptionInUnionProphet` | — | Do not union Option with other types or null — Option is the whole type |
| `NoOptionToNullProphet` | Yes | Do not unwrap an Option back to null with unwrapOr(null) |
| `NoRawLiteralProphet` | Yes | Do not write raw magic literals (empties, newlines, …) — name them with T_String / T_Json / T_Array / T_Int |
| `NoRawRequestProphet` | — | Use FormRequest classes instead of raw Request in controllers |
| `NoRedundantDefaultArgumentProphet` | Yes | Do not pass an argument equal to the parameter default — the default is already applied |
| `NoRepeatedHydrationProphet` | — | Do not re-hydrate the same field with ::from() — declare it as the type so it hydrates once |
| `NoRequestDataPassthroughProphet` | — | Inject request in Data class instead of passing computed values to from() |
| `NoSwallowedNotFoundProphet` | — | Do not catch a not-found exception just to swallow it into null/false/[] — let it throw |
| `NoValidatedMethodProphet` | — | Use typed getters instead of $request-&gt;validated() |
| `OneRulePerFilterProphet` | — | A filter()/reject() closure should hold ONE rule — split an && chain, and say !(…) as reject(…) |
| `OptionDisciplineProphet` | — | Model absence exactly when it is real — adopt Option for value-or-nothing, never for always-value |
| `OutOfPurposeProphet` | — | A class with a role marker (*Registry/*Data/*Resolver) whose body shows a structural second-engine signal (reflection in a registry, an assembler cluster in a DTO, a store in a resolver) is doing a second job — extract it |
| `PassThroughDependencyProphet` | — | A dependency only forwarded to one collaborator, never used itself, should be injected there |
| `PreferClassifierCompositionProphet` | — | Compose classifier checks with anyOf()/allOf(), not a \|\|/&& chain of -&gt;matches() |
| `PreferCoalesceFactoryProphet` | — | Hoist new ValueObject($nullableOrLoose) ceremony into a total ::coalesce() factory |
| `PreferCoalesceForProphet` | Yes | Use T_Array::coalesceFor($array, $key) instead of double-coalescing a dynamic dictionary lookup |
| `PreferCoalescingFactoryProphet` | — | Build a wrapper via a total/coalescing factory, not `cond ? new T(...) : null` + null-guards |
| `PreferCoercionHelperProphet` | Yes | Extract a repeated inline cast-with-fallback (is_x($v) ? (cast) $v : default) into a named coercion helper |
| `PreferCollectionPipelineProphet` | — | Prefer a Collection chain over nested array_* compositions (they read inside-out) |
| `PreferConfigDrivenRegistryProphet` | — | An enum whose cases mirror a config-registered set should be driven by a config registry |
| `PreferDataCollectionOfProphet` | — | Do not hand-roll a Data collection with ::from() in a loop — use #[DataCollectionOf] / ::collect() |
| `PreferDataTransformersProphet` | — | Serialize Data objects through -&gt;toArray()/transformers, not a hand-rolled mapping |
| `PreferDefaultFallbackProphet` | — | Move a call-site presence-check-then-fallback into the callee as a default parameter |
| `PreferDefaultOverNullableProphet` | — | Prefer a $default parameter over a nullable/Option when every caller substitutes a fixed fallback |
| `PreferEmptyOverNullProphet` | — | Return an empty collection/bag instead of null — an empty instance is the absence |
| `PreferEnumCaseGroupsProphet` | — | Name reused subsets of an enum on the enum — do not re-inline the same case-group |
| `PreferEnumForClosedSetFieldProphet` | Yes | Suggest an enum for a string field whose name denotes a closed set |
| `PreferFirstClassCallableProphet` | — | A forwarding closure should be a first-class callable — fn ($x) =&gt; f($x) is f(...) |
| `PreferInjectionOverSingletonProphet` | — | Prefer dependency injection over a hand-rolled singleton |
| `PreferInterfaceOverTypeListProphet` | — | Classify via a marker interface or the AST, not a hardcoded list of type names |
| `PreferNamedBranchFactoryProphet` | — | Extract a non-trivial -&gt;then() branch factory into a named *Factory method returning callable |
| `PreferNamedExceptionsProphet` | — | Do not pass message strings at throw sites — throw named exceptions via static factories |
| `PreferNativeEnumProphet` | — | Prefer a native enum over a hand-rolled constant class |
| `PreferNativeTypedAccessorProphet` | Yes | Use the receiver's native typed accessor instead of coercing its untyped get() |
| `PreferNullCoalescingProphet` | — | Use `??` (or Option::unwrapOr) instead of a self-fallback ternary |
| `PreferNullObjectDefaultsProphet` | Yes | Prefer Null Object defaults over nullable params normalized in the body |
| `PreferSprintfProphet` | Yes | Prefer sprintf() over string interpolation — separate the template from its values |
| `PreferStaticOverInvokableConstructProphet` | — | Prefer a static factory over (new X(...))(...) for project-owned classes |
| `PreferTotalOverNullableProphet` | — | Make a method total or throw when every caller already de-nulls its nullable return |
| `PreferTypeCoalesceProphet` | Yes | Prefer T_*::coalesce() over `?? &lt;empty literal&gt;` on a nullable typed value |
| `PreferTypeMethodOverInlineDispatchProphet` | — | Move per-case dispatch and type-constant mappings onto the type, not inline at the call site |
| `PreferTypedBoundaryProphet` | — | Type values at the deserialization boundary instead of leaking `mixed` for consumers to re-coerce. |
| `PreferYieldOverAccumulatorProphet` | — | Prefer returning / yielding typed results over threading a write-only accumulator parameter through a class |
| `PushGenericToSourceProphet` | — | Push a type to its source @return instead of re-asserting it with a call-site @var |
| `QueryModelsThroughQueryMethodProphet` | Yes | Query models through the ::query() method instead of direct static calls |
| `ReadonlyDataPropertiesProphet` | — | Remove readonly from Data properties with value-injecting attributes like #[WithCast] |
| `RegistryBaseBypassProphet` | — | A Registry subclass that overrides all() to a private store leaves inherited register() dead |
| `RegistryNamingHonestyProphet` | — | A class shaped like a registry (register + keyed store + lookup) should be named *Registry and extend a base |
| `RegistryPatternProphet` | — | When several classes hand-roll the registry shape, extract a shared base (scaffold one) |
| `RegistryPurityProphet` | — | A registry stays a pure keyed store of its target type — resolution/query methods belong on a collaborator |
| `RegistryReturnContractProphet` | — | A registry returns the item or throws — not Option&lt;T&gt; or T \| null (with a has() companion) |
| `RepeatedFallbackProphet` | — | Do not copy-paste a fallback chain — hoist a repeated `?? / ?:` into a named static factory |
| `ResolverNamingHonestyProphet` | — | A *Resolver should do first-match dispatch (ideally via the kernel) — otherwise rename off the suffix |
| `ResolverPatternProphet` | — | Drive first-match dispatch and predicate code into the resolver + Predicate pattern |
| `SecretToLogOrResponseProphet` | — | SECURITY: a secret (config token/password, -&gt;password) must not be logged or dumped unredacted |
| `SetNamingHonestyProphet` | — | A class shaped like a set (add + iterate, no keyed lookup) should be named *Set and extend a base |
| `SetReturnContractProphet` | — | A set is a total, iterate-only collection — has(): bool, no Option/nullable leak, and no keyed get(string) lookup (that is a registry) |
| `ShortClosureProphet` | — | Keep anonymous functions short — extract a big closure to a named private method |
| `StringMatchMirrorsEnumProphet` | — | A match/switch over strings that mirror an enum's cases should dispatch on the enum |
| `StringsThatShouldBeEnumsProphet` | — | Use enum cases instead of raw string literals for closed-set values |
| `SuggestCompareSelfTraitProphet` | Yes | Use a CompareSelf-style trait helper instead of chained enum equality comparisons |
| `TaintedInputToSinkProphet` | — | SECURITY: request input must not reach raw SQL / exec / unserialize without sanitization |
| `ThrowOnUnhandledCaseProphet` | — | Throw a named exception for an unhandled closed-set case — do not model an invariant violation as null/Option |
| `TooManyParametersProphet` | — | Keep parameter lists short — group related parameters into an object |
| `TranslationKeyCongruenceProphet` | — | A __()/trans() key must exist in a lang file — a missing key renders as the key string |
| `WideUnionTypeProphet` | Yes | Avoid wide type unions — model value-or-nothing as an Option |

### Frontend (Vue / TypeScript)

_29 prophets._

| Prophet | Auto-fix | What it enforces |
|---|---|---|
| `ArrowFunctionAssignmentsProphet` | — | Prefer named function declarations over arrow function assignments |
| `CompositionApiProphet` | — | Use &lt;script setup&gt; Composition API instead of Options API |
| `ConditionalArrayBuildingProphet` | — | Consider disabled flags pattern instead of conditional array building |
| `ContentLikePropsProphet` | — | Consider using slots instead of content-like props |
| `DeepNestingProphet` | — | Avoid deeply nested templates - consider extracting components |
| `EmitsTypeScriptProphet` | — | Use TypeScript interface for defineEmits instead of runtime declaration |
| `ExplicitDefaultSlotProphet` | — | Use explicit &lt;template #default&gt; when using named slots |
| `InlineDialogProphet` | — | Extract inline dialog definitions to separate components |
| `InlineEmitTransformProphet` | — | Avoid inline emit handlers with transformation logic in templates |
| `InlineMarkupProphet` | — | Avoid excessive native HTML markup - extract components instead |
| `InlineTypeCastingProphet` | — | Avoid inline type casting in template bindings |
| `KebabCasePropsProphet` | Yes | Props should be bound using kebab-case in templates |
| `LongTsFilesProphet` | — | TypeScript files in components should be under 200 lines |
| `LongVueFilesProphet` | — | Keep Vue files under 200 lines by extracting components |
| `LoopsWithIndexedStateProphet` | — | Extract loop items with indexed state to separate components |
| `MultipleSlotDefinitionsProphet` | — | Components with slots must use defineSlots for type safety |
| `NoFetchAxiosProphet` | — | Use Inertia requests instead of fetch/axios |
| `PageDataAccessProphet` | — | Page components should use PageData indexed access for prop types |
| `PropsTypeScriptProphet` | — | Use TypeScript interface for defineProps instead of runtime declaration |
| `RepeatingPatternsProphet` | — | Detect repeating patterns that could be extracted into reusable components or composables |
| `RouterHardcodedUrlsProphet` | — | Never hardcode URLs in router calls |
| `ScriptFirstProphet` | — | Thou shalt put script before template |
| `StyleOverridesProphet` | — | Avoid style/class overrides on base components - use semantic props instead |
| `SwitchCaseProphet` | — | Use SwitchCase component instead of v-if chains comparing the same variable |
| `SwitchCheckboxVModelProphet` | — | Use v-model for Switch/Checkbox components, not v-model:checked or :checked |
| `TemplateVForProphet` | Yes | Use &lt;template v-for&gt; wrapper instead of v-for on elements |
| `TemplateVIfProphet` | Yes | Thou shalt wrap v-if/v-else in template elements |
| `WatchIfPatternProphet` | — | Use whenever() instead of watch() with if condition |
| `WayfinderRoutesProphet` | — | Never hardcode URLs in href attributes |

<!-- AUTOGEN:PROPHETS:END -->

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
