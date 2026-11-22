package marker

import (
	"reflect"
	"strings"
	"testing"
)

func TestADirectiveReadsAsItsKeyOwnerColumnOperatorAndTargets(t *testing.T) {
	for text, want := range map[string]Directive{
		"@contract kind/loop":                             {Key: "kind/loop"},
		"@contract link/break/destination -> outer":       {Key: "link/break/destination", Operator: "->", Targets: []Target{{Label: "outer"}}},
		"@contract fact/*/constantValue on reference = 3": {Key: "fact/*/constantValue", Owner: "reference", Operator: "=", Value: []byte("3")},
		"@contract fact/text/value at 9 = \"Item \"":      {Key: "fact/text/value", Column: 9, Operator: "=", Value: []byte(`"Item "`)},
		"@contract role/call/arguments -> [first, line(4,literal,7)]": {Key: "role/call/arguments", Operator: "->",
			Targets: []Target{{Label: "first"}, {Line: 4, Kind: "literal", Column: 7}}},
		"@contract role/arm/patterns -> []":                     {Key: "role/arm/patterns", Operator: "->", Targets: []Target{}},
		"@contract fact/*/obligations -> one.py#area":           {Key: "fact/*/obligations", Operator: "->", Targets: []Target{{Path: "one.py", Label: "area"}}},
		"@contract fact/*/constantValue = {\"type\": \"null\"}": {Key: "fact/*/constantValue", Operator: "=", Value: []byte(`{"type": "null"}`)},
		"@label outer loop":                                     {Label: "outer", Owner: "loop"},
		"@label first argument 12":                              {Label: "first", Owner: "argument", Column: 12},
	} {
		got, err := Parse(text)
		if err != nil {
			t.Errorf("%s: %v", text, err)

			continue
		}
		if !reflect.DeepEqual(got, want) {
			t.Errorf("%s:\nread %+v\nwant %+v", text, got, want)
		}
	}
}

func TestADirectiveThatDoesNotEndWhereItShouldIsRefusedByName(t *testing.T) {
	for text, fault := range map[string]string{
		"@contract":                                   "names no item key",
		"@contract fact/text/value = \"unclosed":      "not complete JSON",
		"@contract fact/*/constantValue = 3 junk":     "text after the value",
		"@contract link/break/destination -> outer x": "text after the target",
		"@contract role/call/arguments -> [a, b":      "not closed",
		"@contract kind/loop ~ outer":                 "neither an operator",
		"@label 9lives loop":                          "not an ASCII identifier",
		"@label outer loop 3 extra":                   "takes a name, a kind",
	} {
		if _, err := Parse(text); err == nil || !strings.Contains(err.Error(), fault) {
			t.Errorf("%s: refused with %v, not naming %q", text, err, fault)
		}
	}
}
