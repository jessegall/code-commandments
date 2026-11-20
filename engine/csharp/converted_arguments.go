package csharp

import (
	"github.com/jessegall/code-commandments/engine"
	"strconv"
)

func callConversion(match engine.Match) engine.CallConversion {
	call := Node{Match: match}
	target := call.Target().Symbol()
	evidence := engine.CallConversion{}
	for position := range call.Arguments() {
		evidence.Slots = append(evidence.Slots, target+"#"+strconv.Itoa(position))
	}
	if !Of(match.Codebase()).ReachesOwnSignature(call) {
		return evidence
	}
	picked := len(evidence.Slots)
	for position, key := range call.ScalarConversions() {
		if position < picked {
			picked = position
			evidence.Key = evidence.Slots[position] + "=" + key
		}
	}
	return evidence
}
