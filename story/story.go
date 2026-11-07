package story

import (
	"fmt"
	"os"
	"runtime"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
)

// Story is one story being told: the test it fails, the journal it drives, the project it runs in, the file it is written in and every step it took, so a failure can say how it got there and the run can say which stories overlap.
type Story struct {
	t       *testing.T
	journal Journal
	project string
	file    string
	steps   []Step
	began   map[string]int
}

const (
	relayVariable   = "STORY_RELAY"
	projectVariable = "STORY_PROJECT"
)

func Begin(t *testing.T) *Story {
	t.Helper()

	address, project := os.Getenv(relayVariable), os.Getenv(projectVariable)
	if address == "" || project == "" {
		t.Fatalf("a story runs through scripts/stories, which starts the relay and names the project in %s and %s",
			relayVariable, projectVariable)
	}

	_, file, _, _ := runtime.Caller(1)

	s := &Story{t: t, journal: Reached(address), project: project, file: file, began: costsOf(project)}
	t.Cleanup(func() { thisRun.Keeps(Told{Name: t.Name(), File: s.file, Steps: s.steps}) })

	return s
}

func (s *Story) Asks(argv ...string) Ran {
	s.t.Helper()

	return s.AsksIn(s.project, argv...)
}

func (s *Story) AsksIn(folder string, argv ...string) Ran {
	s.t.Helper()

	s.steps = append(s.steps, journalStep(argv))

	ran, err := s.journal.Runs(folder, argv...)
	if err != nil {
		s.Fails(err.Error())
	}

	return ran
}

func journalStep(argv []string) Step {
	named := min(2, len(argv))

	shape := "asks the journal"
	for _, word := range argv[:named] {
		shape += " " + strings.ReplaceAll(word, "%", "%%")
	}

	for range argv[named:] {
		shape += " %s"
	}

	return StepOf(shape, argv[named:]...)
}

func (s *Story) Fails(why string) {
	s.t.Helper()

	s.t.Fatalf("%s\n\nthe story so far:\n  %s", why, strings.Join(textsOf(s.steps), "\n  "))
}

func (s *Story) GivenTheTreeIsHeldFor(folder string) {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("given the tree is held for %s", folder))

	warmed, err := s.journal.Runs(folder, "sins", "--changes")
	if err != nil || warmed.Code != 0 {
		s.Fails(fmt.Sprintf("the tree could not be warmed for %s: %v %s", folder, err, warmed.Err))
	}

	s.began = costsOf(s.project)
}

func (s *Story) TestCheckout() string {
	return s.project + "/.journal/worktrees/story-engine"
}

func (s *Story) Cost(name string) int {
	return costsOf(s.project)[name] - s.began[name]
}

func costsOf(project string) map[string]int {
	costs := ledger.Of(project)

	counted := map[string]int{}
	for _, name := range ledger.Names() {
		counted[name] = costs.Count(name)
	}

	return counted
}

func Checked(m *testing.M) int {
	code := m.Run()

	overlaps := thisRun.Overlaps()
	for _, overlap := range overlaps {
		fmt.Fprintln(os.Stderr, "✗ "+overlap)
	}

	if len(overlaps) > 0 {
		return 1
	}

	return code
}
