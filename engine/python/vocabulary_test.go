package python_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/python"
)

func TestAStringIsNamedByTheConstantThePHPEngineNamesItBy(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string]string
	golden(t, "vocabulary", &want)
	named := map[string]string{}
	for _, match := range codebase.WhereCall().Get() {
		call := python.Node{Match: match}
		arguments := call.Arguments()
		for _, keyword := range call.Keywords() {
			arguments = append(arguments, keyword.Child("value"))
		}
		for _, literal := range arguments {
			if name, ok := codebase.Program.ConstantFor(call, literal); ok && literal.Node().Literal == "string" {
				named[strings.TrimPrefix(match.File(), fixture.root+"/")+"@"+itoa(literal.Node().Span.Start)+"-"+itoa(literal.Node().Span.End)] = name
			}
		}
	}
	if marshal(named) != marshal(want) {
		t.Errorf("%v, not %v", named, want)
	}
}
