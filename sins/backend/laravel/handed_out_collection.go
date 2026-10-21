package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// HandedOutCollection is a getter whose whole body hands back the Collection the class holds — an object, so every caller holds the same one.
type HandedOutCollection struct{}

func init() {
	sins.Register(catalog.PHP, HandedOutCollection{})
}

// Definition is what the sin states about itself.
func (HandedOutCollection) Definition() sins.Definition {
	return sins.Definition{
		Name:        "handed-out-collection",
		Skill:       skills.LaravelIdioms{},
		Description: "a getter whose whole body returns the class's own `Collection`, untouched — a Collection is an object, so every caller holds the same one and may `push`, `forget` or `sort` it behind the class's back",
		Rule:        "A class keeps its own Collection: hand out something a caller cannot change, and give the class the methods callers were reaching in to use.",
		Suggestion:  "Return `$this->lines->collect()` or `$this->lines->all()` — a copy and a plain array, neither of which the caller can change on you — and add the methods the callers were doing by hand: `addLine()`, `totalFor()`, `without($line)`. A PHP array returned from a getter is already copied, which is why this is about a Collection and not about every getter.",
	}
}
