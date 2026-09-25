package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
	"github.com/jessegall/code-commandments/typescript"
	"github.com/jessegall/code-commandments/vue"
)

func init() {
	detectors.Register(catalog.Frontend, IndexAsKeyDetector{})
}

// IndexAsKeyDetector finds a v-for keyed by its index, `:key="index"`, which shifts as items are inserted or
// reordered. The two-alias form names an index only over an array, so it is flagged only when the iterable is
// a name whose type is provably one; over an object its second alias is the key.
type IndexAsKeyDetector struct{}

func (IndexAsKeyDetector) Sin() sins.Sin {
	return frontend.IndexAsKey{}
}

func (IndexAsKeyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return vue.In(codebase).
		WhereElement().
		Where(vue.HasDirective(vue.For)).
		Where(engine.As(keyedByLastAlias)).
		Where(func(m engine.Match) bool { return aliasIsAnIndex(codebase, vue.Of(m)) }).
		Get()
}

// keyedByLastAlias says whether the element's :key is the bare name of its v-for's last alias.
func keyedByLastAlias(element vue.Element) bool {
	aliases := element.Directive(vue.For).Aliases()
	key := element.Binding("key").Value()
	if len(aliases) < 2 || key.Kind() != "Identifier" {
		return false
	}
	last := aliases[len(aliases)-1]

	return last.Kind() == "Identifier" && last.Name() == key.Name()
}

// aliasIsAnIndex says whether the v-for's last alias is an index: always in the three-alias form, and in the
// two-alias form only over a name whose type is an array.
func aliasIsAnIndex(codebase *engine.Codebase, element vue.Element) bool {
	loop := element.Directive(vue.For)
	if len(loop.Aliases()) >= 3 {
		return true
	}
	iterable := loop.Iterable()
	if iterable.Kind() != "Identifier" {
		return false
	}
	iterated, ok := vue.ComponentOf(element.Match).TypeOf(codebase, iterable.Name())

	return ok && typescript.IsArray(iterated)
}
