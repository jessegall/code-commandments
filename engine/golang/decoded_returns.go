package golang

import "github.com/jessegall/code-commandments/engine"

func decodedDestination(call engine.Match) engine.Match {
	var at int
	switch call.TargetSymbol() {
	case "encoding/json.Unmarshal":
		at = 1
	case "encoding/json.Decoder.Decode":
		at = 0
	default:
		if call.Child("fun").Name() != "Decode" || call.Receiver().TargetSymbol() != "encoding/json.NewDecoder" {
			return engine.Match{}
		}
	}
	arguments := call.Arguments()
	if len(arguments) <= at || arguments[at].Operator() != "&" {
		return engine.Match{}
	}
	return arguments[at].Child("x")
}

func returnsUntypedData(value engine.Match) bool {
	typed := value.Typed()
	return typed != nil && (typed.Text == "any" || typed.Text == "interface{}" || engine.IsUntypedCollection(typed))
}
