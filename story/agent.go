package story

import (
	"encoding/json"
	"fmt"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"time"
)

const (
	testEnvironment = "story-engine"
	workerPrefix    = "story-engine-"
	hearsWithin     = 10 * time.Second
	reopenedWithin  = 30 * time.Second
	stillnessWithin = 12 * time.Second
	betweenMoments  = 2 * time.Second
	aModelTurn      = 3 * time.Second
)

var agentRow = regexp.MustCompile(`agent:(\d+)`)

var taskRow = regexp.MustCompile(`task:(\d+)`)

var proofRow = regexp.MustCompile(`proof:(\d+)`)

// Agent is a session the journal knows in the test environment, with no program behind it: the story sends the hooks Claude Code would send for it and makes the file changes it would make.
type Agent struct {
	s       *Story
	name    string
	session string
	folder  string
	row     string
	pid     string
	role    string
	edited  bool
	told    string
	task    string
	proof   string
	merged  bool
}

func (s *Story) Agent(name string) *Agent {
	s.t.Helper()

	agent := s.registers(name, "worker", s.TestCheckout())
	if !s.rehearsal {
		agent.settlesIn()
	}

	return agent
}

func (s *Story) Worker(name string) *Agent {
	s.t.Helper()

	worktree := workerPrefix + notALetter.ReplaceAllString(strings.ToLower(name), "-")
	folder := filepath.Join(filepath.Dir(s.TestCheckout()), worktree)

	s.steps = append(s.steps, StepOf("cuts a worktree for a worker named %s", s.own(name)))

	if ran, err := s.journal.Cuts(worktree); err != nil || ran.Code != 0 {
		s.Fails("the worktree " + worktree + " could not be cut: " + failure(ran, err))
	}

	s.t.Cleanup(func() { _, _ = s.journal.Drops(worktree) })

	worker := s.registers(name, "worker", folder)
	if !s.rehearsal {
		worker.settlesIn()
	}

	return worker
}

func (s *Story) DispatchedWorker(name string, by *Agent) *Agent {
	s.t.Helper()

	if s.rehearsal {
		return s.Worker(name)
	}

	s.steps = append(s.steps, StepOf("dispatches a worker with no program named %s", s.own(name)))

	found := taskRow.FindStringSubmatch(by.Asks("task", "make", "story work of "+name, "--brief", "the work of a story's worker").Text())
	if found == nil {
		s.Fails("the worker " + name + " was given no task to work on")

		return nil
	}

	session := fmt.Sprintf("story-%s-%d", notALetter.ReplaceAllString(strings.ToLower(name), "-"), time.Now().UnixNano())

	dispatched := s.AsksIn(s.TestCheckout(), "worker", "dispatch", found[1], "--no-program", "--program", "claude", "--session", session,
		"--environment", testEnvironment, "--fresh", "a story's worker", "--json")

	var launch struct{ ID int }

	if err := json.Unmarshal([]byte(dispatched.Out), &launch); err != nil || launch.ID == 0 {
		s.Fails("the worker " + name + " could not be dispatched: " + dispatched.Text())

		return nil
	}

	row := strconv.Itoa(launch.ID)
	folder := filepath.Join(s.TestCheckout(), ".journal", "worktrees", testEnvironment, "worker-"+row)

	s.keepsTheBranchWhereItIs()

	worker := &Agent{s: s, name: name, session: session, folder: folder, row: row, role: "worker", task: found[1]}
	worker.settlesIn()

	return worker
}

func (s *Story) keepsTheBranchWhereItIs() {
	s.t.Helper()

	if s.keepsBranch {
		return
	}

	s.keepsBranch = true

	tip := s.branchTip()

	s.putsTheBranchBackOn(tip)
	s.t.Cleanup(func() { s.putsTheBranchBackOn(tip) })
}

func (s *Story) putsTheBranchBackOn(tip string) {
	s.t.Helper()

	if reset, err := s.journal.ResetsTheBranch(tip); err != nil || reset.Code != 0 {
		s.t.Errorf("the story branch could not be put back: %s", failure(reset, err))
	}
}

func (s *Story) branchTip() string {
	s.t.Helper()

	tip, err := s.journal.Moves("tip")
	if err != nil || tip.Code != 0 {
		s.Fails("the story branch could not be read: " + failure(tip, err))
	}

	return strings.TrimSpace(tip.Out)
}

