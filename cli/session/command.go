package session

import (
	"github.com/jessegall/code-commandments/cli/binary"
	"math"
	"os"
	"path/filepath"
	"strconv"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/folder"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/layout"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Command is `session`: where this session keeps its state, and the verbs that name, list and adopt the
// folders sessions keep.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"session"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Where this session keeps its state — the folder holding its checklist and hook counters.").
		Form("session", "print the folder, and what is in it").
		Form("session list", "every session folder this project has, newest first — what a SECOND terminal asks, having no session of its own. An ORPHAN, a folder no session and no name points at any more, is marked as one").
		Form("session --path", "print only the path, for piping somewhere").
		Form(`session name "<name>"`, "NAME this session — the folder is renamed to match in every checkout, so it is one you can come back to").
		Form(`session forget "<name>"`, "drop a name; its session answers to its hash again").
		Form("session adopt <folder>", "take a stranded folder INTO this session — everything it holds is moved, and nothing that did not come across is deleted").
		Option("--path", "the bare path and nothing else").
		Option("--into", "the folder to adopt INTO, for a terminal with no session of its own").
		Note("Run it from inside Claude Code by typing `!" + binary.Here() + " session` at the prompt: " +
			"the `!` prefix runs a shell command in the session, so the answer lands in the conversation " +
			"without costing a turn of thinking. From any other terminal, `session list` is the one to use — " +
			"a shell outside the harness has no session of its own to report.").
		Note("`adopt` acts on the CHECKOUT it is run from, because worktree-scoped state belongs to its " +
			"worktree — so a folder `list` shows under a worktree is adopted from inside that worktree.")
}

// Run answers the form the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	project := projectRoot()
	root := workspace.OfSession(project, "").Root()
	verb, _ := in.FirstArgument()
	named, _ := in.Argument(1)

	switch verb {
	case "list":
		return c.list(root, console), nil
	case "name":
		return c.name(root, named, console)
	case "forget":
		return c.forget(root, named, console)
	case "adopt":
		into, _ := in.Option("into")

		return c.adopt(project, named, into, console), nil
	default:
		return c.show(root, in, console), nil
	}
}

func projectRoot() string {
	cwd, err := os.Getwd()
	if err != nil {
		cwd = "."
	}

	return workspace.ProjectRoot(cwd)
}

func (Command) name(root, name string, console cli.Console) (int, error) {
	if name == "" {
		return console.Refuse("Say what to call it: `commandments session name \"<name>\"`."), nil
	}

	id := os.Getenv("CLAUDE_CODE_SESSION_ID")
	if id == "" {
		return console.Refuse("No session to name — a shell outside the harness has none of its own."), nil
	}

	was := foldersOf(root, id)
	given, err := workspace.At(root, "").Names().Name(id, name)

	if err != nil || !given {
		return console.Refuse("`" + name + "` already belongs to another session."), err
	}

	follow(was, id)

	return console.Say("▸ This session is `"+name+"`.", "  "+workspace.At(root, id).SessionDir()), nil
}

func (Command) forget(root, name string, console cli.Console) (int, error) {
	names := workspace.At(root, "").Names()
	id, known := names.IDOf(name)

	if !known {
		return console.Refuse("No session is called `" + name + "`."), nil
	}

	was := foldersOf(root, id)

	if _, err := names.Forget(name); err != nil {
		return 0, err
	}

	follow(was, id)

	return console.Say("▸ `"+name+"` is forgotten.", "  "+workspace.At(root, id).SessionDir()), nil
}

// checkout is a folder a session keeps in one checkout of the project.
type checkout struct {
	root   string
	folder string
}

// checkouts are the project and every other worktree of it.
func checkouts(root string) []string {
	return append([]string{root}, git.Worktrees(root)...)
}

func foldersOf(root, id string) []checkout {
	var folders []checkout

	for _, each := range checkouts(root) {
		folders = append(folders, checkout{each, workspace.At(each, id).SessionDir()})
	}

	return folders
}

// follow moves each checkout's folder to where the session's key now points.
func follow(was []checkout, id string) {
	for _, each := range was {
		to := workspace.At(each.root, id).SessionDir()

		if each.folder == to || !isDir(each.folder) {
			continue
		}

		if isDir(to) {
			Take(each.folder, to)

			continue
		}

		os.Rename(each.folder, to)
	}
}

func (c Command) adopt(project, name, into string, console cli.Console) int {
	if name == "" {
		return console.Refuse("Say which folder to adopt: `commandments session adopt <folder>`.", "  `session list` marks the orphans.")
	}

	space := workspace.At(project, "")
	from := space.SessionDirNamed(name)

	if !isDir(from) {
		return console.Refuse("No folder called `"+name+"` here.", "  `session list` names every folder, and marks the orphans.")
	}

	to, found := destination(space, into)

	switch {
	case !found:
		return console.Refuse(
			"No session to adopt into — a shell outside the harness has none of its own.",
			"  Name the destination: `commandments session adopt "+name+" --into=<folder>`.",
		)
	case from == to:
		return console.Refuse("`" + name + "` is the folder this session already reads.")
	default:
		return report(name, Take(from, to), to, console)
	}
}

