# Arguments: name the input and return the result — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`array-bag`** — A loose map is read through fixed keys — `ArrayBagDetector`
- **`array-bag-parameter`** — Callers pass a loose map with the same fields — `ArrayBagParameterDetector`
- **`array-return-bag`** — A routine returns a loose map with fixed fields — `ArrayReturnBagDetector`
- **`assembled-template`** — A multiline template is split into lines and joined — `AssembledTemplateDetector`
- **`bare-state-predicate`** — A Boolean method about its own state has the name of an action — `BareStatePredicateDetector`
- **`computed-boolean-argument`** — Callers repeatedly compute Boolean arguments from one object — `ComputedBooleanArgumentDetector`
- **`constructor-side-effect`** — A constructor calls a collaborator and drops the result — `ConstructorSideEffectDetector`
- **`coupled-fields`** — An operation changes and checks the same fields together — `CoupledFieldsDetector`
- **`data-clump`** — The same group of scalar parameters appears on different types — `DataClumpDetector`
- **`derived-argument`** — Callers repeatedly pass pieces of the same object — `DerivedArgumentDetector`
- **`feature-envy`** — An operation reads several pieces of another local type — `FeatureEnvyDetector`
- **`filled-parameter`** — The caller reads the result from a parameter the function filled — `FilledParameterDetector`
- **`flag-argument`** — A Boolean parameter selects which operation runs — `FlagArgumentDetector`
- **`hand-rolled-wither`** — A wither repeats fields from its type's constructor — `HandRolledWitherDetector`
- **`handed-out-collection`** — A routine exposes its own mutable collection — `HandedOutCollectionDetector`
- **`keyed-lookup-envy`** — A routine looks up a value using a field of another local type — `KeyedLookupEnvyDetector`
- **`match-default-returns-null`** — The default arm silently returns an absent value — `MatchDefaultReturnsNullDetector`
- **`member-after-method`** — A field is declared below a method — `MemberAfterMethodDetector`
- **`member-out-of-order`** — A constant is declared below an instance field — `MemberOutOfOrderDetector`
- **`mutable-static-state`** — An operation writes shared static state — `MutableStaticStateDetector`
- **`mutable-value-object`** — A value type changes a field after construction — `MutableValueObjectDetector`
- **`narrated-command`** — A command method is named in the third person — `NarratedCommandDetector`
- **`param-resolved-from-param`** — One parameter is only used to resolve another parameter — `ParamResolvedFromParamDetector`
- **`positional-tuple-return`** — A return tuple has interchangeable unnamed slots — `PositionalTupleReturnDetector`
- **`redundant-arrow-return-type`** — An arrow repeats the type already inferred from its expression — `RedundantArrowReturnTypeDetector`
- **`scratch-state-restore`** — A method saves a field, changes it, and restores it — `ScratchStateRestoreDetector`
- **`unused-parameter`** — The function never reads a declared parameter — `UnusedParameterDetector`