func (s *Story) registers(name, role, folder string) *Agent {
	s.t.Helper()

	session := fmt.Sprintf("story-%s-%d", notALetter.ReplaceAllString(strings.ToLower(name), "-"), time.Now().UnixNano())

	s.steps = append(s.steps, StepOf("starts a "+role+" agent named %s", s.own(name)))

	process, err := s.journal.StartsProcess()
	if err != nil || process.Code != 0 {
		s.Fails("the " + role + " " + name + " could not be given a process of its own: " + failure(process, err))
	}

	pid := strings.TrimSpace(process.Out)

	s.t.Cleanup(func() { _, _ = s.journal.EndsProcess(pid) })

	started, err := s.journal.Runs(folder, "agent", "start", session, "claude", role, "--environment", testEnvironment, "--pid", pid)
	if err != nil || started.Code != 0 {
		s.Fails("the " + role + " " + name + " could not be started: " + failure(started, err))
	}

	row := ""
	if found := agentRow.FindStringSubmatch(started.Out); found != nil {
		row = found[1]

		s.t.Cleanup(func() { _, _ = s.journal.Runs(folder, "agent", "end", found[1]) })
	}

	return &Agent{s: s, name: name, session: session, folder: folder, row: row, pid: pid, role: role}
}

func (a *Agent) Folder() string {
	return a.folder
}

func (a *Agent) Edits(sample Sample, file string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent edits "+shapeOf(sample.Label)+" as %s", a.s.own(file)))

	return a.writes(sample, file, func(path string) string { return path })
}

func (a *Agent) EditsByRelativePath(sample Sample, file string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent edits "+shapeOf(sample.Label)+" as %s, naming it relative to its folder", a.s.own(file)))

	return a.writes(sample, file, func(path string) string { return strings.TrimPrefix(path, a.folder+"/") })
}