func destination(space workspace.Workspace, into string) (string, bool) {
	if into != "" {
		return space.SessionDirNamed(into), true
	}

	id := os.Getenv("CLAUDE_CODE_SESSION_ID")
	if id == "" {
		return "", false
	}

	return workspace.At(space.Root(), id).SessionDir(), true
}

func report(name string, adoption *Adoption, to string, console cli.Console) int {
	entries := strconv.Itoa(len(adoption.Moved())) + " entries"
	if len(adoption.Moved()) == 1 {
		entries = "1 entry"
	}

	lines := []string{"▸ `" + name + "` — " + entries + " adopted into", "  " + to}

	for _, entry := range adoption.Moved() {
		lines = append(lines, "  moved    "+entry)
	}

	if adoption.IsComplete() {
		return console.Say(append(lines, "  `"+name+"` is gone; nothing was left behind.")...)
	}

	for _, entry := range adoption.Kept() {
		lines = append(lines, "  KEPT     "+entry+"  (the destination already has one; nothing was deleted)")
	}

	return console.Refuse(append(lines, "  `"+name+"` still stands. Merge what is left by hand, then adopt again.")...)
}

func (Command) show(root string, in *cli.Input, console cli.Console) int {
	dir := workspace.At(root, "").SessionDir()

	if in.HasFlag("path") {
		return console.Say(dir)
	}

	console.Say(dir, "")

	if !isDir(dir) {
		return console.Say("  (nothing written yet — it appears the first time something records state)")
	}

	for _, file := range folder.NewestFirst(dir) {
		console.Say("  " + layout.PadBytes(filepath.Base(file), 24) + " " + layout.PadBytesLeft(size(file), 6) + "  " + modifiedAt(file, "15:04"))
	}

	return 0
}

func (Command) list(root string, console cli.Console) int {
	named := map[string]Session{}

	for _, session := range Sessions(root) {
		named[workspace.At(root, session.ID).SessionKey()] = session
	}

	names := workspace.At(root, "").Names().All()
	var lines []string

	for _, each := range checkouts(root) {
		folders := folders(each, named, names)

		if len(folders) == 0 {
			continue
		}

		if each != root {
			lines = append(lines, "  in "+each+":")
		}

		lines = append(lines, folders...)
	}

	if len(lines) == 0 {
		return console.Say("No session has recorded anything for this project yet.")
	}

	return console.Say(lines...)
}

func folders(root string, named map[string]Session, names []workspace.Pair) []string {
	var lines []string

	for _, dir := range folder.NewestFirst(workspace.At(root, "").SessionsDir()) {
		if !isDir(dir) {
			continue
		}

		key := filepath.Base(dir)
		session, known := named[key]
		id := ""

		if known {
			id = session.ID[:min(8, len(session.ID))]
		}

		lines = append(lines, "  "+layout.PadBytes(key, 8)+" "+layout.PadBytes(id, 10)+" "+modifiedAt(dir, "2006-01-02 15:04")+"  "+describe(key, session, known, names))
	}

	return lines
}

func describe(key string, session Session, known bool, names []workspace.Pair) string {
	if known && session.Name == "" {
		return "(nothing said yet)"
	}

	if known {
		return session.Name
	}

	if key == workspace.DefaultSession {
		return "(no transcript found)"
	}

	for _, pair := range names {
		if pair.Name == key {
			return "(no transcript found)"
		}
	}

	return "(ORPHAN — nothing points here; `session adopt " + key + "`)"
}

// size is a file's size as PHP printed it: bytes under a kilobyte, else kilobytes to one decimal.
func size(file string) string {
	bytes := sizeOf(file)

	if bytes < 1024 {
		return strconv.FormatInt(bytes, 10) + "B"
	}

	kilobytes := math.Round(float64(bytes)/1024*10) / 10

	return strconv.FormatFloat(kilobytes, 'f', -1, 64) + "K"
}

// sizeOf is a file's length, or a folder's as APFS, where the PHP tool was recorded, reports it: 64 bytes
// and 32 more for each entry, since a folder's own size is otherwise the filesystem's to choose.
func sizeOf(path string) int64 {
	info, err := os.Stat(path)

	switch {
	case err != nil:
		return 0
	case info.IsDir():
		return 64 + 32*int64(len(folder.Entries(path)))
	}

	return info.Size()
}

// modifiedAt is when the path last changed, in UTC as PHP's default zone prints it.
func modifiedAt(path, format string) string {
	return time.Unix(folder.Modified(path), 0).UTC().Format(format)
}
