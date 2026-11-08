package story

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
)

func storyOver(t *testing.T, answer func(request) Ran) (*Story, *[]request) {
	t.Helper()

	address, asked := relayAnswering(t, answer)

	return &Story{t: t, journal: Reached(address), project: t.TempDir(), file: "builder_test.go", began: map[string]int{}}, asked
}

func quiet(request) Ran {
	return Ran{}
}

func kindsOf(asked *[]request) []string {
	var kinds []string
	for _, one := range *asked {
		kinds = append(kinds, one.Kind)
	}

	return kinds
}

func TestASampleIsWrittenUnderTheScratchFolderOfItsStoryAndRemovedAtTheEnd(t *testing.T) {
	var s *Story
	var asked *[]request

	t.Run("inner", func(t *testing.T) {
		s, asked = storyOver(t, quiet)

		path := s.Writes(Python("an else after a return", "x = 1\n"), "order.py")

		if !strings.Contains(path, "/bridge/mypy/story_scratch_testasampleiswrittenunderthescratchfolderofitsstoryandremovedattheend_inner/order.py") {
			t.Errorf("the file was written at %q, where it must sit under a scratch folder named for its story", path)
		}
	})

	kinds := kindsOf(asked)
	if strings.Join(kinds, " ") != "write look remove" {
		t.Fatalf("the relay was asked %v, where the file is written, the journal looks at the checkout and then the folder is removed when the story ends", kinds)
	}

	if (*asked)[0].Stdin != "x = 1\n" || (*asked)[1].In != s.TestCheckout() || (*asked)[2].Path != s.ScratchIn(s.TestCheckout(), "bridge/mypy") {
		t.Errorf("the relay was asked %+v, where the text and the scratch folder to remove must both arrive", *asked)
	}
}

func TestTwoFilesInOneScratchFolderAreRemovedOnce(t *testing.T) {
	var asked *[]request

	t.Run("inner", func(t *testing.T) {
		var s *Story
		s, asked = storyOver(t, quiet)

		s.Writes(Python("a first sample", "a = 1\n"), "a.py")
		s.Writes(Python("a second sample", "b = 1\n"), "b.py")
	})

	if kinds := strings.Join(kindsOf(asked), " "); kinds != "write look write look remove" {
		t.Errorf("the relay was asked %q, where one scratch folder is removed once however many files sit in it", kinds)
	}
}

func TestAWrittenSampleIsAStepWhoseShapeNamesWhatItHolds(t *testing.T) {
	s, _ := storyOver(t, quiet)

	s.Writes(Python("an else after a return", ""), "order.py")
	s.Writes(Python("a swallowed exception", ""), "order.py")

	if shapesOf(s.steps[:1]) == shapesOf(s.steps[1:]) {
		t.Errorf("writing two different samples took the same step %q, so the overlap check could not tell the stories apart", s.steps[0].Text())
	}

	if !strings.HasPrefix(s.steps[0].Text(), "writes an else after a return as story_scratch_") || !strings.HasSuffix(s.steps[0].Text(), "/order.py") {
		t.Errorf("the step reads %q", s.steps[0].Text())
	}
}

func TestAFileIsRemovedByTheSampleItWasWrittenAs(t *testing.T) {
	s, asked := storyOver(t, quiet)

	s.Removes(PHP("a nullable return", ""), "Order.php")

	if sent := (*asked)[0]; sent.Kind != "remove" || !strings.HasSuffix(sent.Path, "/bridge/php/src/story_scratch_testafileisremovedbythesampleitwaswrittenas/Order.php") {
		t.Errorf("the relay was asked %+v, where the file under the PHP bridge's scratch folder is removed", sent)
	}
}

func TestEachBridgeHasItsOwnJudgedFolder(t *testing.T) {
	for _, sample := range []Sample{Python("a", ""), PHP("a", ""), CSharp("a", "")} {
		if sample.Under == "" {
			t.Errorf("%+v names no folder it is judged under", sample)
		}
	}

	if CSharp("a", "").Under != "bridge/roslyn" || PHP("a", "").Under != "bridge/php/src" {
		t.Error("the C# and PHP samples must sit under the bridges the project judges")
	}
}

