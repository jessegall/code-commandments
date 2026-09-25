package csharp

import (
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ConvertedArgumentDetector finds calls that convert what they hand one scalar parameter of a method of the codebase's own the same way, at half or more of the calls filling it: the conversion belongs in the method.
type ConvertedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ConvertedArgumentDetector{})
}

// Sin is the sin the detector finds.
func (ConvertedArgumentDetector) Sin() sins.Sin {
	return cssins.ConvertedArgument{}
}

// Find is every place the sin is committed.
func (ConvertedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program
	calls := cs.In(codebase).WhereCall().Get()
	supplied := map[string]int{}
	for _, match := range calls {
		call := cs.Node{Match: match}
		for position := range call.Arguments() {
			supplied[call.Target().Symbol()+"#"+strconv.Itoa(position)]++
		}
	}
	var dominant []engine.Match
	for _, bucket := range engine.RecurringBuckets(calls, func(match engine.Match) (string, bool) { return conversionSlot(cs.Node{Match: match}, program) }, 2) {
		slot, _ := conversionSlot(cs.Node{Match: bucket[0]}, program)
		if float64(len(bucket))/float64(supplied[slot[:strings.LastIndex(slot, "=")]]) >= 0.5 {
			dominant = append(dominant, bucket...)
		}
	}

	return dominant
}

// GroupKey is the group a call recurs in: the parameter of the codebase's own method it converts a scalar for, and
// the conversion.
func (ConvertedArgumentDetector) GroupKey(match engine.Match) (string, bool) {
	return conversionSlot(cs.Node{Match: match}, cs.Of(match.Codebase()))
}

// conversionSlot is the first parameter of the codebase's own method the call converts a scalar for, with the
// conversion: `Shop.Pricing.Quote(…)#1=Convert.ToInt32`; false for a call that converts none.
func conversionSlot(call cs.Node, program *cs.Program) (string, bool) {
	if !program.ReachesOwnSignature(call) {
		return "", false
	}
	conversions := call.ScalarConversions()
	var positions []int
	for position := range conversions {
		positions = append(positions, position)
	}
	if len(positions) == 0 {
		return "", false
	}
	sort.Ints(positions)

	return call.Target().Symbol() + "#" + strconv.Itoa(positions[0]) + "=" + conversions[positions[0]], true
}
