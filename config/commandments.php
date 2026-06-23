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
                Backend\NoExternalDataFromProphet::class => [
                    // 'data_suffixes' => ['Data'],
                    // 'severity' => 'sin',   // or 'warning'
                ],
                Backend\NoRedundantDefaultArgumentProphet::class,
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
                Backend\PreferOptionFactoryProphet::class,
                Backend\StringsThatShouldBeEnumsProphet::class,
                Backend\EnumCaseMustBeDocumentedProphet::class,
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
                Backend\PreferEnumCaseGroupsProphet::class => [
                    // Smallest inline group worth naming on the enum:
                    // 'min_group' => 3,
                    //
                    // How many sites inline the same group before it's "reused":
                    // 'min_reuse' => 2,
                    //
                    // Enums whose groups should never be flagged:
                    // 'exclude_enums' => [
                    //     'App\\Support\\SomeFlagEnum',
                    // ],
                ],
                Backend\PreferStaticOverInvokableConstructProphet::class,
                Backend\NoConditionalArraySpreadProphet::class,
                Backend\PreferTypeMethodOverInlineDispatchProphet::class,
                Backend\FeatureEnvyProphet::class,
                Backend\EncapsulateModelMutationProphet::class,
                Backend\PreferCollectionPipelineProphet::class,
                Backend\PreferFirstClassCallableProphet::class,
                Backend\NoInlineParamDocProphet::class,
                Backend\ConstantsAndPropertiesFirstProphet::class,
                Backend\NoFacadesInServicesProphet::class,
                Backend\UnwrapOptionWithGuardProphet::class,
                Backend\PreferAndThenProphet::class,
                Backend\PreferEmptyOverNullProphet::class,
                Backend\RegistryReturnContractProphet::class,
                Backend\EagerRegistryProphet::class,
                Backend\RegistryNamingHonestyProphet::class,
                Backend\RegistryPatternProphet::class,
                Backend\RegistryBaseBypassProphet::class,
                Backend\SetNamingHonestyProphet::class,
                Backend\SetReturnContractProphet::class,
                Backend\OutOfPurposeProphet::class => [
                    // Role-vs-behaviour incoherence: a class with a role MARKER
                    // (*Registry/*Data/*Resolver) whose body shows a STRUCTURAL
                    // second-engine signal for that role. The backbone is generic
                    // (reflection via the native `Reflection*` family, RegistryShape
                    // store-shape, DTO assembler clusters, constructor service
                    // injection, verb-cluster diversity) — it works on plain PHP.
                    // The `forbidden`/`forbidden_namespaces` below are OPTIONAL
                    // framework-specific sharpeners, never the sole trigger.
                    // Override the catalogue wholesale via `roles`:
                    // 'roles' => [
                    //     'registry' => [
                    //         'markers' => [
                    //             'suffix'    => ['Registry', 'Map', 'Catalog'],
                    //             'base'      => ['Registry'],
                    //             'interface' => ['Registry'],
                    //             'attribute' => ['Registry'],
                    //         ],
                    //         // OPTIONAL sharpeners — reflection fires generically even with these empty.
                    //         'forbidden'            => ['DOMDocument', 'PDO'],
                    //         'forbidden_namespaces' => ['Spatie\\StructureDiscoverer', 'Illuminate\\Database'],
                    //         'verbs'                => ['register', 'find', 'get', 'has', 'all'], // own vocabulary, excluded from the verb-cluster signal
                    //         'second_job'           => 'reflection/discovery or I/O',
                    //         'cut'                  => 'Extract it into a *Reflector collaborator; keep the registry a store + lookup.',
                    //     ],
                    // ],
                    // 'min_verb_families'  => 2,                      // secondary signal threshold (>= 2)
                    // 'exempt_bases'       => ['ServiceProvider'],
                    // 'exempt_suffixes'    => ['ServiceProvider'],
                    // 'exempt_attributes'  => ['OutOfPurposeExempt'],
                ],
                Backend\ResolverNamingHonestyProphet::class,
                Backend\PushGenericToSourceProphet::class,
                Backend\PreferNamedBranchFactoryProphet::class,
                Backend\BehaviouralEnumDispatchProphet::class,
                Backend\ThrowOnUnhandledCaseProphet::class,
                Backend\PreferTotalOverNullableProphet::class,
                Backend\NoSwallowedNotFoundProphet::class,
                Backend\DemeterEndpointReachProphet::class,
                Backend\PreferCoercionHelperProphet::class,
                Backend\PreferNativeTypedAccessorProphet::class,
                Backend\ShortClosureProphet::class,
                Backend\ResolverPatternProphet::class,
                Backend\DuplicateCodeProphet::class => [
                    // 'min_lines' => 5,
                ],
                Backend\PreferDataTransformersProphet::class => [
                    // 'data_base' => 'Spatie\\LaravelData\\Data',
                    // 'min_reads' => 3,
                ],
                Backend\PreferDefaultFallbackProphet::class => [
                    // 'presence_prefixes' => ['has', 'contains', 'includes', 'exists'],
                ],
                Backend\NoOptionToNullProphet::class => [
                    // Option accessor methods whose null default is the smell:
                    // 'methods' => ['getOr'],
                ],
                Backend\NoOptionOveruseProphet::class => [
                    // 'option_class' => App\Support\Option::class,
                ],
                Backend\NoOptionInUnionProphet::class => [
                    // 'option_class' => App\Support\Option::class,
                ],
                Backend\NoRedundantOrElseWrapProphet::class => [
                    // 'option_class' => App\Support\Option::class,
                    // 'methods' => ['orElse'],
                ],
                Backend\PreferOptionChainOverGuardProphet::class,
                Backend\PreferCoalesceFactoryProphet::class,
                Backend\PreferYieldOverAccumulatorProphet::class => [
                    // 'min_methods' => 3,   // fire only when >= N methods thread the same collector param
                ],
                Backend\WideUnionTypeProphet::class => [
                    // 'warn_at_types' => 2,   // 0 (or warnings_enabled => false) disables warnings
                    // 'sin_at_types'  => 3,
                ],
                Backend\PreferNullCoalescingProphet::class,
                Backend\NoNullCoalesceToNullProphet::class,
                Backend\PreferTypeCoalesceProphet::class,
                Backend\PreferCoalesceForProphet::class,
                Backend\PreferEnumForClosedSetFieldProphet::class => [
                    // 'names' => ['direction', 'status', 'kind', 'mode', 'type'],
                ],
                Backend\PreferNativeEnumProphet::class,
                Backend\PreferInjectionOverSingletonProphet::class,
                Backend\PreferConfigDrivenRegistryProphet::class,
                Backend\ConfigKeyContractProphet::class,
                Backend\MixedConfigValueUsedTypedProphet::class,
                Backend\HardcodedLiteralShouldBeConfigProphet::class,
                Backend\StringMatchMirrorsEnumProphet::class,
                Backend\PassThroughDependencyProphet::class,
                Backend\DataClumpToValueObjectProphet::class,
                Backend\MigrationModelDriftProphet::class,
                Backend\TranslationKeyCongruenceProphet::class,
                Backend\DeadProducerProphet::class,
                Backend\TaintedInputToSinkProphet::class,
                Backend\SecretToLogOrResponseProphet::class,
                Backend\PreferDefaultOverNullableProphet::class,
                Backend\RegistryPurityProphet::class,
                Backend\NoCompactProphet::class => [
                    // 'functions' => ['compact', 'extract'],
                ],
                Backend\TooManyParametersProphet::class => [
                    // 'max_parameters' => 6,
                    // 'include_constructors' => false,
                ],
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
                    // 'equals_method' => 'equals',
                    // 'equals_any_method' => 'equalsAny',
                    // 'not_equals_method' => 'notEquals',
                    // 'not_equals_any_method' => 'notEqualsAny',
                    // 'min_chain' => 1,
                    // 'exclude_enums' => [],
                ],
                Backend\AnchorEnumComparisonProphet::class => [
                    'trait' => 'App\\Support\\Enums\\CompareSelf',
                    // 'any_methods' => ['equalsAny', 'notEqualsAny', 'equalsAnyIgnoreType', 'notEqualsAnyIgnoreType'],
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

        // Auto-refresh: treat the generated support classes as fully tool-owned.
        // When true, `scaffold` always force-overwrites them and stamps each
        // with a loud DO-NOT-EDIT banner; the scaffold path is excluded from
        // judging (the prophets never flag generated code); and a session-start
        // hook keeps them current. Turn off to hand-edit them.
        'auto_refresh' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Skills
    |--------------------------------------------------------------------------
    |
    | The on-demand "how to do it right" Claude Code skills — one per
    | architectural subject (option, registry, enums, …) — installed under
    | `.claude/skills/commandments-<subject>/`. They pair with the prophets
    | (enforce) and the scripture (terse always-on rule); a prophet finding
    | points back at its skill. New skills shipped by a package upgrade install
    | automatically on `sync` unless `auto` is false. Existing files are never
    | overwritten (run `commandments:install-skills --force` to refresh). Skill
    | examples use your `scaffold.namespace` so they match the generated code.
    | Add subject slugs to `except` to opt out of specific ones.
    |
    */

    'skills' => [
        'auto' => true,
        'except' => [],

        // Auto-refresh: treat the installed skills as fully tool-owned. When
        // true, `install-skills` always force-overwrites them and stamps each
        // with a loud DO-NOT-EDIT banner; the skills path is added to
        // `.gitignore` (regenerated, not committed); and a session-start hook
        // keeps them current. Leave false to commit them like CLAUDE.md.
        'auto_refresh' => false,
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
    | Claude Code Hooks
    |--------------------------------------------------------------------------
    |
    | Optional hook suites the package installs into `.claude/`. The base hooks
    | (session-start scripture, stop-judge, post-commit reminder) are always
    | installed by `install-hooks`/`init`.
    |
    | plan_loop (OFF by default): an autonomous-continuation harness. When true,
    | `install-hooks`/`init` ship six scripts into `.claude/hooks/` and wire a
    | PreToolUse guard + a Stop auto-continue loop + an ExitPlanMode arm + a
    | post-commit plan-progress-memory step. It drives an APPROVED plan to
    | completion and refuses to idle-stop (with a 200-continuation backstop and a
    | `plan-release.sh` escape). Leave OFF unless you want self-driving sessions.
    |
    */

    'hooks' => [
        'plan_loop' => false,
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
