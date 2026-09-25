package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/python/pythontest"
)

func TestADefinitionAnswersWhatItsDecoratorsAndSignatureSay(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{"clock.py": `
import dataclasses
from typing import NoReturn


@dataclasses.dataclass(frozen=True)
class Clock:
    def __init__(self, hour: int) -> None:
        self.hour = hour

    @staticmethod
    def zero() -> "Clock":
        return Clock(0)

    @classmethod
    def at(cls, hour: int, **options: str) -> "Clock":
        def inner() -> int:
            return 1
        return cls(hour)

    @property
    def label(self) -> str:
        return str(self.hour)

    def fail(self) -> NoReturn:
        raise ValueError(self.hour)
`})
	defs := map[string]python.Node{}
	var class python.Node
	for _, match := range codebase.Files()[0].Match(0).Descendants() {
		node := python.Node{Match: match}
		if node.IsFunction() {
			defs[node.Name()] = node
		}
		if node.Kind() == "ClassDef" {
			class = node
		}
	}
	checks := map[string]bool{
		"a dataclass":                 class.IsDataclass(),
		"__init__ is its initializer": class.Initializer() == defs["__init__"],
		"five methods":                len(class.Methods()) == 5,
		"a method":                    defs["zero"].IsMethod() && !defs["inner"].IsMethod(),
		"a staticmethod":              defs["zero"].IsStatic() && !defs["at"].IsStatic(),
		"a classmethod":               defs["at"].IsClassMethod(),
		"a property":                  defs["label"].IsPropertyGetter(),
		"a dunder":                    defs["__init__"].IsDunder() && !defs["zero"].IsDunder(),
		"a keyword rest":              defs["at"].TakesKeywordRest() && !defs["zero"].TakesKeywordRest(),
		"returns nothing":             defs["fail"].ReturnsNothing() && defs["__init__"].ReturnsNothing() && !defs["zero"].ReturnsNothing(),
		"every returned value":        len(defs["at"].ReturnedValues()) == 2,
		"a raise bails out":           defs["fail"].ChildrenIn("body")[0].IsBailOut(),
		"a keyword argument":          class.Decorators()[0].Keyword("frozen").Node().Value != nil,
	}
	for check, holds := range checks {
		if !holds {
			t.Errorf("%s does not hold", check)
		}
	}
}
