package story

import (
	"strings"
	"testing"
)

var (
	opens     = StepOf("a project of %s Python files is served", "300")
	cuts      = StepOf("worker %s is cut a worktree", "Ada")
	writesOne = StepOf("worker %s writes %s", "Ada", "src/order.py")
	judges    = StepOf("worker %s runs %s", "Ada", "journal sins --changes")
	stops     = StepOf("worker %s stops", "Ada")
)

func TestTwoStoriesOfOneFileThatOpenAlikeAreToldToShareAFixture(t *testing.T) {
	run := &Telling{}
	run.Keeps(Told{Name: "TestAWorkerIsJudged", File: "workers_test.go", Steps: []Step{opens, cuts, writesOne, judges}})
	run.Keeps(Told{Name: "TestAWorkerStops", File: "workers_test.go", Steps: []Step{opens, cuts, stops}})

	said := run.Overlaps()

	if len(said) != 1 || !strings.Contains(said[0], "open with the same 2 steps") {
		t.Fatalf("two stories opening with the same two steps were answered %q, where they must be told to share a fixture", said)
	}

	if !strings.Contains(said[0], "worker Ada is cut a worktree") {
		t.Errorf("the overlap does not name the shared steps, so the reader cannot see what to move: %q", said[0])
	}
}

func TestStoriesInDifferentFilesMayOpenAlike(t *testing.T) {
	run := &Telling{}
	run.Keeps(Told{Name: "TestAWorkerIsJudged", File: "workers_test.go", Steps: []Step{opens, cuts, writesOne, judges}})
	run.Keeps(Told{Name: "TestAMergeLands", File: "merges_test.go", Steps: []Step{opens, cuts, stops}})

	if said := run.Overlaps(); len(said) != 0 {
		t.Errorf("stories in two files were told to share a fixture: %q", said)
	}
}

func TestOneSharedStepIsNotYetAFixture(t *testing.T) {
	run := &Telling{}
	run.Keeps(Told{Name: "TestAWorkerIsJudged", File: "workers_test.go", Steps: []Step{opens, writesOne, judges}})
	run.Keeps(Told{Name: "TestAWorkerStops", File: "workers_test.go", Steps: []Step{opens, stops}})

	if said := run.Overlaps(); len(said) != 0 {
		t.Errorf("two stories sharing only their first step were told to share a fixture: %q", said)
	}
}

func TestAStoryThatOnlyRephrasesAnotherIsCaughtAnywhere(t *testing.T) {
	run := &Telling{}
	run.Keeps(Told{Name: "TestAdaIsJudged", File: "workers_test.go", Steps: []Step{
		StepOf("worker %s writes %s", "Ada", "src/order.py"),
		StepOf("worker %s runs %s", "Ada", "journal sins --changes"),
	}})
	run.Keeps(Told{Name: "TestGraceIsJudged", File: "other_test.go", Steps: []Step{
		StepOf("worker %s writes %s", "Grace", "src/cart.py"),
		StepOf("worker %s runs %s", "Grace", "journal sins --changes"),
	}})

	said := run.Overlaps()

	if len(said) != 1 || !strings.Contains(said[0], "one story told twice") {
		t.Errorf("two stories with the same steps in other words were answered %q, where they are one story told twice", said)
	}
}

func TestStoriesThatDoDifferentThingsDoNotOverlap(t *testing.T) {
	run := &Telling{}
	run.Keeps(Told{Name: "TestAWorkerIsJudged", File: "workers_test.go", Steps: []Step{cuts, writesOne, judges}})
	run.Keeps(Told{Name: "TestAWorkerStops", File: "workers_test.go", Steps: []Step{stops, writesOne}})

	if said := run.Overlaps(); len(said) != 0 {
		t.Errorf("two different stories were said to overlap: %q", said)
	}
}

func TestACommandStepIsShapedByItsNounAndVerb(t *testing.T) {
	listsEnvironments := journalStep([]string{"environment", "list"})
	listsTasks := journalStep([]string{"task", "list"})
	showsOneTask := journalStep([]string{"task", "show", "12"})
	showsAnother := journalStep([]string{"task", "show", "40"})

	if listsEnvironments.Shape == listsTasks.Shape {
		t.Errorf("listing environments and listing tasks have one shape, %q, so two different stories read as one", listsTasks.Shape)
	}

	if showsOneTask.Shape != showsAnother.Shape {
		t.Errorf("showing task 12 and task 40 have different shapes, so a story that only changed the number is not caught")
	}

	if showsOneTask.Text() != "asks the journal task show 12" {
		t.Errorf("the step reads %q", showsOneTask.Text())
	}
}
