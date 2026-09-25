package vue

import "github.com/jessegall/code-commandments/engine"

// Name is a directive's name without its v- prefix, argument or modifiers: `if`, `for`, `model`, `on`.
type Name string

// The directives the engine asks about by name.
const (
	If     Name = "if"
	ElseIf Name = "else-if"
	Else   Name = "else"
	For    Name = "for"
	Show   Name = "show"
	Model  Name = "model"
	Bind   Name = "bind"
	On     Name = "on"
	Slot   Name = "slot"
	HTML   Name = "html"
	Text   Name = "text"
)

// Structural is the directives that decide which DOM renders: v-if, v-else-if, v-else and v-for.
var Structural = []Name{If, ElseIf, Else, For}

// Directive is a directive on an element: a Match whose kind is Directive.
type Directive struct {
	engine.Match
}

// Name is the directive's name, `if` for `v-if` and `bind` for `:href`.
func (d Directive) Name() Name {
	node := d.Node()
	if node == nil || node.Extras == nil || node.Extras.Vue == nil || node.Extras.Vue.Directive == nil {
		return ""
	}

	return Name(node.Extras.Vue.Directive.Name)
}

// Named says whether the directive is the one named.
func (d Directive) Named(name Name) bool {
	return d.Exists() && d.Name() == name
}

// Value is the directive's expression; no node for a directive written without one.
func (d Directive) Value() engine.Match {
	return d.Child("value")
}

// Argument is the directive's argument, `href` in `:href`; no node when it has none.
func (d Directive) Argument() engine.Match {
	return d.Child("arg")
}

// Iterable is what a v-for loops over; no node on any other directive.
func (d Directive) Iterable() engine.Match {
	return d.Child("iterable")
}

// Aliases is the patterns a v-for binds for each item: the item, then its key and index when written.
func (d Directive) Aliases() []engine.Match {
	return d.ChildrenIn("alias")
}
