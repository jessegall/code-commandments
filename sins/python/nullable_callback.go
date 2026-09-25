package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/python"
)

// NullableCallback is `cb: Callable | None = None` asked `if cb is not None:` / `if cb:` / `cb or …` in the body — a no-op treated as if it might be missing.
type NullableCallback struct{}

func init() {
	sins.Register(catalog.Python, NullableCallback{})
}

// Definition is what the sin states about itself.
func (NullableCallback) Definition() sins.Definition {
	return sins.Definition{
		Name:        "python-nullable-callback",
		Skill:       skills.Absence{},
		Description: "`cb: Callable | None = None` asked `if cb is not None:` / `if cb:` / `cb or …` in the body — a no-op treated as if it might be missing.",
		Rule:        "Default an optional callback to a no-op in the signature; don't take `None` and normalise it in the body.",
		Suggestion:  "Default the parameter to a named no-op — `def ignore(*_): pass`, then `on_retry: Callable[[int], None] = ignore` — and call it unconditionally.",
	}
}
