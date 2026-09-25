package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// ValueObjects is the backend/value-objects discipline.
type ValueObjects struct{}

func init() { skill.Register(catalog.Backend, ValueObjects{}) }

// Definition is what the skill states about itself.
func (ValueObjects) Definition() skill.Definition {
	return skill.Definition{
		Slug:    "backend/value-objects",
		Tier:    skill.Mandatory,
		Order:   3,
		Title:   "Value objects — give related data a type",
		Trigger: `WHEN to give data a type instead of passing it loose — an ` + "`" + `array<string,mixed>` + "`" + ` bag, 3+ values that always travel together (a data clump), a string-indexed structured array, primitive obsession, or a too-long parameter list all want a typed object. Read this BEFORE you pass or return an untyped array, add another parameter to a crowded signature, or write ` + "`" + `$arr['key']` + "`" + ` on a structured array. (How to WRITE the class is ` + "`" + `spatie-data` + "`" + `; this is when to make one.)`,
		Intro: `Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a
cluster of values is passed around, returned, or reached into by string keys, it wants a name and a type.`,
		Summary: `give related data a type: no loose ` + "`" + `array<string,mixed>` + "`" + ` bags, no data clumps, no primitive obsession. (Decide the type; then ` + "`" + `spatie-data` + "`" + ` is how to write it.)`,
		Principle: `Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a cluster
of values is passed around, returned, or reached into by string keys, it wants a name and a type — the type
IS the documentation, the validation, and the contract, all enforced by the compiler instead of by every
reader's memory.

Reach for a type when you: pass or return an ` + "`" + `array<string, mixed>` + "`" + ` bag whose keys are an undocumented
contract; pass three or more values that always travel together (a data clump); reach into a structured array
by string key (` + "`" + `$entry['title']` + "`" + `) instead of a typed object; keep adding parameters to an already-crowded
signature instead of grouping them; or pass a bare primitive that is really a concept — a ` + "`" + `string $email` + "`" + `, a
` + "`" + `string $currency` + "`" + ` + ` + "`" + `int $amount` + "`" + `, a ` + "`" + `string $key` + "`" + ` with format rules — that wants a value object owning its
own validation.

Introduce the type at the boundary that first receives the data, or the method that first assembles it — not
several steps later, after it has been passed around as a loose array. A value object
introduced late just relabels data everyone already mishandled. This is fix-at-the-source applied to shape.`,
		Languages: []string{"php"},
		Related: []skill.Related{
			{Slug: "backend/fix-at-the-source", Reason: "introduce the type where the data is born, not downstream."},
			{Slug: "backend/spatie-data", Reason: `once you've decided it's a DTO, that skill is *how* to write it (and its honest-field-types rule keeps the new type from being a fresh all-nullable bag).`},
			{Slug: "backend/absence", Reason: "the new type's fields still answer \"can this be missing?\" honestly."},
		},
	}
}
