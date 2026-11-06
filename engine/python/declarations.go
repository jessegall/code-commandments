package python

import (
	"slices"
	"strings"
	"sync"
)

var enumRoots = []string{"Enum", "StrEnum", "IntEnum", "Flag", "IntFlag", "ReprEnum"}

// declarations is what the program declares by name, found once: its enums, the member values of each class that is one, as literal keys, its TypedDicts, and its dataclasses.
type declarations struct {
	once       sync.Once
	enums      map[string]bool
	values     [][]string
	typedDicts map[string]bool
}

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

func (p *Program) IsEnum(spelled Node) bool {
	declared, ok := p.ClassNamed(spelled, p.ModuleOf(spelled))

	return ok && p.declared().enums[symbolOf(declared)]
}

func (p *Program) IsEnumSymbol(symbol string) bool {
	return p.declared().enums[symbol]
}

func (p *Program) IsTypedDictClass(class Node) bool {
	return class.Kind() == "ClassDef" && p.declared().typedDicts[symbolOf(class)]
}

func (p *Program) IsEnumClass(class Node) bool {
	return class.Kind() == "ClassDef" && p.declared().enums[symbolOf(class)]
}

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

func (p *Program) IsTypedDict(spelled Node) bool {
	declared, ok := p.ClassNamed(spelled, p.ModuleOf(spelled))

	return ok && p.declared().typedDicts[symbolOf(declared)]
}

func (p *Program) Dataclass(spelled Node) (Node, bool) {
	class, ok := p.ClassNamed(spelled, p.ModuleOf(spelled))
	if !ok || !class.IsDataclass() {
		return Node{}, false
	}

	return class, true
}

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

func (n Node) decoratorArguments() []Node {
	var arguments []Node
	for _, decorator := range n.Decorators() {
		arguments = append(arguments, decorator.Arguments()...)
	}

	return arguments
}

func (n Node) MemberValueKeys() []string {
	var keys []string
	for _, statement := range n.ChildrenIn("body") {
		if key := statement.Child("value").LiteralKey(); statement.Kind() == "Assign" && len(statement.ChildrenIn("targets")) == 1 && key != "" {
			keys = append(keys, key)
		}
	}

	return keys
}

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
