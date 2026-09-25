package task

import (
	"os"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// stale is how many minutes untouched makes an active task stale, unless --for says otherwise.
const stale = 60

// Command is `task`: the numbered work in front of this session.
type Command struct {
	// Now is the clock tasks are stamped with.
	Now func() time.Time
}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"task", "tasks"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("The work in front of this session — numbered tasks, one markdown file each, moved between queue, active and history.").
		Form("task", "the board — every task still owed, in address order, subtasks beneath their parent").
		Form(`task add "<title>" ["<why>"]`, "queue one, and print the NUMBER it was given").
		Form(`task add --under=<id> "<title>" ["<why>"]`, "queue a SUBTASK of <id> — it carries <id>'s number (`002.1`), it does not move into a folder of its own").
		Option("--under=ID", "with `add`: the task the new one is a subtask of").
		Form("task start <id>", "begin it — the file moves from `queue/` into `active/`").
		Form(`task done <id> "<what came of it>"`, "close it — the file moves into `history/`, and the reason goes in its log").
		Form("task show <id>", "read one out, whole — what you paste into a worker's brief").
		Form("task history", "what has been closed, and what came of each").
		Form("task stale [--for=N]", "active tasks nobody has touched for N minutes (default "+strconv.Itoa(stale)+")").
		Option("--for=N", "with `stale`: how many minutes untouched counts as stale (default "+strconv.Itoa(stale)+")").
		Note("A task is ADDRESSED by its number, not by where it sits. That is the whole design: `002.1` " +
			"means the same thing in a listing, in a filename and in a brief handed to a worker, where a " +
			"folder path stops meaning anything the moment the work moves on. A subtask carries its " +
			"parent's number and nothing else — there is no nesting to walk, and no cursor to be standing in.").
		Note("The three folders under `.commandments/sessions/<id>/tasks/` ARE the state: moving the file " +
			"is the change, so `git status` shows it, nothing has to be kept in step with a field, and a " +
			"task nobody closed is a file still sitting in `active/`. `history/` keeps every task this " +
			"session ever had — which is also why a number is never handed out twice.")
}

// Run answers the form the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	cwd, err := os.Getwd()
	if err != nil {
		cwd = "."
	}

	tasks := InSession(workspace.OfSession(workspace.ProjectRoot(cwd), ""))
	verb, _ := in.FirstArgument()
	id, _ := in.Argument(1)

	switch verb {
	case "add":
		return c.add(tasks, in, console), nil
	case "start":
		return c.start(tasks, id, console), nil
	case "done":
		why, _ := in.Argument(2)

		return c.done(tasks, id, why, console), nil
	case "show":
		return c.show(tasks, id, console), nil
	case "history":
		return history(tasks, console), nil
	case "stale":
		return c.stale(tasks, in, console), nil
	case "":
		return board(tasks, console), nil
	default:
		return help.Usage(console.Err, c, "No `task "+verb+"`."), nil
	}
}

func (c Command) now() time.Time {
	if c.Now == nil {
		return time.Now()
	}

	return c.Now()
}

func (c Command) add(tasks Tasks, in *cli.Input, console cli.Console) int {
	title, _ := in.Argument(1)
	if title == "" {
		return help.Usage(console.Err, c, "Say what the task is: `commandments task add \"<title>\" \"<why>\"`.")
	}

	under, found := parentIn(tasks, in)
	if !found {
		named, _ := in.Option("under")

		return noSuchTask(named, console)
	}

	why, _ := in.Argument(2)
	added, written := tasks.Add(under, title, why, c.now())

	if !written {
		return console.Refuse("The task could not be written — check that " + tasks.Root + " is writable.")
	}

	return console.Say("▸ " + added.ID.Render() + " queued — " + title)
}

func (c Command) start(tasks Tasks, id string, console cli.Console) int {
	task, found := named(tasks, id)
	if !found {
		return noSuchTask(id, console)
	}

	if task.State == Active {
		return console.Refuse("`" + task.ID.Render() + "` is already active.")
	}

	return c.moved(tasks, task, Active, "", "▸ "+task.ID.Render()+" started — "+task.Title, console)
}

