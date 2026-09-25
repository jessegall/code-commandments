package python

import (
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

// Resources a function reaches are named by kind: an outside function it calls, or a class it builds or names.
const (
	functionResource = "fn:"
	typeResource     = "type:"
)

// resourceReach is what every def reaches, counted once.
type resourceReach struct {
	once      sync.Once
	functions engine.ResourcePopulation
}

// FunctionReach is the reach counted over every def, each named where it is declared: its outside calls and the
// classes it builds or names.
func (p *Program) FunctionReach() engine.ResourcePopulation {
	p.reach.once.Do(func() {
		reach := map[string]map[string]bool{}
		for _, module := range p.modules {
			for _, def := range module.Nodes() {
				if def.IsFunction() {
					reach[DeclarationOf(def)] = p.reachOf(def, module)
				}
			}
		}
		p.reach.functions = engine.Counting(reach)
	})

	return p.reach.functions
}

// IsTypeResource says whether the resource is something other than a verb: a class a function builds or names. A
// shared class is the subject two functions work on, never evidence that one of them forgot a step.
func IsTypeResource(resource string) bool {
	return !strings.HasPrefix(resource, functionResource)
}

// reachOf is what the def reaches: its outside calls and the classes it names.
func (p *Program) reachOf(def Node, module *Module) map[string]bool {
	reached := map[string]bool{}
	for _, expression := range def.OwnExpressions() {
		var resource string
		var ok bool
		if expression.IsCall() {
			resource, ok = p.resourceCalled(expression.Callee(), def, module)
		} else {
			resource, ok = classNamedByMypy(expression)
		}
		if ok {
			reached[resource] = true
		}
	}

	return reached
}

// resourceCalled is what calling the callee reaches: the class it builds, or the outside function it names. A
// function of the project's own is a collaborator, not a resource.
func (p *Program) resourceCalled(callee, caller Node, module *Module) (string, bool) {
	if class, ok := classNamedByMypy(callee); ok {
		return class, true
	}
	name, ok := p.outsideName(callee, caller, module)

	return functionResource + name, ok
}

// classNamedByMypy is the class a name or an attribute names, when mypy says calling it builds one.
func classNamedByMypy(expression Node) (string, bool) {
	resolved := expression.Node().Resolved
	if (expression.Kind() != "Name" && expression.Kind() != "Attribute") || resolved == nil || resolved.Constructs == "" {
		return "", false
	}

	return typeResource + resolved.Constructs, true
}

// outsideName is the dotted name the callee, called in the caller, has outside the project: through the module's
// absolute imports, or as a builtin. None for anything the project or the caller binds, or that cannot be named.
func (p *Program) outsideName(callee, caller Node, module *Module) (string, bool) {
	dotted := callee.DottedName()
	if dotted == "" {
		return "", false
	}
	head, rest, qualified := strings.Cut(dotted, ".")
	if imported, ok := module.bindings().absolute[head]; ok {
		name := imported
		if qualified {
			name += "." + rest
		}

		return name, !p.OwnsPackage(strings.Split(name, ".")[0])
	}
	if qualified || module.Binds(dotted) || caller.BindsLocally(dotted) {
		return "", false
	}

	return "builtins." + dotted, true
}

// BindsLocally says whether the def binds the name for itself: as a parameter, or by declaring, importing or
// assigning it anywhere in its body.
func (n Node) BindsLocally(name string) bool {
	for _, parameter := range n.Parameters() {
		if parameter.Name() == name {
			return true
		}
	}
	for _, statement := range n.statementsIn() {
		for _, bound := range append(statement.declaredNames(), statement.writtenNames()...) {
			if bound == name {
				return true
			}
		}
	}

	return false
}

// OwnExpressions is every expression the def evaluates itself: its parameters' defaults and what its body's
// statements hold, a nested def or class aside, which is a scope of its own.
func (n Node) OwnExpressions() []Node {
	var own []Node
	var walk func(Node)
	walk = func(node Node) {
		for _, child := range node.Children() {
			switch {
			case child.IsDefinition():
				continue
			case child.Node().Role == "expression":
				if child.IsEvaluated() {
					own = append(own, child)
				}
			case child.Node().Role == "type" || child.Node().Field == "decorator_list":
				continue
			}
			walk(child)
		}
	}
	for _, child := range n.Children() {
		if child.Node().Field == "args" || child.Node().Field == "body" {
			if child.IsDefinition() {
				continue
			}
			if child.Node().Role == "expression" && child.IsEvaluated() {
				own = append(own, child)
			}
			walk(child)
		}
	}

	return own
}
