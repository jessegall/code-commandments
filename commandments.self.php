<?php

/*
|------------------------------------------------------------------------------
| Self-judgment — code-commandments judging its own source.
|------------------------------------------------------------------------------
|
| Dogfooding: run the prophets against this package's own `src/`. Standalone,
| so it uses plain __DIR__ paths (no Laravel app helpers). Backend only — the
| package has no frontend. Run it with:
|
|     php bin/commandments judge -c commandments.self.php
|     php bin/commandments judge -c commandments.self.php --next   # one at a time
|
| Scoped to src/, so tests/, vendor/, stubs/, bin/, config/ and workbench/ are
| all outside the scan. Laravel/Spatie-specific prophets that don't apply stay
| silent (their supported() is false, or there's simply no matching code).
|
*/

use JesseGall\CodeCommandments\Prophets\Backend;

return [

    'scrolls' => [
        'backend' => [
            'path' => __DIR__ . '/src',
            'extensions' => ['php'],
            'exclude' => [
                // Belt-and-suspenders — already outside src/, listed for intent.
                'tests',
                'vendor',
                'stubs',
                'workbench',
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
                Backend\ControllerPrivateMethodsProphet::class,
                Backend\KebabCaseRoutesProphet::class,
                Backend\ConstructorDependencyInjectionProphet::class,
                Backend\NoInlineBootLogicProphet::class,
                Backend\ComputedPropertyMustHookProphet::class,
                Backend\QueryModelsThroughQueryMethodProphet::class,
                Backend\NoRequestDataPassthroughProphet::class,
                Backend\NoAuthUserInDataClassesProphet::class,
                Backend\LongMethodProphet::class => [
                    // Parser/AST code runs longer than app code — 80 lines here.
                    'max_method_lines' => 80,
                ],
                Backend\NoRawLiteralProphet::class,
                Backend\NoArrayStringIndexingProphet::class,
                Backend\NoArrayBagProphet::class,
                Backend\NoManualHydrationProphet::class,
                Backend\NoRepeatedHydrationProphet::class,
                Backend\PreferDataCollectionOfProphet::class,
                Backend\ExplicitDataFactoryProphet::class,
                Backend\DataClassFromArrayOnlyProphet::class,
                Backend\NoExternalDataFromProphet::class,
                Backend\PreferNamedExceptionsProphet::class,
                Backend\PreferSprintfProphet::class,
                Backend\PreferOptionOverNullProphet::class,
                Backend\PreferAndThenProphet::class,
                Backend\PreferEmptyOverNullProphet::class,
                Backend\RegistryReturnContractProphet::class,
                Backend\StringsThatShouldBeEnumsProphet::class,
                Backend\RepeatedFallbackProphet::class,
                Backend\PreferEnumCaseGroupsProphet::class,
                Backend\PreferStaticOverInvokableConstructProphet::class,
                Backend\NoConditionalArraySpreadProphet::class,
                Backend\PreferTypeMethodOverInlineDispatchProphet::class,
                Backend\ResolverPatternProphet::class,
                Backend\ResolverNamingHonestyProphet::class,
                Backend\PushGenericToSourceProphet::class,
                Backend\PreferNamedBranchFactoryProphet::class,
                Backend\BehaviouralEnumDispatchProphet::class,
                Backend\ThrowOnUnhandledCaseProphet::class,
                Backend\PreferTotalOverNullableProphet::class,
                Backend\DuplicateCodeProphet::class,
                Backend\PreferDataTransformersProphet::class,
                Backend\PreferDefaultFallbackProphet::class,
                Backend\NoOptionToNullProphet::class,
                Backend\NoOptionOveruseProphet::class,
                Backend\NoOptionInUnionProphet::class,
                Backend\NoRedundantOrElseWrapProphet::class,
                Backend\PreferOptionChainOverGuardProphet::class,
                Backend\WideUnionTypeProphet::class,
                Backend\PreferNullCoalescingProphet::class,
                Backend\NoNullCoalesceToNullProphet::class,
                Backend\PreferTypeCoalesceProphet::class,
                Backend\PreferEnumForClosedSetFieldProphet::class,
                Backend\NoCompactProphet::class,
                Backend\TooManyParametersProphet::class,
                Backend\NoContainerResolutionProphet::class,
                Backend\PreferNullObjectDefaultsProphet::class,
                Backend\LongDocblockProphet::class,
                Backend\SuggestCompareSelfTraitProphet::class,
            ],
        ],
    ],

    'confession' => [
        'tablet_path' => __DIR__ . '/.commandments/confessions.json',
    ],

    'scaffold' => [
        'auto' => false,
        'namespace' => 'JesseGall\\CodeCommandments\\Support',
        'path' => __DIR__ . '/src/Support',
        'except' => [],
    ],

    'report' => [
        'repo' => 'jessegall/code-commandments',
    ],

    'node_path' => null,
];
