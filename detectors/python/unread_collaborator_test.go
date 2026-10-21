package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestACollaboratorNothingReadsIsFoundAndAReadOneIsNot holds the rule to the fault it names: a collaborator
// __init__ keeps under a name of its own that nothing anywhere reads. A collaborator one method reads, one a
// subclass reads, a setting rather than a collaborator, and a class that answers reads it does not declare are
// all how the construct earns its place and must pass.
func TestACollaboratorNothingReadsIsFoundAndAReadOneIsNot(t *testing.T) {
	const mailer = "class Mailer:\n    def send(self, to: str) -> None:\n        pass\n\n\n"
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"kept and never read":        {mailer + "class Desk:\n    def __init__(self, mailer: Mailer) -> None:\n        self._mailer = mailer\n\n    def post(self, to: str) -> str:\n        return to\n", 1},
		"kept and read by a method":  {mailer + "class Desk:\n    def __init__(self, mailer: Mailer) -> None:\n        self._mailer = mailer\n\n    def post(self, to: str) -> None:\n        self._mailer.send(to)\n", 0},
		"kept and read by a child":   {mailer + "class Desk:\n    def __init__(self, mailer: Mailer) -> None:\n        self._mailer = mailer\n\n\nclass Bench(Desk):\n    def post(self, to: str) -> None:\n        self._mailer.send(to)\n", 0},
		"a setting, not a messenger": {"class Desk:\n    def __init__(self, prefix: str) -> None:\n        self._prefix = prefix\n\n    def post(self, to: str) -> str:\n        return to\n", 0},
		"kept under a public name":   {mailer + "class Desk:\n    def __init__(self, mailer: Mailer) -> None:\n        self.mailer = mailer\n\n    def post(self, to: str) -> str:\n        return to\n", 0},
		"a class that answers reads": {mailer + "class Desk:\n    def __init__(self, mailer: Mailer) -> None:\n        self._mailer = mailer\n\n    def __getattr__(self, name: str) -> object:\n        return None\n", 0},
		"a class that reads its own dict": {mailer + "class Desk:\n    def __init__(self, mailer: Mailer) -> None:\n        self._mailer = mailer\n\n    def shown(self) -> dict:\n        return dict(self.__dict__)\n", 0},
		"kept and read in __init__":  {mailer + "class Desk:\n    def __init__(self, mailer: Mailer) -> None:\n        self._mailer = mailer\n        self._to = self._mailer\n\n    def post(self, to: str) -> str:\n        return to\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/desk.py": test.source})
			found := (python.UnreadCollaboratorDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
