package frontend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
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
			if boundary := vue.CommonAncestor(cluster); boundary.Exists() {
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
	return slices.ContainsFunc(element.Reads(), func(expression typescript.Node) bool {
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
	reactive := vue.ComponentOf(elements[0].Match).ModelRoots()
	type object struct {
		fields   map[string]bool
		elements []vue.Element
	}
	objects := map[string]*object{}
	var order []string
	for _, element := range elements {
		for _, chain := range element.Chains() {
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
