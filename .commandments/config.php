<?php

declare(strict_types=1);

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Skills;
use JesseGall\CodeCommandments\Sins;
use JesseGall\CodeCommandments\Hooks;
use JesseGall\CodeCommandments\Agents;

/*
 | code-commandments configuration.
 |
 | paths()   — the source roots judge and repent scan (auto-detected on first run; edit to
 |             adjust scope, or run `commandments config reindex`).
 | exclude() — subtract explicit paths ON TOP of paths(): a dir or file listed here is never a
 |             target (never reported, never rewritten), e.g. $config->exclude('src/Generated').
 | disable() — silence a rule by its Sin, Detector, or whole Skill class (or use
 |             `commandments disable/enable <sin>`).
 |
 | Extend it inside the closure:
 |   $config->detector(\App\Commandments\NoRawSqlDetector::class);        — add your own finder
 |   $config->package(\App\Commandments\MyFrameworkPackage::class);       — register its exemptions
 |   $config->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10));  — tune a threshold
 */

/**
 * Uncomment a line to disable that skill (every detector it teaches). New entries are appended on composer update; your edits are kept.
 */
$disabledSkills = function (Config $config): void {
    $config->disable(
        // ----------[ Backend ]----------
        // Skills\Backend\Absence::class,
        // Skills\Backend\BehaviourPerMethod::class,
        // Skills\Backend\ClassLayout::class,
        // Skills\Backend\Concurrent\ConcurrentState::class,
        // Skills\Backend\DependencyDirection::class,
        // Skills\Backend\Documentation::class,
        // Skills\Backend\EnumsWithBehaviour::class,
        // Skills\Backend\Exceptions::class,
        // Skills\Backend\FixAtTheSource::class,
        // Skills\Backend\GuardClausesAndFlow::class,
        // Skills\Backend\Laravel\LaravelIdioms::class,
        // Skills\Backend\Laravel\RouteActions::class,
        // Skills\Backend\MethodMood::class,
        // Skills\Backend\PassTheObject::class,
        // Skills\Backend\RepeatedCallHelper::class,
        // Skills\Backend\RoleVocabulary::class,
        // Skills\Backend\Spatie\PageObjects::class,
        // Skills\Backend\Spatie\SpatieData::class,
        // Skills\Backend\Spatie\SpatieDataHydration::class,
        // Skills\Backend\TellDontAsk::class,
        // Skills\Backend\Templates::class,
        // Skills\Backend\TypeHonesty::class,
        // Skills\Backend\ValueObjects::class,
        // Skills\TypeScript\Absence::class,
        // Skills\TypeScript\Duplication::class,

        // ----------[ Frontend ]----------
        // Skills\Frontend\MirroredServerType::class,
        // Skills\Frontend\VueComponents::class,
        // Skills\Frontend\VueControlFlow::class,

        // ----------[ Python ]----------
        // Skills\Python\Absence::class,
        // Skills\Python\BehaviourPerMethod::class,
        // Skills\Python\ClassLayout::class,
        // Skills\Python\Documentation::class,
        // Skills\Python\Duplication::class,
        // Skills\Python\Enums::class,
        // Skills\Python\Exceptions::class,
        // Skills\Python\FixAtTheSource::class,
        // Skills\Python\Flow::class,
        // Skills\Python\MethodMood::class,
        // Skills\Python\RepeatedCallHelper::class,
        // Skills\Python\TellDontAsk::class,
        // Skills\Python\Templates::class,
        // Skills\Python\TypeHonesty::class,
        // Skills\Python\ValueObjects::class,

        // ----------[ C# ]----------
        // Skills\CSharp\Absence::class,
        // Skills\CSharp\Duplication::class,
        // Skills\CSharp\Enums::class,
        // Skills\CSharp\Exceptions::class,
        // Skills\CSharp\Flow::class,
        // Skills\CSharp\ValueObjects::class,
    );
};

/**
 * Uncomment a line to disable that single sin. New entries are appended on composer update; your edits are kept.
 */
