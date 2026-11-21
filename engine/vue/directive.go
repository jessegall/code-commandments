package vue

import "github.com/jessegall/code-commandments/engine"

// Name is a directive's name without its v- prefix, argument or modifiers: `if`, `for`, `model`, `on`.
type Name string

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

var Structural = []Name{If, ElseIf, Else, For}

// Directive is a directive on an element: a Match whose kind is Directive.
type Directive struct {
	engine.Match
}

func (d Directive) Name() Name {
	node := d.Node()
	if node == nil || node.Kind != "directive" {
		return ""
	}

	return Name(node.Name)
}

func (d Directive) Named(name Name) bool {
	return d.Exists() && d.Name() == name
}

func (d Directive) Value() engine.Match {
	return d.Child("value")
}

func (d Directive) Argument() engine.Match {
	return d.Child("arg")
}

func (d Directive) Iterable() engine.Match {
	return d.Child("iterable")
}

func (d Directive) Target() string {
	argument := d.Argument()
	if !argument.Exists() || argument.Role() != "other" {
		return ""
	}

	return argument.Name()
}

func IsBoundValue(m engine.Match) bool {
	return m.Parent().Kind() == "Directive" && m.Node().InRole() == "value"
}
