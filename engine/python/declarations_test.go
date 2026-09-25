package python_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/python"
)

func TestEveryClassIsDeclaredAsThePHPEngineReadsIt(t *testing.T) {
	codebase := shopFixture(t)
	type declared struct {
		Enum      []string            `json:"enum"`
		TypedDict bool                `json:"typedDict"`
		Dataclass bool                `json:"dataclass"`
		Owned     map[string][]string `json:"owned"`
	}
	var want map[string]declared
	golden(t, "declarations", &want)
	program := codebase.Program
	for _, match := range codebase.WhereClass().Get() {
		class := python.Node{Match: match}
		key := match.Node().Symbol + "@" + place(class)
		got := declared{TypedDict: program.IsTypedDict(class.Name()), Owned: map[string][]string{}}
		if program.IsEnum(class.Name()) {
			got.Enum = class.MemberValueKeys()
			if got.Enum == nil {
				got.Enum = []string{}
			}
		}
		_, got.Dataclass = program.Dataclass(class.Name())
		for _, method := range class.Methods() {
			owned := program.OwnedParameters(method, class)
			if owned == nil {
				owned = []string{}
			}
			got.Owned[method.Name()] = owned
		}
		answer := want[key]
		for at, value := range answer.Enum {
			answer.Enum[at] = unescaped.Replace(value)
		}
		if a, b := marshal(got), marshal(answer); a != b {
			t.Errorf("%s: %s, not %s", key, a, b)
		}
	}
}

// unescaped decodes the escapes the PHP parser leaves raw in a string literal's value, which the bridge decodes.
var unescaped = strings.NewReplacer(`\n`, "\n", `\t`, "\t", `\\`, `\`, `\'`, "'", `\"`, `"`)
