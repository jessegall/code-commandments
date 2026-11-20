# Dependencies: keep imports going in one direction — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`concrete-cross-module-dependency`** — A constructor takes another module's concrete service — `ConcreteCrossModuleDependencyDetector`
- **`guard-beside-transition`** — A caller checks another object's state before telling it to act — `GuardBesideTransitionDetector`
- **`hand-rolled-registry`** — A type repeats a checked keyed store — `HandRolledRegistryDetector`
- **`interface-overlap`** — An interface repeats the signatures of a contract it can reuse. — `InterfaceOverlapDetector`
- **`lone-implementer-interface`** — An interface has only one local implementation — `LoneImplementerInterfaceDetector`
- **`middle-man`** — A type forwards its methods to one field — `MiddleManDetector`
- **`namespace-cycle`** — Two independent modules import each other — `NamespaceCycleDetector`
- **`namespace-dependency`** — An import crosses a layer boundary its source may not use — `NamespaceDependencyDetector`
- **`refused-bequest`** — An override only throws — `RefusedBequestDetector`
- **`train-wreck`** — A chain reaches through three local types — `TrainWreckDetector`
- **`undeclared-member`** — A member access the receiver's declared type neither declares nor inherits — `UndeclaredMemberDetector`
- **`unread-collaborator`** — A collaborator is stored and never read — `UnreadCollaboratorDetector`
- **`unused-private-member`** — A private member has no readers — `UnusedPrivateMemberDetector`
- **`useless-property-hook`** — A getter reads no state on its object — `UselessPropertyHookDetector`