func TestAnAgentInTheTestCheckoutIsStartedAsAWorkerInTheTestEnvironmentWithTheRelaysProcess(t *testing.T) {
	s, asked := storyOver(t, func(request) Ran { return Ran{Out: "created: agent:41\n"} })

	s.Agent("Ada")

	if first := (*asked)[0]; first.Kind != "pid" || first.Action != "start" {
		t.Errorf("the relay was first asked %+v, where the agent is first given a process of its own", first)
	}

	sent := (*asked)[1]
	if strings.Join(sent.Argv[:2], " ") != "agent start" || sent.Argv[3] != "claude" || sent.Argv[4] != "worker" ||
		!strings.Contains(strings.Join(sent.Argv, " "), "--environment story-engine --pid created: agent:41") {
		t.Errorf("the relay was asked %v, where a main agent is registered in story-engine with the process it was handed", sent.Argv)
	}
}

func TestAWorkerGetsAWorktreeCutAndIsStartedInIt(t *testing.T) {
	var asked *[]request
	var worker *Agent

	t.Run("inner", func(t *testing.T) {
		var s *Story
		s, asked = storyOver(t, func(request) Ran { return Ran{Out: "created: agent:42\n"} })

		worker = s.Worker("Grace Hopper")
	})

	if kinds := strings.Join(kindsOf(asked), " "); kinds != "worktree pid command look command pid worktree" {
		t.Fatalf("the relay was asked %q, where the worktree is cut, a process handed out, the agent started, the journal looks at its worktree, and at the end the agent ended, its process ended and the worktree dropped", kinds)
	}

	if (*asked)[0].Name != "story-engine-grace-hopper" || (*asked)[0].Action != "cut" || (*asked)[6].Action != "drop" {
		t.Errorf("the worktree was asked for as %+v and %+v", (*asked)[0], (*asked)[6])
	}

	if (*asked)[5].Action != "end" || (*asked)[5].Pid != "created: agent:42" {
		t.Errorf("the process was ended with %+v, where the one handed out is the one ended", (*asked)[5])
	}

	if (*asked)[3].In != worker.Folder() {
		t.Errorf("the journal looked at %q, where it takes its first look at the worker's own worktree", (*asked)[3].In)
	}

	if !strings.HasSuffix(worker.Folder(), "/.journal/worktrees/story-engine-grace-hopper") {
		t.Errorf("the worker works in %q, where it works in its own worktree beside the test checkout", worker.Folder())
	}

	if end := (*asked)[4].Argv; strings.Join(end, " ") != "agent end 42" {
		t.Errorf("the agent was ended with %v", end)
	}
}

func hookBody(t *testing.T, sent request) map[string]any {
	t.Helper()

	if sent.Kind != "hook" {
		t.Fatalf("the relay was asked a %s, where a hook was meant", sent.Kind)
	}

	var body map[string]any
	if err := json.Unmarshal([]byte(sent.Stdin), &body); err != nil {
		t.Fatalf("the hook carried %q, which is not JSON", sent.Stdin)
	}

	return body
}

func TestAnEditWritesTheFileThenSendsTheHookAnEditSends(t *testing.T) {
	s, asked := storyOver(t, quiet)
	ada := &Agent{s: s, name: "Ada", session: "session-ada", folder: s.TestCheckout()}

	ada.Edits(Python("an else after a return", "x = 1\n"), "order.py")

	if kinds := strings.Join(kindsOf(asked), " "); kinds != "write hook" {
		t.Fatalf("the relay was asked %q, where the file is written and then the hook sent", kinds)
	}

	body := hookBody(t, (*asked)[1])
	input, _ := body["tool_input"].(map[string]any)

	if body["hook_event_name"] != "PostToolUse" || body["session_id"] != "session-ada" || body["tool_name"] != "Write" ||
		!strings.HasSuffix(input["file_path"].(string), "/order.py") {
		t.Errorf("the hook carried %v, where it is the PostToolUse of a Write of the file, for this agent's session", body)
	}
}

func TestMovingOnSendsAToolHookBeforeAnythingElseHappens(t *testing.T) {
	s, asked := storyOver(t, quiet)
	ada := &Agent{s: s, name: "Ada", session: "session-ada", folder: "/checkout"}

	ada.Moves()

	if body := hookBody(t, (*asked)[0]); body["hook_event_name"] != "PreToolUse" || body["cwd"] != "/checkout" {
		t.Errorf("the hook carried %v, where it is a PreToolUse from the agent's own folder", body)
	}
}

