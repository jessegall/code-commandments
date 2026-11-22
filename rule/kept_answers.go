package rule

import (
	"slices"
	"sync"
	"sync/atomic"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// keptAnswers holds, for one judging, the answer of each definition that reads the whole program or is written kept, by
// the values it was handed and the language of the site asking: a definition sees only its own arguments, so the same
// arguments give the same answer, and a callee's calls or a type's supertypes are gathered once, not once per site.
type keptAnswers struct {
	kept    map[string]bool
	answers sync.Map
}

var answersAsked atomic.Int64

type answerKey struct {
	definition string
	language   contract.Language
	arguments  [3]value
}

func keepingAnswers(definitions map[string]definition) *keptAnswers {
	reads := map[string]bool{}
	for name, defined := range definitions {
		defined.Expr.Each(func(each Expr) {
			reads[name] = reads[name] || each.IsCall() && slices.Contains(readsTheWholeTree, each.Operator)
		})
	}
	for grew := true; grew; {
		grew = false
		for name, defined := range definitions {
			if reads[name] {
				continue
			}
			defined.Expr.Each(func(each Expr) {
				if each.IsCall() && reads[each.Operator] && !reads[name] {
					reads[name], grew = true, true
				}
			})
		}
	}

	for name, defined := range definitions {
		reads[name] = reads[name] || defined.Kept
	}

	return &keptAnswers{kept: reads}
}

func (k *keptAnswers) answer(name string, site engine.Match, arguments []value, ask func() value) value {
	key, keyed := k.keyOf(name, site, arguments)
	if !keyed {
		engine.CountedCheck()

		return ask()
	}
	if kept, found := k.answers.Load(key); found {
		return kept.(value)
	}
	answersAsked.Add(1)
	engine.CountedCheck()
	answered := ask()
	k.answers.Store(key, answered)

	return answered
}

func (k *keptAnswers) keyOf(name string, site engine.Match, arguments []value) (answerKey, bool) {
	if k == nil || !k.kept[name] || len(arguments) > len(answerKey{}.arguments) {
		return answerKey{}, false
	}
	key := answerKey{definition: name}
	if source := site.Source(); source != nil {
		key.language = source.Language()
	}
	for at, argument := range arguments {
		switch argument.held.(type) {
		case nil, string, bool, int, int64, float64, engine.Match:
			key.arguments[at] = argument
		default:
			return answerKey{}, false
		}
	}

	return key, true
}
