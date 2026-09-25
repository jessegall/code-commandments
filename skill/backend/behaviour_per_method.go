package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// BehaviourPerMethod is the backend/behaviour-per-method discipline.
type BehaviourPerMethod struct{}

func init() { skill.Register(catalog.Backend, BehaviourPerMethod{}) }

// Definition is what the skill states about itself.
func (BehaviourPerMethod) Definition() skill.Definition {
	return skill.Definition{
		Slug:    "backend/behaviour-per-method",
		Tier:    skill.KeepInMind,
		Order:   18,
		Title:   "One method, one behaviour — never a flag that picks",
		Trigger: `A parameter that selects WHICH behaviour runs rather than feeding one. When a method's whole body is ` + "`" + `if ($flag) { … } else { … }` + "`" + `, it is two methods sharing a name, and every call site reads ` + "`" + `render($order, true)` + "`" + ` — a truth value that says nothing about what it asked for. Split it into two named methods and let the caller say which it wants. Read this before adding a ` + "`" + `bool` + "`" + ` parameter, before widening a required parameter to ` + "`" + `?T = null` + "`" + ` so that leaving it out means 'all of them', before writing a method whose body is one branch on a parameter, and when a call site passes a bare ` + "`" + `true` + "`" + `/` + "`" + `false` + "`" + ` literal.`,
		Intro: `` + "`" + `render($order, true)` + "`" + ` — true *what*? The caller already knows which of the two
things it wants; the flag is that decision, flattened into a truth value and
handed over for the callee to unpack again.`,
		Summary: `a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call site say which it wants, instead of passing a bare ` + "`" + `true` + "`" + `.`,
		Principle: `A parameter is supposed to be something a method **works with**. A flag argument is
something the method works *around*: it arrives, gets tested once, and decides which
half of the body runs. The two halves were never one method — they share a name and a
signature, and nothing else.

The cost lands at the call sites, where it is worst:

- **` + "`" + `send($order, true)` + "`" + ` is unreadable.** Nothing at the call site says what ` + "`" + `true` + "`" + `
  means. Every reader has to open the callee to find out, every time.
- **The two behaviours cannot evolve apart.** A parameter one half needs becomes a
  parameter the other half must ignore, and the signature grows to fit the union of
  two jobs it was never doing together.
- **It hides how the code is really used.** Split, you can see at a glance that
  ` + "`" + `sendDraft()` + "`" + ` has three callers and ` + "`" + `sendFinal()` + "`" + ` has thirty. Fused, both are just
  "send".
- **Flags multiply.** Two bools mean four documented behaviours in one body, and
  usually only three of them were ever intended.

So: **name the two behaviours.** ` + "`" + `render($order, true)` + "`" + ` becomes ` + "`" + `renderCompact($order)` + "`" + `
and ` + "`" + `renderFull($order)` + "`" + `. The shared middle, if there is any, becomes a private method
they both call — which is the honest structure, and was invisible before.

### The disguise: a nullable that means "all of them"

The flag does not have to be a ` + "`" + `bool` + "`" + `. The commonest form it takes in a growing codebase
is a REQUIRED parameter widened to nullable so that leaving it out asks a different
question: ` + "`" + `attributes(string $kind)` + "`" + ` becomes ` + "`" + `attributes(?string $kind = null)` + "`" + `, and now
` + "`" + `attributes(Slot::class)` + "`" + ` means "the ones of this kind" while ` + "`" + `attributes()` + "`" + ` means
"every one it carries". That is two questions behind one name, selected by an ABSENCE at
the call site — which says even less than a bare ` + "`" + `true` + "`" + `, because nothing is written there
at all. It arrives disguised as an additive, backward-compatible change, which is why it
passes review. The fix is the same: ` + "`" + `attributesOfKind(string $kind)` + "`" + ` and ` + "`" + `attributes()` + "`" + `.

### What is NOT this sin

- **A flag that is DATA the method stores or forwards.** ` + "`" + `setVisible(bool $visible)` + "`" + `,
  ` + "`" + `withTrailingSlash(bool $on)` + "`" + ` — the value is the point, not a switch between two
  behaviours. It is kept, not obeyed.
- **A flag that tunes ONE behaviour.** ` + "`" + `parse($input, strict: true)` + "`" + ` where strictness
  changes what counts as an error inside one parsing pass, rather than selecting a
  different pass, is one method doing one job more or less leniently.
- **An early return that guards.** ` + "`" + `if ($force) { return $this->overwrite(); }` + "`" + ` at the
  top of a method is a guard clause and belongs there — see guard-clauses-and-flow.
  The smell is the body being *nothing but* a two-way branch.
- **An enum or object that names the choice.** ` + "`" + `render($order, Density::Compact)` + "`" + ` is
  already honest: the argument says what it means at the call site, and adding a third
  case does not add a fourth code path by accident.

### The tell

The method's entire body is ` + "`" + `if ($flag) { … } else { … }` + "`" + `, or a ` + "`" + `match ($flag)` + "`" + ` with a
` + "`" + `true` + "`" + ` arm and a ` + "`" + `false` + "`" + ` arm — or ` + "`" + `if ($kind === null) { … } else { … }` + "`" + ` on a parameter
that used to be required. Ask what you would call each half on its own. If both
halves have an obvious name, they are already two methods — give them their names.`,
		Languages: []string{"php"},
		Related: []skill.Related{
			{Slug: "backend/enums-with-behaviour", Reason: "when the choice has more than two cases, it is an enum — and the behaviour belongs on it."},
			{Slug: "backend/guard-clauses-and-flow", Reason: "an early return that guards is the shape this one is NOT; check preconditions at the top and leave."},
			{Slug: "backend/pass-the-object", Reason: `the sibling on the other side: a caller that pre-decides with a bool it computed from an object it holds.`},
		},
	}
}