func (a *Agent) EditsByWindowsPath(sample Sample, file string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent edits "+shapeOf(sample.Label)+" as %s, naming it with backslashes", a.s.own(file)))

	return a.writes(sample, file, func(path string) string { return strings.ReplaceAll(path, "/", `\`) })
}

func (a *Agent) EditsTwice(sample Sample, file string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent edits "+shapeOf(sample.Label)+" as %s, and the same edit arrives twice", a.s.own(file)))

	a.writes(sample, file, func(path string) string { return path })

	return a.writes(sample, file, func(path string) string { return path })
}

func (a *Agent) SendsTheEvent(event string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent sends the event %s", event))

	return a.sends(event, map[string]any{})
}

func (a *Agent) SendsAnEventNobodyKnows() Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent sends an event nobody has a handler for"))

	return a.sends("SomethingElse", map[string]any{})
}

func (a *Agent) SendsAnEditNamingNoFile() Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent sends an edit that names no file"))

	return a.sends("PostToolUse", map[string]any{"tool_name": "Write", "tool_input": map[string]any{}})
}

func (a *Agent) EditsMany(sample Sample, many int) {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent edits many files like "+shapeOf(sample.Label)+", %s of them", fmt.Sprint(many)))

	a.s.writesManyIn(a.folder, sample, many)
}

func (a *Agent) Deletes(sample Sample, file string) {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent deletes "+shapeOf(sample.Label)+" as %s", a.s.own(file)))

	if ran, err := a.s.journal.Removes(filepath.Join(a.s.ScratchIn(a.folder, sample.Under), file)); err != nil || ran.Code != 0 {
		a.s.Fails("the file could not be deleted: " + failure(ran, err))
	}
}

func (a *Agent) Rebases() {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent rebases its branch onto the test checkout's"))

	if ran, err := a.s.journal.Rebases(filepath.Base(a.folder)); err != nil || ran.Code != 0 {
		a.s.Fails("the worktree could not be rebased: " + failure(ran, err))
	}
}

func (a *Agent) HandsIn() Ran {
	a.s.t.Helper()

	if found := proofRow.FindStringSubmatch(a.Asks("proof", "make", "1", "--task", a.task, "a story's hand-in").Text()); found != nil {
		a.proof = found[1]
	}

	handedIn := a.Asks("worker", "deliver", "--differences", "none")
	a.s.Exits(handedIn, 0)

	return handedIn
}

func (a *Agent) Ends() {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent is ended"))

	if ran, err := a.s.journal.Runs(a.folder, "agent", "end", a.row); err != nil || ran.Code != 0 {
		a.s.Fails("the agent could not be ended: " + failure(ran, err))
	}

	_, _ = a.s.journal.EndsProcess(a.pid)
}

func (a *Agent) Leaves() {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("a worker leaves and its worktree is removed"))

	_, _ = a.s.journal.Runs(a.folder, "agent", "end", a.row)
	_, _ = a.s.journal.EndsProcess(a.pid)

	if ran, err := a.s.journal.Drops(filepath.Base(a.folder)); err != nil || ran.Code != 0 {
		a.s.Fails("the worktree could not be removed: " + failure(ran, err))
	}
}

func (a *Agent) writes(sample Sample, file string, named func(string) string) Ran {
	a.s.t.Helper()

	path := a.s.writesQuietly(a.folder, sample, file)
	a.edited = true

	return a.sends("PostToolUse", map[string]any{
		"tool_name":     "Write",
		"tool_input":    map[string]any{"file_path": named(path), "content": sample.Text},
		"tool_response": map[string]any{"type": "create", "filePath": named(path), "content": sample.Text,
			"structuredPatch": []any{}, "originalFile": nil},
	})
}

func (a *Agent) settlesIn() {
	a.s.t.Helper()

	if looked, err := a.s.journal.LooksAt(a.folder); err != nil || looked.Code != 0 {
		a.s.Fails("the journal could not take its first look at " + a.folder + ": " + failure(looked, err))
	}
}

func (a *Agent) Moves() Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent moves on"))

	return a.moves()
}

func (a *Agent) moves() Ran {
	if a.merged {
		return a.linesHeldForIt()
	}

	return a.sends("PreToolUse", map[string]any{
		"tool_name":  "Read",
		"tool_input": map[string]any{"file_path": filepath.Join(a.folder, "README.md")},
	})
}

func (a *Agent) linesHeldForIt() Ran {
	a.s.t.Helper()

	held, err := a.s.journal.LinesHeldFor(a.row)
	if err != nil {
		a.s.Fails(err.Error())
	}

	return held
}

func (a *Agent) Asks(argv ...string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, journalStep(argv))

	ran, err := a.s.journal.RunsAs(a.folder, a.session, a.row, a.role, argv...)
	if err != nil {
		a.s.Fails(err.Error())
	}

	return ran
}

func (a *Agent) Runs(command string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent runs a shell command %s", command))

	return a.sends("PreToolUse", map[string]any{
		"tool_name":  "Bash",
		"tool_input": map[string]any{"command": command},
	})
}

func (a *Agent) Stops() Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent stops"))
	a.takesAModelTurn()

	return a.sends("Stop", map[string]any{})
}

func (a *Agent) takesAModelTurn() {
	if a.edited && !a.s.rehearsal {
		time.Sleep(aModelTurn)
	}

	a.edited = false
}

func (a *Agent) Hears(text string) Ran {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent moves on until it is told something"))

	if a.s.rehearsal {
		return Ran{}
	}

	if strings.Contains(a.told, text) {
		return Ran{Out: a.told}
	}

	var last Ran

	for until := time.Now().Add(a.hearingWindow()); time.Now().Before(until); time.Sleep(betweenMoments) {
		last = a.moves()
		if strings.Contains(last.Text(), text) {
			return last
		}
	}

	a.s.Fails(fmt.Sprintf("%s was never told %q; the last moment answered %q", a.name, text, last.Text()))

	return last
}

func (a *Agent) hearingWindow() time.Duration {
	if a.merged {
		return reopenedWithin
	}

	return hearsWithin
}

func (a *Agent) StaysUntold(text string) {
	a.s.t.Helper()

	a.s.steps = append(a.s.steps, StepOf("an agent moves on while nothing is told"))

	if a.s.rehearsal {
		return
	}

	for until := time.Now().Add(stillnessWithin); time.Now().Before(until); time.Sleep(betweenMoments) {
		if answer := a.moves(); strings.Contains(answer.Text(), text) {
			a.s.Fails(fmt.Sprintf("%s was told %q, which must not be told: %q", a.name, text, answer.Text()))
		}
	}
}

func (a *Agent) sends(event string, fields map[string]any) Ran {
	a.s.t.Helper()

	body := map[string]any{"session_id": a.session, "hook_event_name": event, "cwd": a.folder}
	for name, value := range fields {
		body[name] = value
	}

	text, err := json.Marshal(body)
	if err != nil {
		a.s.Fails(err.Error())
	}

	ran, err := a.s.journal.Hooks(a.folder, string(text), a.row, a.role)
	if err != nil {
		a.s.Fails(err.Error())
	}

	a.told += ran.Text()

	return ran
}
