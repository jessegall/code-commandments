package laravel

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// ContainerBindings is what the container is asked for and what it is told: every class the code outside its tests names outside its own registration, and the bound abstracts the codebase declares.
type ContainerBindings struct {
	referenced map[string]bool
	declared   map[string]bool
}

var containerBindings = engine.Memoised(readContainerBindings)

func ContainerBindingsOf(codebase *engine.Codebase) *ContainerBindings {
	return containerBindings.Of(codebase)
}

func (b *ContainerBindings) IsResolvedSomewhere(abstract string) bool {
	return abstract != "" && b.referenced[abstract]
}

func (b *ContainerBindings) IsDeclaredHere(abstract string) bool {
	return abstract != "" && b.declared[abstract]
}

func readContainerBindings(codebase *engine.Codebase) *ContainerBindings {
	bindings := &ContainerBindings{referenced: map[string]bool{}, declared: map[string]bool{}}
	var bound []string
	for _, file := range codebase.Of(contract.PHP).Files() {
		registrations := map[string][]contract.Span{}
		for _, node := range file.Nodes() {
			abstract := BoundAbstractOf(file.Match(node.ID))
			if abstract == "" {
				continue
			}
			registrations[abstract] = append(registrations[abstract], node.Span)
			if slices.Contains(bound, abstract) {
				continue
			}
			bound = append(bound, abstract)
		}
		for _, node := range file.Nodes() {
			match := file.Match(node.ID)
			if match.IsTest() {
				if abstract := ClassArgument(match, 0); abstract != "" &&
					((Node{Match: match}).ResolvesFromContainer() || testContainerResolution(match)) {
					bindings.referenced[abstract] = true
				}
				continue
			}
			for _, named := range []string{namedClass(match), strings.TrimLeft(php.Written(node.Written).Class(), `\`)} {
				if named != "" && !within(node.Span.Start, registrations[named]) {
					bindings.referenced[named] = true
				}
			}
		}
	}
	program := php.ProgramOf(codebase)
	for _, abstract := range bound {
		if _, ok := program.Declaration(abstract); ok {
			bindings.declared[abstract] = true
		}
	}

	return bindings
}

func testContainerResolution(call engine.Match) bool {
	receiver := call.Child("var")
	return call.Kind() == "Expr_MethodCall" && slices.Contains(ContainerResolvers, CallName(call)) &&
		receiver.Kind() == "Expr_PropertyFetch" && receiver.Child("var").Name() == "this" && receiver.Child("name").Name() == "app"
}

func namedClass(node engine.Match) string {
	switch {
	case isName(node):
		if parent := node.Parent().Kind(); parent == "UseItem" || slices.Contains([]string{"Stmt_Class", "Stmt_Interface", "Stmt_Trait", "Stmt_Enum"}, parent) {
			return ""
		}

		return strings.TrimLeft(node.Name(), `\`)
	case node.Kind() == "Scalar_String":
		if value, _ := node.Node().Value.Text(); strings.Contains(value, `\`) {
			return strings.TrimLeft(value, `\`)
		}
	}

	return ""
}

func within(start int, spans []contract.Span) bool {
	return slices.ContainsFunc(spans, func(span contract.Span) bool { return start >= span.Start && start < span.End })
}
