package python

import (
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

const (
	functionResource = "fn:"
	typeResource     = "type:"
)

// resourceReach is what every def reaches, counted once.
type resourceReach struct {
	once      sync.Once
	functions engine.ResourcePopulation
}

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

func IsTypeResource(resource string) bool {
	return !strings.HasPrefix(resource, functionResource)
}

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

func (p *Program) resourceCalled(callee, caller Node, module *Module) (string, bool) {
	if class, ok := classNamedByMypy(callee); ok {
		return class, true
	}
	name, ok := p.outsideName(callee, caller, module)

	return functionResource + name, ok
}

func classNamedByMypy(expression Node) (string, bool) {
	resolved := expression.Node().Resolved
	if (expression.Kind() != "Name" && expression.Kind() != "Attribute") || resolved == nil || resolved.Constructs == "" {
		return "", false
	}

	return typeResource + resolved.Constructs, true
}

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
		if child.Node().Field != "args" && child.Node().Field != "body" {
			continue
		}
		if child.IsDefinition() {
			continue
		}
		if child.Node().Role == "expression" && child.IsEvaluated() {
			own = append(own, child)
		}
		walk(child)
	}

	return own
}
