// Package sync refreshes a project's code-commandments integration: the skills published into the library
// every agent reads, the AGENTS.md briefing, the config, and each agent's own view of them.
package sync

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/agents"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/library"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// BriefingBlock names the block the canon is kept in, in AGENTS.md.
const BriefingBlock = "code-commandments briefing"

// retiredCommands are commands the tool once published into an agent's folder, removed by exact name.
var retiredCommands = []string{"until.md", "stop-condition.md"}

// Command is `sync`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"sync"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Refresh this project's code-commandments integration — publish the skills into the library every agent reads, refresh the AGENTS.md briefing and the config surface, and wire each agent's own view of them.").
		Form("sync", "run it (idempotent; composer runs it for you on every install/update once `install` has wired it)")
}

// Run syncs the project the working folder is in.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	cwd, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	root := ConsumerRoot(cwd)
	if root == "" {
		return help.Usage(console.Err, c, "no composer.json at or above "+cwd+" — sync publishes into a project, and would otherwise write into whatever directory you happen to be standing in."), nil
	}

	release := hold(workspace.At(root, "").Shared(".sync.lock"))
	defer release()

	return 0, Sync(root, console)
}

// Sync runs every step over the project at root, in the order that keeps a run failing halfway harmless:
// the ignore rules before anything is generated, and the canon before anything points at it.
func Sync(root string, console cli.Console) error {
	project, err := config.Load(root)
	if err != nil {
		return err
	}

	kept := agents.ForProject(project)

	if err := ensureGitignored(filepath.Join(root, ".gitignore"), kept); err != nil {
		return err
	}

	lib := library.At(root, project)

	published, err := lib.Publish()
	if err != nil {
		return err
	}

	canon := agents.InstructionsAt(filepath.Join(root, "AGENTS.md"), root)
	warn(console, canon.Inject(BriefingBlock, library.Briefing(project)))

	for _, agent := range kept {
		wire(root, agent, lib, published, canon, console)
	}

	if changed, _, err := EnsureComposerHook(root); err != nil {
		return err
	} else if changed {
		console.Say("↻ composer post-install/post-update now call `commandments sync`.")
	}

	if err := ensureConfig(root, console); err != nil {
		return err
	}

	if err := ensureCommandmentsGitignore(root); err != nil {
		return err
	}

	removeLegacyArtifacts(root)

	if converted := Migrate(workspace.At(root, "")); len(converted) > 0 {
		console.Say("↻ session state upgraded — carried over " + strings.Join(converted, ", ") + ".")
	}

	var names []string
	for _, agent := range kept {
		names = append(names, agent.Name())
	}

	console.Say("↻ code-commandments synced — " + strconv.Itoa(len(published)) + " skills published to " + library.Dir + ", read by " + strings.Join(names, ", ") + ".")

	return nil
}

// wire points the agent at what was published: a link per skill in its own folder, its commands, its own
// instructions file, and whatever else it wires.
func wire(root string, agent agents.Agent, lib library.Library, published []string, canon agents.Instructions, console cli.Console) {
	if dir := agent.SkillsDir(); dir != "" {
		os.RemoveAll(filepath.Join(root, dir, "commandments"))

		for _, id := range published {
			agents.Point(filepath.Join(root, dir, id), lib.Path(id))
		}

		lib.Reconcile(filepath.Join(root, dir), published)
	}

	if dir := agent.CommandsDir(); dir != "" {
		for _, retired := range retiredCommands {
			os.Remove(filepath.Join(root, dir, retired))
		}
	}

	if file := agent.InstructionsFile(); file != "" {
		instructions := agents.InstructionsAt(filepath.Join(root, file), root)

		if !instructions.SameFileAs(canon) {
			warn(console, instructions.Inject(agent.BlockName(), agent.Instructions()))
		}
	}

	_, err := agent.Wire(root)
	if err != nil {
		console.Warn(err.Error())
	}
}

// ensureConfig brings the project's config to config.json: a config.php is migrated once, a project with
// neither starts on one, and the schema beside it is this version's.
func ensureConfig(root string, console cli.Console) error {
	migrated, err := config.Migrate(root)
	if err != nil {
		console.Warn("⚠ .commandments/config.php stays as it is: " + err.Error())
	}

	if migrated {
		console.Say("↻ .commandments/config.php is now .commandments/config.json — the original is kept as " + config.Backup + ".")
	}

	if _, err := config.EditorIn(root).Scaffold(config.DetectRoots(root)); err != nil {
		return err
	}

	_, err = config.WriteSchema(root)

	return err
}

func warn(console cli.Console, err error) {
	if err != nil {
		console.Warn("⚠ " + err.Error())
	}
}

// ConsumerRoot is the project sync writes into: the nearest folder at or above cwd holding a composer.json,
// short of the home folder, else the git repository cwd is in; empty when there is neither.
func ConsumerRoot(cwd string) string {
	home, _ := os.UserHomeDir()

	if real, err := filepath.EvalSymlinks(cwd); err == nil {
		cwd = real
	}

	for dir := cwd; dir != filepath.Dir(dir) && dir != home; dir = filepath.Dir(dir) {
		if info, err := os.Stat(filepath.Join(dir, "composer.json")); err == nil && info.Mode().IsRegular() {
			return dir
		}
	}

	return git.Root(cwd)
}
