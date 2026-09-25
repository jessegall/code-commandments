package python

import (
	"slices"
	"strings"
	"sync"
)

// enumRoots is the standard library's enum base classes, as a base list spells them.
var enumRoots = []string{"Enum", "StrEnum", "IntEnum", "Flag", "IntFlag", "ReprEnum"}

// declarations is what the program declares by name, found once: its enums and each one's member values as
// literal keys, its TypedDicts, and its dataclasses.
type declarations struct {
	once        sync.Once
	enums       map[string]bool
	values      map[string][]string
	typedDicts  map[string]bool
	dataclasses map[string]Node
}

func (p *Program) declared() *declarations {
	p.names.once.Do(func() {
		p.names.enums, p.names.values = map[string]bool{}, map[string][]string{}
		p.names.typedDicts, p.names.dataclasses = map[string]bool{}, map[string]Node{}
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
					p.names.enums[class.Name()] = true
				}
			}
		}
		for _, class := range classes {
			if p.names.enums[class.Name()] {
				p.names.values[class.Name()] = class.MemberValueKeys()
			}
			if slices.ContainsFunc(class.ChildrenIn("bases"), isTypedDictBase) {
				p.names.typedDicts[class.Name()] = true
			}
			if class.IsDataclass() {
				p.names.dataclasses[class.Name()] = class
			}
		}
	})

	return &p.names
}

// IsEnum says whether the program declares an enum by the name: a class whose base, or the call a decorator
// makes, is a standard enum or another enum of the program.
func (p *Program) IsEnum(name string) bool {
	return p.declared().enums[name]
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
func (p *Program) IsTypedDict(name string) bool {
	return p.declared().typedDicts[name]
}

// Dataclass is the dataclass the program declares under the name, the last declared where several share it.
func (p *Program) Dataclass(name string) (Node, bool) {
	class, ok := p.declared().dataclasses[name]

	return class, ok
}

// isEnumBase says whether the base names a standard enum or an enum the program declares.
func (p *Program) isEnumBase(base Node) bool {
	dotted := base.DottedName()
	named := dotted[strings.LastIndex(dotted, ".")+1:]

	return slices.Contains(enumRoots, named) || p.names.enums[named]
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
		if at > 0 && annotation != "" && short != host.Name() && p.DeclaresClass(annotation) && !p.IsEnum(short) {
			owned = append(owned, parameter.Name())
		}
	}

	return owned
}
