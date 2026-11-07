package story

import (
	"strings"
	"testing"
	"time"
)

func agentOf(s *Story) *Agent {
	return &Agent{s: s, name: "Ada", session: "session-ada", folder: "/project/.journal/worktrees/story-engine-ada", row: "41"}
}

func argvOf(sent request) string {
	return strings.Join(sent.Argv, " ")
}

func TestManyFilesAreWrittenInOneAskUnderTheScratchFolder(t *testing.T) {
	var asked *[]request

	t.Run("inner", func(t *testing.T) {
		var s *Story
		s, asked = storyOver(t, quiet)

		s.WritesMany(Python("a clean function", "x = 1\n"), 150)
	})

	if kinds := strings.Join(kindsOf(asked), " "); kinds != "write remove" {
		t.Fatalf("the relay was asked %q, where 150 files are one write and one removal", kinds)
	}

	if (*asked)[0].Many != 150 || !strings.HasSuffix((*asked)[0].Path, "/many.py") {
		t.Errorf("the relay was asked %+v, where the count and a file name to number must arrive", (*asked)[0])
	}
}

func TestAnAgentEditsManyFilesInItsOwnCheckout(t *testing.T) {
	s, asked := storyOver(t, quiet)

	agentOf(s).EditsMany(Python("a clean function", ""), 200)

	if sent := (*asked)[0]; sent.Many != 200 || !strings.Contains(sent.Path, "/story-engine-ada/bridge/mypy/story_scratch_") {
		t.Errorf("the relay was asked %+v, where the files are written under the agent's own worktree", sent)
	}
}

func TestTheSweepIsLetFallByWaiting(t *testing.T) {
	s, _ := storyOver(t, quiet)
	was := sweepFalls
	sweepFalls = 20 * time.Millisecond
	t.Cleanup(func() { sweepFalls = was })

	began := time.Now()
	s.LetsTheSweepFall()

	if time.Since(began) < 20*time.Millisecond {
		t.Error("the story did not wait for the sweep")
	}
}

func TestSeveralThingsAskedTogetherAreAnsweredEachInItsPlace(t *testing.T) {
	s, asked := storyOver(t, func(one request) Ran { return Ran{Out: strings.Join(one.Argv, " ")} })

	answers := s.AsksTogether("/project", []string{"sins", "--changes"}, []string{"commandments", "lanes"})

	if len(*asked) != 2 || answers[0].Out != "sins --changes" || answers[1].Out != "commandments lanes" {
		t.Errorf("the answers came back as %v, where each ask is answered in its own place", answers)
	}
}

func TestMovingTheTestCheckoutIsUndoneAtTheEnd(t *testing.T) {
	var asked *[]request

	t.Run("inner", func(t *testing.T) {
		var s *Story
		s, asked = storyOver(t, quiet)

		s.SwitchesToAnotherBranch()
		s.DetachesTheHead()
	})

	actions := []string{}
	for _, sent := range *asked {
		actions = append(actions, sent.Kind+":"+sent.Action)
	}

	if joined := strings.Join(actions, " "); joined != "checkout:switch checkout:detach checkout:back checkout:back" {
		t.Errorf("the relay was asked %q, where each move is undone when the story ends", joined)
	}
}

func TestMergingAWorkerAsksTheJournalToMergeItsRow(t *testing.T) {
	s, asked := storyOver(t, quiet)

	s.Merges(agentOf(s))

	if merge := argvOf((*asked)[0]); merge != "worker merge 41" {
		t.Errorf("the journal was asked %q, where the worker's row is merged", merge)
	}
}

func TestAWorkersHandInAsksForTheNextTaskFromItsWorktree(t *testing.T) {
	s, asked := storyOver(t, quiet)

	agentOf(s).HandsIn()

	if sent := (*asked)[0]; argvOf(sent) != "worker next --differences none" || sent.In != "/project/.journal/worktrees/story-engine-ada" {
		t.Errorf("the journal was asked %+v, where the hand-in is made from the worker's own worktree", sent)
	}
}

func TestAnEventNobodyKnowsAndAnEditWithNoFileAreSentAsHooks(t *testing.T) {
	s, asked := storyOver(t, quiet)
	ada := agentOf(s)

	ada.SendsAnEventNobodyKnows()
	ada.SendsAnEditNamingNoFile()

	if first := hookBody(t, (*asked)[0]); first["hook_event_name"] != "SomethingElse" {
		t.Errorf("the first hook carried %v", first)
	}

	second := hookBody(t, (*asked)[1])
	if input, _ := second["tool_input"].(map[string]any); len(input) != 0 {
		t.Errorf("the second hook carried %v, where the edit names no file", second)
	}
}

func TestAFixtureIsOneStepHoweverManyItTook(t *testing.T) {
	s, _ := storyOver(t, quiet)

	s.Given("a held tree and a main agent", func() {
		s.steps = append(s.steps, StepOf("one"), StepOf("two"))
	})
	s.steps = append(s.steps, StepOf("three"))

	if texts := strings.Join(textsOf(s.steps), "|"); texts != "given a held tree and a main agent|three" {
		t.Errorf("the steps read %q, where the fixture's own steps fold into one", texts)
	}
}

