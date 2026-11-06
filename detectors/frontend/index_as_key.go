package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
)

func init() {
	detectors.Register(catalog.Vue, IndexAsKeyDetector{})
}

// IndexAsKeyDetector finds a v-for keyed by its index, `:key="index"`, which shifts as items are inserted or reordered.
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
		Reject(func(m engine.Match) bool { return listsPlainValues(codebase, vue.Of(m)) }).
		Get()
}

func keyedByLastAlias(element vue.Element) bool {
	aliases := element.Directive(vue.For).Aliases()
	key := element.Binding("key").Value()
	if len(aliases) < 2 || key.Kind() != "Identifier" {
		return false
	}
	last := aliases[len(aliases)-1]

	return last.Kind() == "Identifier" && last.Name() == key.Name()
}

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

func listsPlainValues(codebase *engine.Codebase, element vue.Element) bool {
	return iteratesPrimitives(codebase, element) && !element.HoldsState()
}

func iteratesPrimitives(codebase *engine.Codebase, element vue.Element) bool {
	iterable := element.Directive(vue.For).Iterable()
	if iterable.Kind() != "Identifier" {
		return false
	}
	iterated, ok := vue.ComponentOf(element.Match).TypeOf(codebase, iterable.Name())

	return ok && typescript.IsPrimitive(typescript.ElementOf(iterated))
}
