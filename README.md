# Code Commandments

**This is a personal tool I built for my own projects. It's public in case others find it useful, but it's tailored to my specific coding standards and workflow.**

A Laravel package for enforcing code commandments through prophets who judge and absolve transgressions.

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

# Mark files as absolved after manual review
php artisan commandments:judge --absolve
```

### Seek Absolution (Auto-fix)

```bash
# Auto-fix all sins that can be absolved
php artisan commandments:repent

# Preview what would be fixed
php artisan commandments:repent --dry-run

# Fix a specific file
php artisan commandments:repent --file=app/Http/Controllers/UserController.php
```

### Read the Scripture (List Commandments)

```bash
# List all prophets
php artisan commandments:scripture

# List with detailed descriptions
php artisan commandments:scripture --detailed

# List prophets from a specific scroll
php artisan commandments:scripture --scroll=frontend
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

## Configuration

Configure your scrolls in `config/commandments.php`:

```php
return [
    'scrolls' => [
        'backend' => [
            'path' => app_path(),
            'extensions' => ['php'],
            'exclude' => ['Console/Kernel.php'],
            'thresholds' => [
                'max_method_lines' => 15,
            ],
            'prophets' => [
                \JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet::class,
                // Add more prophets...
            ],
        ],
        'frontend' => [
            'path' => resource_path('js'),
            'extensions' => ['vue', 'ts', 'js'],
            'thresholds' => [
                'max_vue_lines' => 200,
            ],
            'prophets' => [
                \JesseGall\CodeCommandments\Prophets\Frontend\CompositionApiProphet::class,
                // Add more prophets...
            ],
        ],
    ],

    'confession' => [
        'tablet_path' => storage_path('commandments/confessions.json'),
    ],
];
```

## Built-in Prophets

### Backend (PHP)

1. **NoRawRequestProphet** - Thou shalt not access raw request data
2. **NoJsonResponseProphet** - Thou shalt not return raw JSON responses
3. **NoHasMethodInControllerProphet** - Thou shalt not use has() in controllers
4. **NoEventDispatchProphet** - Thou shalt not dispatch events in controllers
5. **NoRecordThatOutsideAggregateProphet** - Thou shalt not use recordThat() outside aggregates
6. **NoValidatedMethodProphet** - Thou shalt not use validated() method
7. **NoInlineValidationProphet** - Thou shalt not use inline validation
8. **TypeScriptAttributeProphet** - Thou shalt use #[TypeScript] on Resources
9. **ReadonlyDataPropertiesProphet** - Thou shalt use readonly in Data classes
10. **FormRequestTypedGettersProphet** - Thou shalt use typed getters in FormRequest
11. **HiddenAttributeProphet** - Thou shalt hide sensitive model attributes
12. **NoCustomFromModelProphet** - Thou shalt not use custom fromModel methods

### Frontend (Vue/TypeScript)

1. **NoFetchAxiosProphet** - Thou shalt not use fetch() or axios directly
2. **TemplateVForProphet** - Thou shalt use :key with v-for
3. **TemplateVIfProphet** - Thou shalt not use v-if with v-for
4. **RouterHardcodedUrlsProphet** - Thou shalt not hardcode URLs
5. **CompositionApiProphet** - Thou shalt use Composition API
6. **ArrowFunctionAssignmentsProphet** - Thou shalt use const arrow functions
7. **SwitchCaseProphet** - Thou shalt not use switch statements
8. **LongVueFilesProphet** - Thou shalt keep Vue files concise
9. **LongTsFilesProphet** - Thou shalt keep TypeScript files concise
10. **RepeatingPatternsProphet** - Thou shalt not repeat template patterns
11. **ScriptFirstProphet** - Thou shalt put script before template
12. **PropsTypeScriptProphet** - Thou shalt type props with TypeScript
13. **EmitsTypeScriptProphet** - Thou shalt type emits with TypeScript
14. **InlineEmitTransformProphet** - Thou shalt not transform data in emit handlers
15. **InlineTypeCastingProphet** - Thou shalt not type cast in templates
16. **WatchIfPatternProphet** - Thou shalt not use watch with if conditions
17. **PageDataAccessProphet** - Thou shalt use typed page props
18. **DeepNestingProphet** - Thou shalt not deeply nest template elements
19. **StyleOverridesProphet** - Thou shalt not override child component styles
20. **ExplicitDefaultSlotProphet** - Thou shalt use explicit default slots
21. **MultipleSlotDefinitionsProphet** - Thou shalt not define duplicate slots
22. **ConditionalArrayBuildingProphet** - Thou shalt not build arrays with conditional push
23. **SwitchCheckboxVModelProphet** - Thou shalt use v-model on Switch/Checkbox
24. **LoopsWithIndexedStateProphet** - Review indexed state in loops (requires confession)
25. **ContentLikePropsProphet** - Review content-like props (requires confession)
26. **InlineDialogProphet** - Review inline dialog definitions (requires confession)

## Creating Custom Prophets

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
