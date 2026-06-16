<?php

use JesseGall\CodeCommandments\Prophets\Backend;
use JesseGall\CodeCommandments\Prophets\Frontend;

return [
    /*
    |--------------------------------------------------------------------------
    | Sacred Scrolls (Groups)
    |--------------------------------------------------------------------------
    |
    | Define the scrolls (groups) of commandments. Each scroll contains
    | prophets that judge specific parts of your codebase.
    |
    | The order of prophets in each scroll determines their commandment number.
    | First prophet = Commandment #1, second = Commandment #2, etc.
    |
    | Prophets can be configured using the associative array format:
    | ProphetClass::class => ['option' => 'value']
    |
    | You can also exclude specific paths per-prophet:
    | ProphetClass::class => ['exclude' => ['path/to/exclude', 'another/path']]
    |
    */

    'scrolls' => [
        'backend' => [
            'path' => app_path(),
            'extensions' => ['php'],
            'exclude' => [
                'Console/Kernel.php',
                'Exceptions/Handler.php',
            ],
            'prophets' => [
                Backend\NoRawRequestProphet::class,
                Backend\NoJsonResponseProphet::class,
                Backend\NoDirectRequestInputProphet::class,
                Backend\NoEventDispatchProphet::class,
                Backend\NoRecordThatOutsideAggregateProphet::class,
                Backend\NoValidatedMethodProphet::class,
                Backend\NoInlineValidationProphet::class,
                Backend\TypeScriptAttributeProphet::class,
                Backend\ReadonlyDataPropertiesProphet::class,
                Backend\FormRequestTypedGettersProphet::class,
                Backend\HiddenAttributeProphet::class,
                Backend\ControllerPrivateMethodsProphet::class => [
                    'max_private_methods' => 3,
                    'min_method_lines' => 3,
                ],
                Backend\KebabCaseRoutesProphet::class,
                Backend\ConstructorDependencyInjectionProphet::class,
                Backend\NoInlineBootLogicProphet::class,
                Backend\ComputedPropertyMustHookProphet::class,
                Backend\QueryModelsThroughQueryMethodProphet::class,
                Backend\NoRequestDataPassthroughProphet::class,
                Backend\NoAuthUserInDataClassesProphet::class,
                Backend\LongMethodProphet::class => [
                    // 'max_method_lines' => 20,
                ],
                Backend\NoRawLiteralProphet::class => [
                    // Always on: empty '' / JSON {} [] / matrix [[]].
                    // Whitespace (\n \n\n \t \r \r\n \0) is on by default.
                    // The rest are opt-in (off by default):
                    // 'flag_whitespace'    => true,  // \n \n\n \t \r \r\n \0
                    // 'flag_empty_array'   => true,  // []
                    // 'flag_space'         => true,  // ' '
                    // 'flag_separators'    => true,  // , ', ' / . -
                    // 'flag_sentinel_ints' => true,  // 0 1 -1
                    // 'flag_sentinel_floats' => true,  // 0.0
                    //
                    // Override the type-helper classes the fixer rewrites to:
                    // 'string_class' => 'JesseGall\\PhpTypes\\T_String',
                    // 'json_class' => 'JesseGall\\PhpTypes\\T_Json',
                    // 'array_class' => 'JesseGall\\PhpTypes\\T_Array',
                    // 'int_class' => 'JesseGall\\PhpTypes\\T_Int',
                ],
                Backend\NoArrayStringIndexingProphet::class,
                Backend\NoArrayBagProphet::class => [
                    // Extra method names whose array params/returns stay
                    // exempt (toArray, jsonSerialize, cast, rules, ... are
                    // built in):
                    // 'exempt_methods' => ['definition'],
                ],
                Backend\NoManualHydrationProphet::class => [
                    // 'min_key_reads' => 2,
                    // 'min_property_reads' => 3,
                ],
                Backend\NoRepeatedHydrationProphet::class => [
                    // 'min_occurrences' => 2,
                    // 'methods' => ['from'],
                    // 'severity' => 'warning',   // or 'sin'
                ],
                Backend\PreferDataCollectionOfProphet::class => [
                    // 'methods' => ['from'],
                    // 'severity' => 'warning',   // or 'sin'
                ],
                Backend\ExplicitDataFactoryProphet::class => [
                    // 'data_suffixes' => ['Data'],
                    // 'severity' => 'warning',   // or 'sin'
                ],
                Backend\DataClassFromArrayOnlyProphet::class => [
                    // 'trait_class' => App\Support\FromArrayOnly::class,
                    // 'base_class'  => Spatie\LaravelData\Data::class,
                ],
                Backend\PreferNamedExceptionsProphet::class => [
                    // Exception classes that may be thrown inline:
                    // 'allow' => ['InvalidArgumentException'],
                ],
                Backend\PreferSprintfProphet::class => [
                    // Opinionated/opt-in. Defaults shown:
                    // 'require_escape' => true,      // only flag interpolation that also has \n / \t / …
                    // 'extract_whitespace' => true,  // \n\n -> a T_String::PARAGRAPH arg
                    // 'min_interpolations' => 1,
                ],
                Backend\PreferOptionOverNullProphet::class => [
                    // Suggested wrapper for value-or-nothing returns:
                    // 'option_class' => 'App\\Support\\Option',
                    //
                    // Per-type sentinels: when a flagged method's return type
                    // matches a key, the suggestion becomes "return this Null
                    // Object" instead of "wrap in Option":
                    // 'null_objects' => [
                    //     'App\\Workflow\\PortRef' => 'App\\Workflow\\NullPortRef',
                    // ],
                    //
                    // 'exclude_methods' => ['try*', '__*'],
                    // 'severity' => 'warning', // or 'sin'
                ],
                Backend\StringsThatShouldBeEnumsProphet::class,
                Backend\RepeatedFallbackProphet::class => [
                    // How many identical occurrences before it's flagged:
                    // 'min_occurrences' => 2,
                    //
                    // Null-object classes whose fallbacks defer to the
                    // null-object prophet (the map's values are reused):
                    // 'null_objects' => [
                    //     'App\\Workflow\\PortRef' => 'App\\Workflow\\NullPortRef',
                    // ],
                ],
                Backend\PreferStaticOverInvokableConstructProphet::class,
                Backend\NoContainerResolutionProphet::class,
                Backend\PreferNullObjectDefaultsProphet::class => [
                    'null_objects' => [
                        'callable' => 'App\\Support\\NullCallable',
                        'Psr\\Log\\LoggerInterface' => 'Psr\\Log\\NullLogger',
                    ],
                ],
                Backend\LongDocblockProphet::class => [
                    // 'max_narrative_lines' => 3,
                ],
                Backend\SuggestCompareSelfTraitProphet::class => [
                    'trait' => 'App\\Support\\Enums\\CompareSelf',
                    // 'is_one_of_method' => 'isOneOf',
                    // 'is_not_one_of_method' => 'isNotOneOf',
                    // 'min_chain' => 2,
                    // 'exclude_enums' => [],
                ],
            ],
        ],

        'frontend' => [
            'path' => resource_path('js'),
            'extensions' => ['vue', 'ts', 'js', 'tsx', 'jsx'],
            'exclude' => [
                'node_modules',
                'dist',
                '*.d.ts',
            ],
            'prophets' => [
                Frontend\NoFetchAxiosProphet::class,
                Frontend\TemplateVForProphet::class,
                Frontend\TemplateVIfProphet::class,
                Frontend\WayfinderRoutesProphet::class,
                Frontend\RouterHardcodedUrlsProphet::class,
                Frontend\KebabCasePropsProphet::class,
                Frontend\CompositionApiProphet::class,
                Frontend\ArrowFunctionAssignmentsProphet::class,
                Frontend\SwitchCaseProphet::class,
                Frontend\LongVueFilesProphet::class => [
                    'max_vue_lines' => 200,
                ],
                Frontend\LongTsFilesProphet::class => [
                    'max_ts_lines' => 300,
                ],
                Frontend\RepeatingPatternsProphet::class,
                Frontend\ScriptFirstProphet::class,
                Frontend\PropsTypeScriptProphet::class,
                Frontend\EmitsTypeScriptProphet::class,
                Frontend\InlineEmitTransformProphet::class,
                Frontend\InlineTypeCastingProphet::class,
                Frontend\WatchIfPatternProphet::class,
                Frontend\PageDataAccessProphet::class,
                Frontend\DeepNestingProphet::class,
                Frontend\InlineMarkupProphet::class => [
                    'max_html_tags' => 15,
                ],
                Frontend\StyleOverridesProphet::class,
                Frontend\ExplicitDefaultSlotProphet::class,
                Frontend\MultipleSlotDefinitionsProphet::class,
                Frontend\ConditionalArrayBuildingProphet::class,
                Frontend\SwitchCheckboxVModelProphet::class,
                Frontend\LoopsWithIndexedStateProphet::class,
                Frontend\ContentLikePropsProphet::class,
                Frontend\InlineDialogProphet::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Confession Settings
    |--------------------------------------------------------------------------
    |
    | Configure how manual reviews (confessions) are tracked.
    |
    */

    'confession' => [
        'tablet_path' => storage_path('commandments/confessions.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scaffolding
    |--------------------------------------------------------------------------
    |
    | Support classes the prophets recommend (Option, FromArrayOnly trait,
    | NullCallable, …) generated into your namespace. New scaffolds introduced
    | by a package upgrade are created automatically on `sync` unless `auto`
    | is false. Existing files are never overwritten (run `commandments:scaffold
    | --force` to refresh). Add names to `except` to opt out of specific ones.
    |
    */

    'scaffold' => [
        'auto' => true,
        'namespace' => 'App\\Support',
        'path' => app_path('Support'),
        'except' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    |
    | Where `commandments:report` files prophet false-positives / wrong-rule
    | reports as GitHub issues (via the `gh` CLI). Defaults to the package repo
    | so prophet bugs land where the prophets are maintained.
    |
    */

    'report' => [
        'repo' => 'jessegall/code-commandments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Node.js Settings
    |--------------------------------------------------------------------------
    |
    | Settings for Vue/JS/TS auto-fixing using Node.js scripts.
    |
    */

    'node_path' => env('COMMANDMENTS_NODE_PATH', null),
];