$disabledSins = function (Config $config): void {
    $config->disable(
        // ----------[ Backend ]----------
        // Sins\Backend\ArchaeologyComment::class,
        // Sins\Backend\ArrayBag::class,
        // Sins\Backend\ArrayReturnBag::class,
        // Sins\Backend\AssembledTemplate::class,
        // Sins\Backend\BareStatePredicate::class,
        // Sins\Backend\BlankStringDefault::class,
        // Sins\Backend\BlankStringOnTheWire::class,
        // Sins\Backend\BloatedDocblock::class,
        // Sins\Backend\CancelledCoalesce::class,
        // Sins\Backend\CeremonyDocblock::class,
        // Sins\Backend\CoalescedLoopSubject::class,
        // Sins\Backend\ComputedBooleanArgument::class,
        // Sins\Backend\Concurrent\ConcurrentSubclass::class,
        // Sins\Backend\ConditionalArraySpread::class,
        // Sins\Backend\ConstClassEnum::class,
        // Sins\Backend\ConstructorSideEffect::class,
        // Sins\Backend\ConvertedArgument::class,
        // Sins\Backend\CoupledFields::class,
        // Sins\Backend\DanglingDocReference::class,
        // Sins\Backend\DataClump::class,
        // Sins\Backend\DeNulledFinder::class,
        // Sins\Backend\DeepNesting::class,
        // Sins\Backend\DerivedArgument::class,
        // Sins\Backend\DivergentTwin::class,
        // Sins\Backend\DuplicateFunction::class,
        // Sins\Backend\EnumCaseOrChain::class,
        // Sins\Backend\EnumValueMatch::class,
        // Sins\Backend\ErasedNullObject::class,
        // Sins\Backend\FeatureEnvy::class,
        // Sins\Backend\FlagArgument::class,
        // Sins\Backend\GenericException::class,
        // Sins\Backend\HandRolledWither::class,
        // Sins\Backend\IfElseLadder::class,
        // Sins\Backend\InArrayMirrorsEnum::class,
        // Sins\Backend\InlineDocblock::class,
        // Sins\Backend\InlineThrow::class,
        // Sins\Backend\KeyedLookupEnvy::class,
        // Sins\Backend\Laravel\BoundaryDuplicatedOperation::class,
        // Sins\Backend\Laravel\ConfigRead::class,
        // Sins\Backend\Laravel\ContainerReach::class,
        // Sins\Backend\Laravel\DanglingRouteName::class,
        // Sins\Backend\Laravel\DeadConfigKey::class,
        // Sins\Backend\Laravel\DeadEventWiring::class,
        // Sins\Backend\Laravel\DuplicateRoute::class,
        // Sins\Backend\Laravel\DuplicateRouteAction::class,
        // Sins\Backend\Laravel\DuplicatedConfigDefault::class,
        // Sins\Backend\Laravel\FacadeCall::class,
        // Sins\Backend\Laravel\MassUpdateAtCallSite::class,
        // Sins\Backend\Laravel\ModelMutationAtCallSite::class,
        // Sins\Backend\Laravel\OrphanedBinding::class,
        // Sins\Backend\Laravel\RawRequestInput::class,
        // Sins\Backend\Laravel\RequestAccessorRecast::class,
        // Sins\Backend\Laravel\RouteDelegatesToController::class,
        // Sins\Backend\LoopInvertedGuard::class,
        // Sins\Backend\ManufacturedFakeFill::class,
        // Sins\Backend\MaskedInvariant::class,
        // Sins\Backend\MatchDefaultReturnsNull::class,
        // Sins\Backend\MemberAfterMethod::class,
        // Sins\Backend\MemberOutOfOrder::class,
        // Sins\Backend\MessageAtThrow::class,
        // Sins\Backend\MutableStaticState::class,
        // Sins\Backend\MutableValueObject::class,
        // Sins\Backend\NamespaceCycle::class,
        // Sins\Backend\NamespaceDependency::class,
        // Sins\Backend\NarratedCommand::class,
        // Sins\Backend\NearDuplicateFunction::class,
        // Sins\Backend\NegativeSpaceComment::class,
        // Sins\Backend\NestedTernary::class,
        // Sins\Backend\NonCountingFor::class,
        // Sins\Backend\NullableCallback::class,
        // Sins\Backend\NullableRegistryLookup::class,
        // Sins\Backend\ParamResolvedFromParam::class,
        // Sins\Backend\PhantomNullable::class,
        // Sins\Backend\PhpTypes\OptionAsNullable::class,
        // Sins\Backend\PositionalTupleReturn::class,
        // Sins\Backend\RawDecodedArrayReturn::class,
        // Sins\Backend\RedundantArrowReturnType::class,
        // Sins\Backend\RedundantElse::class,
        // Sins\Backend\RepeatedGuard::class,
        // Sins\Backend\RepeatedNamedCall::class,
        // Sins\Backend\RepeatedTypeGuard::class,
        // Sins\Backend\RestatedComment::class,
        // Sins\Backend\ScratchStateRestore::class,
        // Sins\Backend\ShortCircuitStatement::class,
        // Sins\Backend\Spatie\AllNullableData::class,
        // Sins\Backend\Spatie\AllOptionalData::class,
        // Sins\Backend\Spatie\ConstructorOrchestration::class,
        // Sins\Backend\Spatie\DataCollectionType::class,
        // Sins\Backend\Spatie\DataMethodHintCollision::class,
        // Sins\Backend\Spatie\DataToArrayRoundtrip::class,
        // Sins\Backend\Spatie\DerivedCollectionCast::class,
        // Sins\Backend\Spatie\FlatFieldCluster::class,
        // Sins\Backend\Spatie\HandKeyRemap::class,
        // Sins\Backend\Spatie\HookMissingComputed::class,
        // Sins\Backend\Spatie\InjectedServiceNotHidden::class,
        // Sins\Backend\Spatie\ManualHydrationLoop::class,
        // Sins\Backend\Spatie\ManualInputCast::class,
        // Sins\Backend\Spatie\ManualOutputTransform::class,
        // Sins\Backend\Spatie\NestedTypeMissingTypeScript::class,
        // Sins\Backend\Spatie\NewDataObject::class,
        // Sins\Backend\Spatie\NonFinalData::class,
        // Sins\Backend\Spatie\NullToOptionalMap::class,
        // Sins\Backend\Spatie\NullableWireObject::class,
        // Sins\Backend\Spatie\PageObjectMissingTypeScript::class,
        // Sins\Backend\Spatie\PlaceholderFilledData::class,
        // Sins\Backend\Spatie\PreferOptionalCreate::class,
        // Sins\Backend\Spatie\RedundantEnumUnwrap::class,
        // Sins\Backend\Spatie\RedundantNativeCast::class,
        // Sins\Backend\Spatie\RedundantNestedFrom::class,
        // Sins\Backend\Spatie\ServiceLocationInPageObject::class,
        // Sins\Backend\Spatie\TransformerWithoutTsType::class,
        // Sins\Backend\StackedDocblock::class,
        // Sins\Backend\StringMatchMirrorsEnum::class,
        // Sins\Backend\SwallowCatch::class,
        // Sins\Backend\TernaryStatement::class,
        // Sins\Backend\TypeSwitch::class,
        // Sins\Backend\UnnamedVocabularyLiteral::class,
        // Sins\Backend\UselessPropertyHook::class,
        // Sins\Backend\WrappingWithoutCause::class,

        // ----------[ Frontend ]----------
        // Sins\Frontend\CompoundInlineComponent::class,
        // Sins\Frontend\ControlFlowOnElement::class,
        // Sins\Frontend\DeepDataReach::class,
        // Sins\Frontend\DeepNested::class,
        // Sins\Frontend\DuplicateElement::class,
        // Sins\Frontend\IndexAsKey::class,
        // Sins\Frontend\LoopWithCondition::class,
        // Sins\Frontend\MirroredServerType::class,
        // Sins\Frontend\NearDuplicateElement::class,
        // Sins\Frontend\PropDrilling::class,
        // Sins\Frontend\PropMutation::class,
        // Sins\Frontend\SwitchCase::class,
        // Sins\Frontend\TypeScript\DefendedCertainField::class,
        // Sins\Frontend\TypeScript\DuplicateFunction::class,
        // Sins\Frontend\TypeScript\FalselyOptionalField::class,
        // Sins\Frontend\TypeScript\NearDuplicateFunction::class,

        // ----------[ Python ]----------
        // Sins\Python\AssembledTemplate::class,
        // Sins\Python\BareStatePredicate::class,
        // Sins\Python\BlankStringDefault::class,
        // Sins\Python\CancelledFallback::class,
        // Sins\Python\CoalescedLoopSubject::class,
        // Sins\Python\ConditionalSpread::class,
        // Sins\Python\ConditionalStatement::class,
        // Sins\Python\ConstantClassEnum::class,
        // Sins\Python\ConstantProperty::class,
        // Sins\Python\ConstructorSideEffect::class,
        // Sins\Python\DataClump::class,
        // Sins\Python\DeepNesting::class,
        // Sins\Python\DictBag::class,
        // Sins\Python\DictReturnBag::class,
        // Sins\Python\DuplicateFunction::class,
        // Sins\Python\EnumCaseOrChain::class,
        // Sins\Python\EnumValueMatch::class,
        // Sins\Python\FlagArgument::class,
        // Sins\Python\HandRolledReplace::class,
        // Sins\Python\InLiteralsMirrorsEnum::class,
        // Sins\Python\InventedDefault::class,
        // Sins\Python\LoopWrappedInIf::class,
        // Sins\Python\MatchWildcardReturnsNone::class,
        // Sins\Python\MemberAfterMethod::class,
        // Sins\Python\MemberOutOfOrder::class,
        // Sins\Python\MessageStringRaise::class,
        // Sins\Python\MutableStaticState::class,
        // Sins\Python\MutableValueObject::class,
        // Sins\Python\NarratedCommand::class,
        // Sins\Python\NearDuplicateFunction::class,
        // Sins\Python\NestedConditional::class,
        // Sins\Python\NullableCallback::class,
        // Sins\Python\PlaceholderFilledData::class,
        // Sins\Python\PositionalTupleReturn::class,
        // Sins\Python\RaiseWithoutCause::class,
        // Sins\Python\RawDecodedReturn::class,
        // Sins\Python\RedundantElse::class,
        // Sins\Python\RepeatedGuard::class,
        // Sins\Python\RepeatedNamedCall::class,
        // Sins\Python\RepeatedTypeGuard::class,
        // Sins\Python\ScratchStateRestore::class,
        // Sins\Python\ShortCircuitStatement::class,
        // Sins\Python\StringMatchMirrorsEnum::class,
        // Sins\Python\SubjectLadder::class,
        // Sins\Python\SwallowedException::class,
        // Sins\Python\TypeSwitch::class,

        // ----------[ C# ]----------
        // Sins\CSharp\DataClump::class,
        // Sins\CSharp\DeepNesting::class,
        // Sins\CSharp\DictionaryBag::class,
        // Sins\CSharp\DuplicateMethod::class,
        // Sins\CSharp\GenericThrow::class,
        // Sins\CSharp\InventedDefault::class,
        // Sins\CSharp\LoopWrappedInIf::class,
        // Sins\CSharp\NearDuplicateMethod::class,
        // Sins\CSharp\NullForgiven::class,
        // Sins\CSharp\RedundantElse::class,
        // Sins\CSharp\StringMirrorsEnum::class,
        // Sins\CSharp\SubjectLadder::class,
        // Sins\CSharp\SwallowedException::class,
    );
};