func TestRunningACommandSendsItsTextInTheBashHook(t *testing.T) {
	s, asked := storyOver(t, quiet)
	ada := &Agent{s: s, name: "Ada", session: "session-ada", folder: "/checkout"}

	ada.Runs("git status")

	body := hookBody(t, (*asked)[0])
	input, _ := body["tool_input"].(map[string]any)

	if body["tool_name"] != "Bash" || input["command"] != "git status" {
		t.Errorf("the hook carried %v, where the Bash command's text must arrive", body)
	}
}

func TestStoppingSendsTheStopHook(t *testing.T) {
	s, asked := storyOver(t, quiet)
	ada := &Agent{s: s, name: "Ada", session: "session-ada", folder: "/checkout"}

	ada.Stops()

	if body := hookBody(t, (*asked)[0]); body["hook_event_name"] != "Stop" {
		t.Errorf("the hook carried %v, where the agent's stop is the Stop hook", body)
	}
}

func TestHearingMovesTheAgentOnUntilTheAnswerCarriesTheText(t *testing.T) {
	moments := 0
	s, _ := storyOver(t, func(request) Ran {
		moments++
		if moments < 2 {
			return Ran{}
		}

		return Ran{Out: "src/order.py:8 redundant-python-else"}
	})
	ada := &Agent{s: s, name: "Ada", session: "session-ada", folder: "/checkout"}

	heard := ada.Hears("redundant-python-else")

	if moments != 2 || !strings.Contains(heard.Out, "src/order.py:8") {
		t.Errorf("the agent moved on %d times and heard %q, where the second moment is the one that tells", moments, heard.Out)
	}
}

func TestStayingUntoldPassesWhenNoAnswerCarriesTheText(t *testing.T) {
	s, asked := storyOver(t, func(request) Ran { return Ran{Out: "nothing to say"} })
	ada := &Agent{s: s, name: "Ada", session: "session-ada", folder: "/checkout"}

	ada.StaysUntold("redundant-python-else")

	if len(*asked) == 0 {
		t.Error("the agent never moved on, so nothing was ever told or not told")
	}
}

func TestTheAnswerAssertionsPassOnWhatThePluginSaid(t *testing.T) {
	s, _ := storyOver(t, quiet)
	ran := Ran{Code: 1, Out: "src/order.py, line 8:\n  redundant-python-else\n", Err: "waiting for the tree\n"}

	s.Says(ran, "waiting for the tree", "line 8")
	s.Never(ran, "reads the whole tree itself")
	s.Names(ran, "src/order.py", "redundant-python-else")
	s.Exits(ran, 1)
	s.SaysOnce(ran, "redundant-python-else")
}

func TestACostIsTheDifferenceSinceTheStoryBegan(t *testing.T) {
	s, _ := storyOver(t, quiet)
	costs := ledger.Of(s.project)

	costs.Counts(ledger.TreeReadsStarted, 2, s.project)
	s.began = costsOf(s.project)

	costs.Counts(ledger.TreeReadsStarted, 1, s.project)
	costs.Counts(ledger.FilesReadIntoTrees, 7, s.project)

	s.CostIs(ledger.TreeReadsStarted, 1)
	s.CostAtMost(ledger.FilesReadIntoTrees, 7)
	s.CostAtLeast(ledger.FilesReadIntoTrees, 7)
	s.CostIs(ledger.JudgingsDeclined, 0)
}

func TestDroppingALaneAndReadingTheLanesAskTheCommandmentsVerb(t *testing.T) {
	s, asked := storyOver(t, quiet)

	s.DropsTheLane("/checkout")
	s.ReadsTheLanes()

	if first := strings.Join((*asked)[0].Argv, " "); first != "commandments lanes --drop=/checkout" {
		t.Errorf("dropping a lane asked %q", first)
	}

	if second := strings.Join((*asked)[1].Argv, " "); second != "commandments lanes" {
		t.Errorf("reading the lanes asked %q", second)
	}
}

func TestARanAnswersWithBothItsStreamsInOrder(t *testing.T) {
	if text := (Ran{Out: "out\n", Err: "err\n"}).Text(); text != "out\nerr\n" {
		t.Errorf("the text of a run is %q", text)
	}
}
