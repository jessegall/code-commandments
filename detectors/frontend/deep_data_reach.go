package frontend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
	"github.com/jessegall/code-commandments/typescript"
	"github.com/jessegall/code-commandments/vue"
)

func init() {
	detectors.Register(catalog.Frontend, DeepDataReachDetector{})
}

const (
	// reachTemplateLines is how long a template must be before its deep reaches are worth a component.
	reachTemplateLines = 50
	// reachDepth is how many hops past its root a reach must take: order.customer.name takes 2.
	reachDepth = 2
	// reachFields is how many distinct fields of one nested object a cluster reads.
	reachFields = 2
)

// transparent are the accessors that read through a value rather than deeper into it.
var transparent = []string{"value", "length"}

// DeepDataReachDetector finds elements of a sizeable template that reach two or more fields deep into one
// nested object, order.customer.name and order.customer.email: a child component that wants the mid-object
// as a prop. Each cluster is reported at the lowest element holding all its reaches.
type DeepDataReachDetector struct{}

func (DeepDataReachDetector) Sin() sins.Sin {
	return frontend.DeepDataReach{}
}

func (DeepDataReachDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := vue.In(codebase).
		WhereElement().
		Where(engine.As(inSizeableTemplate)).
		Where(engine.As(reachesDeep)).
		Get()
	var findings []engine.Match
	for _, component := range byComponent(candidates) {
		for _, cluster := range clusters(component) {
			if boundary := commonAncestor(cluster); boundary.Exists() {
				findings = append(findings, boundary.Match)
			}
		}
	}

	return findings
}

func inSizeableTemplate(element vue.Element) bool {
	return vue.ComponentOf(element.Match).TemplateLines() >= reachTemplateLines
}

// reachesDeep says whether any data the element reads, its v-for's iterable included, reaches deep.
func reachesDeep(element vue.Element) bool {
	read := append(element.Expressions(), typescript.Of(element.Directive(vue.For).Iterable()))

	return slices.ContainsFunc(read, func(expression typescript.Node) bool {
		return expression.MemberDepth(transparent...) >= reachDepth
	})
}

// byComponent groups elements by the component they sit in, in order.
func byComponent(elements []engine.Match) [][]vue.Element {
	var groups [][]vue.Element
	index := map[string]int{}
	for _, element := range elements {
		at, ok := index[element.File()]
		if !ok {
			at = len(groups)
			index[element.File()] = at
			groups = append(groups, nil)
		}
		groups[at] = append(groups[at], vue.Of(element))
	}

	return groups
}

// clusters is each nested object a component's elements read two or more fields of, as the elements that
// read it. A chain rooted in a v-model's data is the form's own state, not a reach.
func clusters(elements []vue.Element) [][]vue.Element {
	reactive := reactiveRoots(vue.ComponentOf(elements[0].Match))
	type object struct {
		fields   map[string]bool
		elements []vue.Element
	}
	objects := map[string]*object{}
	var order []string
	for _, element := range elements {
		for _, chain := range chainsOf(element) {
			chain = slices.DeleteFunc(chain, func(segment string) bool { return slices.Contains(transparent, segment) })
			if len(chain) <= reachDepth || slices.Contains(reactive, chain[0]) {
				continue
			}
			key := chain[0] + "." + chain[1]
			if objects[key] == nil {
				objects[key] = &object{fields: map[string]bool{}}
				order = append(order, key)
			}
			objects[key].fields[strings.Join(chain, ".")] = true
			if !slices.ContainsFunc(objects[key].elements, func(each vue.Element) bool { return each.Node() == element.Node() }) {
				objects[key].elements = append(objects[key].elements, element)
			}
		}
	}
	var found [][]vue.Element
	for _, key := range order {
		if len(objects[key].fields) >= reachFields {
			found = append(found, objects[key].elements)
		}
	}

	return found
}

// chainsOf is every member chain the element reads, its v-for's iterable included.
func chainsOf(element vue.Element) [][]string {
	var chains [][]string
	for _, expression := range element.Expressions() {
		chains = append(chains, expression.Chains()...)
	}

	return append(chains, typescript.Of(element.Directive(vue.For).Iterable()).Chains()...)
}

// reactiveRoots is every name a v-model in the component binds from.
func reactiveRoots(component vue.Component) []string {
	var roots []string
	for _, node := range component.Template().Descendants() {
		if directive := (vue.Directive{Match: node}); directive.Named(vue.Model) {
			roots = append(roots, typescript.Of(directive.Value()).Roots()...)
		}
	}

	return roots
}

// commonAncestor is the deepest element holding every one of them; no node when only the template does.
func commonAncestor(elements []vue.Element) vue.Element {
	common := ancestry(elements[0])
	for _, element := range elements[1:] {
		spine := ancestry(element)
		common = slices.DeleteFunc(common, func(node *contract.Node) bool { return !slices.Contains(spine, node) })
	}
	for _, element := range ancestryElements(elements[0]) {
		if len(common) > 0 && element.Node() == common[0] {
			return element
		}
	}

	return vue.Element{}
}

func ancestry(element vue.Element) []*contract.Node {
	var spine []*contract.Node
	for _, each := range ancestryElements(element) {
		spine = append(spine, each.Node())
	}

	return spine
}

func ancestryElements(element vue.Element) []vue.Element {
	var spine []vue.Element
	for at := element; at.Exists(); at = at.Parent() {
		spine = append(spine, at)
	}

	return spine
}