/**
 * Uncomment a line to disable that Claude Code hook (a wired nudge). New entries are appended on composer update; your edits are kept.
 */
$disabledHooks = function (Config $config): void {
    $config->disable(
        // Hooks\Handlers\CommitTrigger::class,
        // Hooks\Handlers\ConstraintReminder::class,
        // Hooks\Handlers\JudgeReminder::class,
        // Hooks\Handlers\ModelChoiceReminder::class,
        // Hooks\Handlers\PlanReminder::class,
        // Hooks\Handlers\Remind::class,
        // Hooks\Handlers\SessionReset::class,
        // Hooks\Handlers\SharedBranchGate::class,
        // Hooks\Handlers\SkillReminder::class,
        // Hooks\Handlers\SourceReminder::class,
        // Hooks\Handlers\TestingReminder::class,
        // Hooks\Handlers\WorkerFinishedTrigger::class,
        // Hooks\Handlers\WorkingState::class,
    );
};

/**
 * Uncomment a line to stop publishing into that agent (its skills, its instructions file, its hooks). New entries are appended on composer update; your edits are kept.
 */
$disabledAgents = function (Config $config): void {
    $config->disable(
        // Agents\ClaudeAgent::class,
        // Agents\CodexAgent::class,
    );
};

return function (Config $config) use ($disabledSkills, $disabledSins, $disabledHooks, $disabledAgents): void {
    $config->paths('src');

    $config->disable(
        // \JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,
    );
    $disabledSkills($config);
    $disabledSins($config);
    $disabledHooks($config);
    $disabledAgents($config);
};
