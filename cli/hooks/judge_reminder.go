package hooks

import (
	"github.com/jessegall/code-commandments/cli/binary"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/checklist"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// batchSeparator parts the commit a batch was reminded at from what the marker file says about itself.
const batchSeparator = "-----"

// batchExplanation is what the marker file says about itself.
const batchExplanation = "Batch marker for the code-commandments judge reminder (`commandments judge-reminder`, wired as\n" +
	"Stop + PreToolUse hooks). The line above the separator is the commit it last reminded at: a\n" +
	"batch is the work on top of one commit, so it nudges once per batch to run `judge`, silent\n" +
	"until the next commit, and clears itself when the tree is clean. Safe to delete — it\n" +
	"regenerates, at most costing you one extra nudge."

// JudgeReminderAbout is what `judge-reminder` says it is.
const JudgeReminderAbout = "A \"did you judge?\" nudge wired to `Stop` and `PreToolUse` hooks; reminds when judged files are touched but unchecked, deduped per changed-file set."

// JudgeReminder nudges the agent to judge what it changed: once per batch of work on top of a commit,
// before it commits and when it stops, and first about any worklist judge left open.
type JudgeReminder struct{}

func (JudgeReminder) Class() string { return "JudgeReminder" }
func (JudgeReminder) Summary() string {
	return "Nudges you to `judge` what you changed — before a risky Bash command, and on stop."
}
func (JudgeReminder) Bindings() []Binding {
	return append([]Binding{{"Stop", ""}, {"PreToolUse", "Bash"}, {"PostToolUse", "Bash"}}, bound("PostToolUse", writers)...)
}
func (JudgeReminder) SpeaksToSubagents()   {}
func (JudgeReminder) QuietWhileWorkPends() {}

func (JudgeReminder) Handle(event Event) Response {
	switch event.Name() {
	case "PreToolUse":
		if !event.IsGitCommit() {
			AuthoredIn(event.Workspace()).Before(git.Root(event.Root))

			return Silent()
		}

		if reason, due := Reminder(event, "before you commit"); due {
			return Injecting(event.Name(), reason, false)
		}
	case "PostToolUse":
		authored := AuthoredIn(event.Workspace())

		if event.IsTool("Bash") {
			authored.After(git.Root(event.Root))
		} else if event.FilePath() != "" {
			authored.Wrote(event.Root, event.FilePath())
		}

		return Silent()
	case "UserPromptSubmit", "SessionStart", "MessageDisplay", "PreCompact", "PostCompact", "SubagentStop":
		return Silent()
	default:
		if reason, due := Reminder(event, "before you wrap up"); due {
			return Blocking(reason)
		}
	}

	return Silent()
}

// Reminder is what the agent is reminded of, lead first, and whether it is due: the worklist judge left
// open, else the batch of files the session changed itself, once per commit it sits on.
func Reminder(event Event, lead string) (string, bool) {
	root := git.Root(event.Root)
	if root == "" {
		return "", false
	}

	space := workspace.At(root, event.SessionID())

	if open, due := openWorklist(space, lead); due {
		return open, true
	}

	tree := git.Status(root)
	marker := space.Path(".judge-reminded")
	authored := AuthoredIn(event.Workspace())

	if len(tree.Changed) == 0 {
		os.Remove(marker)
		authored.Clear()

		return "", false
	}

	mine := authored.Among(tree.Changed)

	if len(mine) == 0 || storedHead(marker) == tree.Head {
		return "", false
	}

	os.MkdirAll(filepath.Dir(marker), 0o777)
	os.WriteFile(marker, []byte(tree.Head+"\n"+batchSeparator+"\n"+batchExplanation+"\n"), 0o666)

	noun := "files"
	if len(mine) == 1 {
		noun = "file"
	}

	return "Code Commandments — " + lead + ": you've changed " + strconv.Itoa(len(mine)) + " judged " + noun + " since the last commit. " +
		"Consider running `" + binary.Invocation(root) + " judge --changes` to confirm they conform, and fix any " +
		"sin at its SOURCE (don't launder a finding with a default/cast/null-check). This is a one-time " +
		"nudge for this batch — if you've already judged, or these changes aren't worth a scan, just say " +
		"so and carry on.", true
}

// openWorklist is the nudge to finish the checklist judge left, once for each state of it.
func openWorklist(space workspace.Workspace, lead string) (string, bool) {
	list := checklist.InSession(space)
	remaining := list.RemainingSins()
	marker := space.Path(".remind-checklist")

	if remaining == 0 {
		os.Remove(marker)

		return "", false
	}

	fingerprint, known := list.Fingerprint()

	if previous, err := os.ReadFile(marker); known && err == nil && string(previous) == fingerprint {
		return "", false
	}

	os.MkdirAll(filepath.Dir(marker), 0o777)
	os.WriteFile(marker, []byte(fingerprint), 0o666)

	noun := "sins"
	if remaining == 1 {
		noun = "sin"
	}

	return "Code Commandments — " + lead + ": you have an OPEN worklist with " + strconv.Itoa(remaining) + " " + noun + " still in " +
		"`" + space.ChecklistRelative() + "`. Finish it before you stop: work straight down — fix each at its " +
		"SOURCE, delete its line — and do NOT re-run judge, re-scan, or re-verify between fixes. " +
		"Only when the file is EMPTY, run `judge` again (wave by wave; a clean run deletes it). If " +
		"you are intentionally pausing here, just say so and carry on.", true
}

// storedHead is the commit the batch marker last reminded at.
func storedHead(marker string) string {
	text, err := os.ReadFile(marker)
	if err != nil {
		return ""
	}

	head, _, _ := strings.Cut(string(text), batchSeparator)

	return strings.TrimSpace(head)
}
