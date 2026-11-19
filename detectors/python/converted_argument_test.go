package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

const tipJar = "class Cents:\n    @classmethod\n    def parse(cls, typed: str) -> int:\n        return round(float(typed) * 100)\n\n\nclass TipJar:\n    def __init__(self) -> None:\n        self.total = 0\n\n    def add(self, cents: int) -> None:\n        self.total += cents\n\n\ndef ring_up(jar: TipJar, cents: int) -> None:\n    jar.add(cents)\n"

const convertingCalls = "from app.tips import Cents, TipJar\n\n\ndef fill(jar: TipJar) -> None:\n    jar.add(Cents.parse(\"1.00\"))\n    jar.add(Cents.parse(\"2.00\"))\n    jar.add(Cents.parse(\"3.00\"))\n"

func TestATestConvertingWhatItHandsProductionCodeSaysNothingAboutItsParameter(t *testing.T) {
	for name, test := range map[string]struct {
		caller string
		want   int
	}{
		"production code converting at most calls": {"app/filling.py", 3},
		"a test converting at most calls":          {"app/test_filling.py", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/__init__.py": "", "app/tips.py": tipJar, test.caller: convertingCalls})
			found := (python.ConvertedArgumentDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
