package hooks

import (
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/counter"
)

// surfaceDirs are the path segments that mark a test or fixture tree, matched whole so src/Contest is none.
var surfaceDirs = []string{"tests", "test", "spec", "specs", "stubs", "fixtures", "fixture", "mocks", "__mocks__", "__snapshots__", "snapshots"}

// SourceReminder nudges the agent, when it edits a test, stub or fixture judge never scans, to check the
// fix belongs at the source: the first such edit of a session, then every tenth.
type SourceReminder struct{}

func (SourceReminder) Class() string { return "SourceReminder" }
func (SourceReminder) Summary() string {
	return "When you edit a test/stub/fixture (which `judge` never scans), nudges you to check the real fix belongs at the SOURCE."
}
func (SourceReminder) Bindings() []Binding { return bound("PreToolUse", writers) }
func (SourceReminder) SpeaksToSubagents()  {}

func (SourceReminder) Handle(event Event) Response {
	if event.Name() != "PreToolUse" || !slices.Contains(writers, event.Tool()) {
		return Silent()
	}

	file := event.FilePath()
	if file == "" || !isSymptomSurface(file) || judged(event.Root, file) {
		return Silent()
	}

	nudges := counter.Named(event.Workspace(), "source-remind", `nudges "fix at the source, not the test" on an edit to an unjudged test/stub`, 10)
	if due, _ := nudges.FirstThenEvery(); !due {
		return Silent()
	}

	return Injecting(event.Name(), "Code Commandments — you're editing `"+filepath.Base(file)+"`, a test/stub/fixture that `judge` never "+
		"scans, so nothing here will flag a symptom-fix. If you're changing it to make a failing check pass, first "+
		"ask WHERE the failure is born: a failing test usually means the PRODUCTION code is missing behaviour — a "+
		"method, a type, a total value on the real type. Fix it THERE (trace to the source; re-open the relevant "+
		"skill), and change this file only if the TEST itself is wrong. If this is just legitimate test coverage, carry on.", false)
}

func judged(root, file string) bool {
	project, _ := config.Load(root)

	return project.IsJudged(root, file)
}

// isSymptomSurface says whether the file is a test, stub, fixture, mock or snapshot, by its folders or name.
func isSymptomSurface(file string) bool {
	segments := strings.Split(strings.ToLower(strings.ReplaceAll(file, `\`, "/")), "/")

	for _, segment := range segments {
		if slices.Contains(surfaceDirs, segment) {
			return true
		}
	}

	base := segments[len(segments)-1]

	for _, suffix := range []string{"test.php", "stub.php", "fixture.php", "mock.php", ".stub", ".snap", ".fixture"} {
		if strings.HasSuffix(base, suffix) {
			return true
		}
	}

	return strings.Contains(base, ".test.") || strings.Contains(base, ".spec.")
}
