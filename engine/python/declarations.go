package python

import (
	"slices"
	"strings"
	"sync"
)

// enumRoots is the standard library's enum base classes, as a base list spells them.
var enumRoots = []string{"Enum", "StrEnum", "IntEnum", "Flag", "IntFlag", "ReprEnum"}

// declarations is what the program declares by name, found once: its enums, the member values of each class that
// is one, as literal keys, its TypedDicts, and its dataclasses. The values are kept per enum, never by name, so a
// plain class sharing an enum's name never hides the enum's members.
type declarations struct {
	once       sync.Once
	enums      map[string]bool
	values     [][]string
	typedDicts map[string]bool
}

// symbolOf is the class's own qualified name, which no other class shares however many spell it alike.
func symbolOf(class Node) string {
	return class.Node().Symbol
}

func (p *Program) declared() *declarations {
	p.names.once.Do(func() {
		p.names.enums = map[string]bool{}
		p.names.typedDicts = map[string]bool{}
		var classes []Node
		for _, module := range p.modules {
			for _, node := range module.Nodes() {
				if node.Kind() == "ClassDef" {
					classes = append(classes, node)
				}
			}
		}
		for known := -1; known != len(p.names.enums); {
			known = len(p.names.enums)
			for _, class := range classes {
				if slices.ContainsFunc(append(class.ChildrenIn("bases"), class.decoratorArguments()...), p.isEnumBase) {
					p.names.enums[symbolOf(class)] = true
				}
			}
		}
		for _, class := range classes {
			if slices.ContainsFunc(append(class.ChildrenIn("bases"), class.decoratorArguments()...), p.isEnumBase) {
				p.names.values = append(p.names.values, class.MemberValueKeys())
			}
			if slices.ContainsFunc(class.ChildrenIn("bases"), isTypedDictBase) {
				p.names.typedDicts[symbolOf(class)] = true
			}
		}
	})

	return &p.names
}

// IsEnum says whether the program declares an enum by the name: a class whose base, or the call a decorator
// makes, is a standard enum or another enum of the program.
func (p *Program) IsEnum(spelled Node) bool {
	declared, ok := p.ClassNamed(spelled, p.ModuleOf(spelled))

	return ok && p.declared().enums[symbolOf(declared)]
}

// IsEnumSymbol says whether the enum is the one the bridge already resolved a reference to: a resolved name is
// an identity rather than a spelling, so it needs no module to read it in.
func (p *Program) IsEnumSymbol(symbol string) bool {
	return p.declared().enums[symbol]
}

// IsTypedDictClass says whether the class itself is a TypedDict, asked of the declaration rather than of a name.
func (p *Program) IsTypedDictClass(class Node) bool {
	return class.Kind() == "ClassDef" && p.declared().typedDicts[symbolOf(class)]
}

// IsEnumClass says whether the class itself is an enum, asked of the declaration rather than of a name.
func (p *Program) IsEnumClass(class Node) bool {
	return class.Kind() == "ClassDef" && p.declared().enums[symbolOf(class)]
}

// EnumsHoldAll says whether all the literal keys name members of one enum.
func (p *Program) EnumsHoldAll(keys []string) bool {
	if len(keys) == 0 {
		return false
	}
	for _, values := range p.declared().values {
		if !slices.ContainsFunc(keys, func(key string) bool { return !slices.Contains(values, key) }) {
			return true
		}
	}

	return false
}

// IsTypedDict says whether the program declares a TypedDict by the name.
func (p *Program) IsTypedDict(spelled Node) bool {
	declared, ok := p.ClassNamed(spelled, p.ModuleOf(spelled))

	return ok && p.declared().typedDicts[symbolOf(declared)]
}

// Dataclass is the dataclass the node spells, read in the module that wrote it: a name is resolved to the
// class it names THERE, never matched against every class of that name in the program.
func (p *Program) Dataclass(spelled Node) (Node, bool) {
	class, ok := p.ClassNamed(spelled, p.ModuleOf(spelled))
	if !ok || !class.IsDataclass() {
		return Node{}, false
	}

	return class, true
}

// isEnumBase says whether the base names a standard enum, or an enum the program declares — the one its own
// module resolves the name to, never any class that happens to spell it the same.
func (p *Program) isEnumBase(base Node) bool {
	dotted := base.DottedName()
	named := dotted[strings.LastIndex(dotted, ".")+1:]
	if slices.Contains(enumRoots, named) {
		return true
	}

	declared, ok := p.ClassNamed(base, p.ModuleOf(base))

	return ok && p.names.enums[symbolOf(declared)]
}

func isTypedDictBase(base Node) bool {
	return slices.Contains([]string{"TypedDict", "typing.TypedDict", "typing_extensions.TypedDict"}, base.DottedName())
}

// decoratorArguments is what the class's decorators are called with: `IntEnum` in `@_simple_enum(IntEnum)`, a
// decorator that builds an enum out of a plain class body.
func (n Node) decoratorArguments() []Node {
	var arguments []Node
	for _, decorator := range n.Decorators() {
		arguments = append(arguments, decorator.Arguments()...)
	}

	return arguments
}

// MemberValueKeys is the literal key of each value the class body assigns to a single name.
func (n Node) MemberValueKeys() []string {
	var keys []string
	for _, statement := range n.ChildrenIn("body") {
		if key := statement.Child("value").LiteralKey(); statement.Kind() == "Assign" && len(statement.ChildrenIn("targets")) == 1 && key != "" {
			keys = append(keys, key)
		}
	}

	return keys
}

// LiteralKey is a literal as a comparable key, its kind and its value: `string:paid`, `number:3`; empty for any
// other expression.
func (n Node) LiteralKey() string {
	facts := n.Node()
	if n.Kind() != "Constant" || facts == nil {
		return ""
	}
	switch facts.Literal {
	case "string", "bytes":
		text, _ := n.Text()

		return facts.Literal + ":" + text
	case "int", "float":
		return "number:" + n.Written()
	case "bool":
		return "bool:" + n.Written()
	case "null":
		return "none:None"
	}

	return ""
}

// OwnedParameters is the parameters of a method of the host annotated with a class the program declares, neither
// the host itself nor an enum, the instance's own parameter aside.
func (p *Program) OwnedParameters(method, host Node) []string {
	var owned []string
	for at, parameter := range method.Parameters() {
		annotation := parameter.Child("annotation").DottedName()
		short := annotation[strings.LastIndex(annotation, ".")+1:]
		if at > 0 && annotation != "" && short != host.Name() && p.DeclaresClass(annotation) && !p.IsEnum(parameter.Child("annotation")) {
			owned = append(owned, parameter.Name())
		}
	}

	return owned
}