func (c Command) done(tasks Tasks, id, why string, console cli.Console) int {
	task, found := named(tasks, id)
	if !found {
		return noSuchTask(id, console)
	}

	if why == "" {
		return help.Usage(console.Err, c, "Say what came of it: `commandments task done "+id+" \"<what came of it>\"` — it is what history keeps.")
	}

	if task.State == Done {
		return console.Refuse("`"+task.ID.Render()+"` is already closed.", "  `commandments task show "+task.ID.Render()+"` reads its log.")
	}

	return c.moved(tasks, task, Done, why, "✓ "+task.ID.Render()+" done — "+task.Title, console)
}

func (c Command) show(tasks Tasks, id string, console cli.Console) int {
	task, found := named(tasks, id)
	if !found {
		return noSuchTask(id, console)
	}

	return console.Say(strings.TrimRight(task.Body(), "\n"))
}

func board(tasks Tasks, console cli.Console) int {
	live := tasks.InState(Live...)

	if len(live) == 0 {
		return console.Say("Nothing queued. `commandments task add \"<title>\"` starts one.")
	}

	lines := make([]string, len(live))

	for i, task := range live {
		lines[i] = task.Line()
	}

	return console.Say(lines...)
}

func history(tasks Tasks, console cli.Console) int {
	closed := tasks.InState(Done)

	if len(closed) == 0 {
		return console.Say("Nothing closed yet.")
	}

	var lines []string

	for _, task := range closed {
		outcome, found := task.Outcome()
		if !found {
			outcome = "closed without a reason"
		}

		lines = append(lines, task.State.Mark()+" "+task.ID.Render()+"  "+task.Title, "    "+outcome)
	}

	return console.Say(lines...)
}

func (c Command) stale(tasks Tasks, in *cli.Input, console cli.Console) int {
	minutes := stale

	if given, set := in.Option("for"); set {
		minutes = leadingInt(given)
	}

	now := c.now().Unix()
	cutoff := now - int64(minutes*60)
	var untouched []string

	for _, task := range tasks.InState(Active) {
		if task.Touched() < cutoff {
			untouched = append(untouched, "  "+task.ID.Render()+"  "+task.Title+" — "+strconv.FormatInt((now-task.Touched())/60, 10)+"m")
		}
	}

	if len(untouched) == 0 {
		return console.Say("Nothing untouched for " + strconv.Itoa(minutes) + "m.")
	}

	return console.Say(append([]string{"Untouched for " + strconv.Itoa(minutes) + "m or more:"}, untouched...)...)
}

func (c Command) moved(tasks Tasks, task Task, to State, why, said string, console cli.Console) int {
	if _, moved := tasks.Move(task, to, why, c.now()); !moved {
		return console.Refuse("`" + task.ID.Render() + "` could not be moved into `" + string(to) + "/`.")
	}

	return console.Say(said)
}

// parentIn is the task --under names, or the board when it names none.
func parentIn(tasks Tasks, in *cli.Input) (ID, bool) {
	under, given := in.Option("under")
	if !given {
		return ID{}, true
	}

	task, found := named(tasks, under)

	return task.ID, found
}

func named(tasks Tasks, text string) (Task, bool) {
	id, parsed := ParseID(text)
	if !parsed {
		return Task{}, false
	}

	return tasks.Find(id)
}

func noSuchTask(id string, console cli.Console) int {
	said := "No task `" + id + "`."
	if id == "" {
		said = "Name the task by its number."
	}

	return console.Refuse(said, "  `commandments task` lists what is open, `commandments task history` what is closed.")
}

// leadingInt reads a number as PHP's (int) cast does: the leading digits with a sign, else 0.
func leadingInt(text string) int {
	text = strings.TrimLeft(text, " \t\n\r\v\f")
	end := 0

	if end < len(text) && (text[end] == '-' || text[end] == '+') {
		end++
	}

	for end < len(text) && text[end] >= '0' && text[end] <= '9' {
		end++
	}

	number, _ := strconv.Atoi(text[:end])

	return number
}
