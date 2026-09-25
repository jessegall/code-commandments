package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// ConvertedArgument is a scalar parameter its callers keep filling with the same conversion — `ReceiptFor(order.Id.ToString())` call after call — because it asks for the converted form instead of the value.
type ConvertedArgument struct{}

func init() {
	sins.Register(catalog.CSharp, ConvertedArgument{})
}

// Definition is what the sin states about itself.
func (ConvertedArgument) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-converted-argument",
		Skill:       skills.PassTheObject{},
		Description: "a scalar parameter its callers keep filling with the same conversion — `ReceiptFor(order.Id.ToString())` call after call — because it asks for the converted form instead of the value",
		Rule:        "Declare the parameter in the type callers actually hold and convert inside — one rule about the conversion, in one place.",
		Suggestion:  "Move the conversion into the method and take what the callers had (`ReceiptFor(Order order)` or `ReceiptFor(int orderId)`); a caller that forgets the conversion can no longer pass the wrong thing.",
	}
}
