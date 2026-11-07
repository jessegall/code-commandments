package fixture

import (
	"sort"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// Growth is what one rule cost over a fixture read at two sizes: the compute of a rule that reads across files is
// allowed to rise with the project, since to say nothing anywhere does this it must look everywhere, but the rise
// must stay proportional. A rule whose compute grows FASTER than the tree it reads is one a consumer cannot
// escape, and this is how it is caught before it ships rather than on their machine.
type Growth struct {
	Detector string
	Small    int64
	Large    int64
	Times    float64
}

func Grown(small, large *engine.Codebase, rules []detectors.Detector) []Growth {
	grown := make([]Growth, 0, len(rules))

	for _, rule := range rules {
		grown = append(grown, Growth{
			Detector: catalog.Name(rule),
			Small:    costOf(small, rule),
			Large:    costOf(large, rule),
		})
	}

	for at, each := range grown {
		if each.Small > 0 {
			grown[at].Times = float64(each.Large) / float64(each.Small)
		}
	}

	sort.Slice(grown, func(one, two int) bool { return grown[one].Times > grown[two].Times })

	return grown
}

func costOf(codebase *engine.Codebase, rule detectors.Detector) int64 {
	before := engine.Counted()

	rule.Find(codebase)

	return engine.Since(before).Points()
}
