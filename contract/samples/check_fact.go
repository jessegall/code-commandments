package samples

import (
	"bytes"
	"encoding/json"
	"fmt"
	"reflect"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

var factsTellZeroFromAbsent = []string{"constantValue", "resolvedType", "effective", "exhaustive", "namedResults", "value", "target"}

func CheckFact(scenario map[string]Written, from Written, each marker.Marker) Occurrence {
	occurrence := Occurrence{Marker: each, Language: from.File.Language}
	name := each.Key[strings.LastIndex(each.Key, "/")+1:]
	node, err := Select(from.File, from.Bytes, each)
	if err != nil {
		return occurrence.failed(Wrong, err.Error())
	}
	written, present := writtenFacts(node)[name]
	if name == "resolvedType" && node.Resolved != nil {
		written, _ = json.Marshal(node.Resolved)
		present = true
	}
	occurrence.Actual = shownRaw(written, present)
	switch each.Operator {
	case "->":
		return occurrence.symbolic(scenario, from, written)
	case "=":
	default:
		return occurrence.failed(Invalid, "a fact marker takes = value or -> label")
	}
	occurrence.Expected = string(each.Value)
	expected := decoded(each.Value)
	if !present && isZero(expected) && !slices.Contains(factsTellZeroFromAbsent, name) {
		return occurrence.failed(Invalid, "false is indistinguishable from absent in this fact")
	}
	if !present {
		return occurrence.failed(Wrong, name+" is not written")
	}
	var holds bool
	switch name {
	case "constantValue":
		holds = constantHolds(expected, decoded(written))
	default:
		holds = fieldsHold(expected, decoded(written))
	}
	if !holds {
		occurrence.Status = Wrong

		return occurrence
	}
	occurrence.Status = Passed

	return occurrence
}

func (o Occurrence) symbolic(scenario map[string]Written, from Written, written json.RawMessage) Occurrence {
	if len(o.Marker.Targets) != 1 {
		return o.failed(Invalid, "a symbolic fact points at one declaration")
	}
	declaration, err := ResolveTarget(scenario, from, o.Marker.Targets[0])
	if err != nil {
		return o.failed(Invalid, err.Error())
	}
	if declaration.Facts == nil {
		return o.failed(Wrong, "the declaration carries no symbol")
	}
	o.Expected = declaration.Symbol
	actual, _ := decoded(written).(string)
	if object, isObject := decoded(written).(map[string]any); isObject {
		actual, _ = object["symbol"].(string)
	}
	o.Actual = actual
	if declaration.Symbol == "" || actual != declaration.Symbol {
		o.Status = Wrong

		return o
	}
	o.Status = Passed

	return o
}

func writtenFacts(node *contract.Node) map[string]json.RawMessage {
	held := map[string]json.RawMessage{}
	if node.Facts == nil {
		return held
	}
	raw, err := json.Marshal(node.Facts)
	if err != nil {
		return held
	}
	json.Unmarshal(raw, &held)

	return held
}

func decoded(raw json.RawMessage) any {
	if len(raw) == 0 {
		return nil
	}
	decoder := json.NewDecoder(bytes.NewReader(raw))
	decoder.UseNumber()
	var value any
	decoder.Decode(&value)

	return value
}

func shownRaw(raw json.RawMessage, present bool) string {
	if !present {
		return "nothing"
	}

	return string(raw)
}

func isZero(value any) bool {
	return value == false || value == json.Number("0") || value == ""
}

func constantHolds(expected, written any) bool {
	object, isObject := written.(map[string]any)
	if !isObject {
		return false
	}
	kind, value := object["type"], object["value"]
	switch stated := expected.(type) {
	case nil:
		return kind == "null" && value == nil
	case bool:
		return kind == "bool" && (value == stated || value == fmt.Sprint(stated))
	case json.Number:
		return kind != "string" && kind != "bool" && value == string(stated)
	case string:
		return kind == "string" && value == stated
	}

	return fieldsHold(expected, written)
}

func fieldsHold(expected, written any) bool {
	stated, isObject := expected.(map[string]any)
	if !isObject {
		return reflect.DeepEqual(expected, written)
	}
	held, isHeld := written.(map[string]any)
	if !isHeld {
		return false
	}
	for name, value := range stated {
		if name != "origin" && !fieldsHold(value, held[name]) {
			return false
		}
	}

	return true
}
