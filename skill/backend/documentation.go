package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// Documentation is the backend/documentation discipline.
type Documentation struct{}

func init() { skill.Register(catalog.Backend, Documentation{}) }

// Definition is what the skill states about itself.
func (Documentation) Definition() skill.Definition {
	return skill.Definition{
		Slug:    "backend/documentation",
		Tier:    skill.Mandatory,
		Order:   6,
		Title:   "Documentation — concise, present-tense, rare",
		Trigger: `How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change ("previously…", "used to…", "now we…", "refactored to…"). Read this the MOMENT you are about to write a docblock (` + "`" + `/**` + "`" + `), an inline comment (` + "`" + `//` + "`" + `), or a class/method description.`,
		Intro: `A docblock describes the **code as it is**, in as few words as possible — write one. An inline comment
is a last resort. Neither is a changelog, a tutorial, or a story about the refactor. Most code needs no
inline comment at all.`,
		Summary: "concise, present-tense docs; rare inline comments; never narrate the past.",
		Principle: `A docblock and a comment are not free: every line a reader must scan costs them time, and a line that just
restates the code, or narrates how it got here, wastes that time for nothing. The bar is high —
write a doc only when it tells the reader something the code itself does not.

Docs are still **wanted**, not banned. A short docblock on a class or method is good and expected: one
sentence saying what it *is* or *does*, plus the ` + "`" + `@param` + "`" + ` / ` + "`" + `@return` + "`" + ` / ` + "`" + `@throws` + "`" + ` type contract. Keep it to
a line or two, present-tense, about the code as it is now — never *how* it works internally, *why* it
changed, or what it *used to* be. Git holds the history; when you replace code, just replace it — don't leave
a comment explaining what it used to be.

Inline comments are the rarest of all — default to none. The code already says *what* it does; the only
comment worth writing explains a non-obvious **why** the code can't: a hidden invariant, a workaround for an
external bug, a constraint the reader can't infer. (A structural section divider in a large class is fine
if the codebase already uses them — structural, not narrative.) Everything else: don't write it.`,
		ExamplesKeepDocblocks: true,
		Languages:             []string{"php"},
		Related: []skill.Related{
			{Slug: "backend/fix-at-the-source", Reason: `fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.`},
		},
	}
}