func TestTwoStoriesThatWriteTheSameNameAreGivenNamesOfTheirOwn(t *testing.T) {
	first, _ := storyOver(t, quiet)
	var second *Story

	t.Run("second", func(t *testing.T) {
		second, _ = storyOver(t, quiet)
	})

	if first.own("order.py") == second.own("order.py") {
		t.Errorf("both stories name their file %q, so the overlap check could not tell their steps apart", first.own("order.py"))
	}
}

func TestARehearsalTellsAStoryWithNoJournalAndNoWaiting(t *testing.T) {
	s := &Story{t: t, journal: Journal{rehearsing: true}, project: "/rehearsal", file: "world_test.go", began: map[string]int{}, rehearsal: true}
	ada := agentOf(s)

	ada.Edits(Python("an else", ""), "order.py")
	ada.Hears("never told")
	ada.StaysUntold("never told")
	s.LetsTheSweepFall()
	s.Says(Ran{}, "something that was never said")
	s.CostIs("tree_reads_started", 5)

	if len(s.steps) != 5 {
		t.Errorf("the rehearsal recorded %d steps, where each of the five steps it took is recorded", len(s.steps))
	}
}

func TestAnAgentCanSendAnyNamedEvent(t *testing.T) {
	s, asked := storyOver(t, quiet)

	agentOf(s).SendsTheEvent("SubagentStop")

	if body := hookBody(t, (*asked)[0]); body["hook_event_name"] != "SubagentStop" || body["session_id"] != "session-ada" {
		t.Errorf("the hook carried %v, where the named event is sent for the agent's session", body)
	}
}

func TestAMeantRefusalPassesOnACodeThatIsNotZero(t *testing.T) {
	s, _ := storyOver(t, quiet)

	s.Refuses(Ran{Code: 126})
}

func TestAnEndedAgentIsEndedByItsRow(t *testing.T) {
	s, asked := storyOver(t, quiet)

	agentOf(s).Ends()

	if ended := argvOf((*asked)[0]); ended != "agent end 41" {
		t.Errorf("the journal was asked %q", ended)
	}

	if process := (*asked)[1]; process.Kind != "pid" || process.Action != "end" {
		t.Errorf("the relay was asked %+v, where the agent's process is ended with it", process)
	}
}

func TestALeavingWorkerIsEndedAndItsWorktreeRemoved(t *testing.T) {
	s, asked := storyOver(t, quiet)

	agentOf(s).Leaves()

	if kinds := strings.Join(kindsOf(asked), " "); kinds != "command pid worktree" {
		t.Fatalf("the relay was asked %q, where the agent is ended, then its process, then its worktree removed", kinds)
	}

	if (*asked)[2].Name != "story-engine-ada" || (*asked)[2].Action != "drop" {
		t.Errorf("the worktree was asked for as %+v", (*asked)[2])
	}
}

func TestADeletedFileIsRemovedFromTheAgentsOwnScratchFolder(t *testing.T) {
	s, asked := storyOver(t, quiet)

	agentOf(s).Deletes(Python("a clean function", ""), "total.py")

	if sent := (*asked)[0]; sent.Kind != "remove" || !strings.Contains(sent.Path, "/story-engine-ada/bridge/mypy/story_scratch_") || !strings.HasSuffix(sent.Path, "/total.py") {
		t.Errorf("the relay was asked %+v, where the file under the agent's own worktree is removed", sent)
	}
}

func TestARebaseIsAskedForTheAgentsWorktreeByName(t *testing.T) {
	s, asked := storyOver(t, quiet)

	agentOf(s).Rebases()

	if sent := (*asked)[0]; sent.Kind != "worktree" || sent.Action != "rebase" || sent.Name != "story-engine-ada" {
		t.Errorf("the relay was asked %+v", sent)
	}
}

func TestAnEditCanNameItsFileRelativeToTheFolderOrWithBackslashesOrTwice(t *testing.T) {
	s, asked := storyOver(t, quiet)
	ada := agentOf(s)

	ada.EditsByRelativePath(Python("an else", ""), "order.py")
	ada.EditsByWindowsPath(Python("an else", ""), "order.py")
	ada.EditsTwice(Python("an else", ""), "order.py")

	var hooks []map[string]any
	for _, sent := range *asked {
		if sent.Kind == "hook" {
			hooks = append(hooks, hookBody(t, sent))
		}
	}

	if len(hooks) != 4 {
		t.Fatalf("%d hooks were sent, where one, one and two are meant", len(hooks))
	}

	relative := hooks[0]["tool_input"].(map[string]any)["file_path"].(string)
	windows := hooks[1]["tool_input"].(map[string]any)["file_path"].(string)

	if strings.HasPrefix(relative, "/") || !strings.HasSuffix(relative, "/order.py") {
		t.Errorf("the relative edit named %q", relative)
	}

	if strings.Contains(windows, "/") || !strings.HasSuffix(windows, `\order.py`) {
		t.Errorf("the backslash edit named %q", windows)
	}
}
