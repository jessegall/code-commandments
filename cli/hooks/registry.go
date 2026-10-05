package hooks

import (
	"fmt"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// settings is Claude Code's project settings file, which holds the wired hooks.
const settings = ".claude/settings.json"

// Stamp marks every hook command the tool writes, so a sync strips only its own and never a user's.
const Stamp = "# @code-commandments-managed"

// legacySubcommands are the verbs older versions wired, taken for ours when a command ends with one.
var legacySubcommands = []string{"remind", "judge-reminder", "plan-reminder"}

// Builtins are the hooks every project runs unless its config turns one off, in the order they answer.
func Builtins() []Hook {
	return []Hook{JudgeReminder{}, SharedBranchGate{}, ModelChoiceReminder{}, SessionReset{}, SourceReminder{}, SkillReminder{}}
}

// ForProject are the hooks the project at root keeps.
func ForProject(root string) []Hook {
	project, _ := config.Load(root)

	return slices.DeleteFunc(Builtins(), func(hook Hook) bool {
		return project.Disables(config.Rule{Kind: config.Hook, Name: hook.Class()})
	})
}

// namespace is where the tool's own hooks are declared, as `hook <Class>` names one.
const namespace = `JesseGall\CodeCommandments\Hooks\Handlers\`

// Named is the builtin hook the fully qualified class names, and whether there is one.
func Named(class string) (Hook, bool) {
	short, qualified := strings.CutPrefix(strings.TrimLeft(class, `\`), namespace)

	for _, hook := range Builtins() {
		if qualified && hook.Class() == short {
			return hook, true
		}
	}

	return nil, false
}

// Wire writes the project's hooks into .claude/settings.json: every hook of ours dropped, then one command
// per event the kept hooks listen for, narrowed to a tool when every hook on that event names the same one.
// A project whose hooks the agent journal runs gets none, since its plugin calls every handler itself.
// False when nothing changed, or the file is not JSON it can read.
func Wire(root string) (bool, error) {
	path := filepath.Join(root, settings)

	file, read := jsonfile.Read(path)
	if !read {
		if _, err := os.Stat(path); err == nil {
			return false, fmt.Errorf("⚠ %s is not readable JSON — hooks left unwired rather than overwrite it.", path)
		}

		file = jsonfile.NewObject()
	}

	before := fingerprint(file)
	hooks := stripOurs(file)

	if !workspace.At(root, "").IsJournalDriven() {
		for _, moment := range moments(ForProject(root)) {
			group := jsonfile.NewObject()

			if moment.matcher != "" {
				group.Set("matcher", moment.matcher)
			}

			command := jsonfile.NewObject()
			command.Set("type", "command")
			command.Set("command", Command(root))
			group.Set("hooks", []any{command})

			groups, _ := hooks.Get(moment.event)
			list, _ := groups.([]any)
			hooks.Set(moment.event, append(list, group))
		}
	}

	file.Set("hooks", hooks)

	if fingerprint(file) == before {
		return false, nil
	}

	return true, jsonfile.Write(path, file)
}

// Command is the command every wired hook runs: the project's executable, dispatching the moment. Where the
// project installs the tool with composer, the launcher runs the binary the shim last ran, without PHP's start-up;
// on Windows, which may have no sh, the shim runs it. Elsewhere it is the binary on the PATH.
func Command(root string) string {
	if !binary.ThroughPHP(root) {
		return binary.Name + " hooks " + Stamp
	}
	if runtime.GOOS == "windows" {
		return `php "$CLAUDE_PROJECT_DIR/` + binary.In(root) + `" hooks ` + Stamp
	}

	return `sh "$CLAUDE_PROJECT_DIR/` + binary.Launcher(root) + `" hooks ` + Stamp
}

// moment is one event the suite is wired on, narrowed to a tool or not.
type moment struct {
	event, matcher string
}

// moments are the events the hooks listen for, in the order they first name them; an event is narrowed to
// a tool only when every hook on it names that same one.
func moments(kept []Hook) []moment {
	var events []string
	matchers := map[string][]string{}

	for _, hook := range kept {
		for _, binding := range hook.Bindings() {
			if _, seen := matchers[binding.Event]; !seen {
				events = append(events, binding.Event)
			}

			if !slices.Contains(matchers[binding.Event], binding.Matcher) {
				matchers[binding.Event] = append(matchers[binding.Event], binding.Matcher)
			}
		}
	}

	var wired []moment

	for _, event := range events {
		unique := matchers[event]

		if len(unique) == 1 && unique[0] != "" {
			wired = append(wired, moment{event, unique[0]})
		} else {
			wired = append(wired, moment{event, ""})
		}
	}

	return wired
}

// stripOurs is the file's hooks with every command of ours taken out, and a group or event left empty by
// that taken out with it.
func stripOurs(file *jsonfile.Object) *jsonfile.Object {
	existing, _ := file.Get("hooks")

	hooks, isObject := existing.(*jsonfile.Object)
	if !isObject {
		return jsonfile.NewObject()
	}

	for _, event := range hooks.Keys() {
		value, _ := hooks.Get(event)
		groups, _ := value.([]any)

		var rebuilt []any

		for _, group := range groups {
			fields, isGroup := group.(*jsonfile.Object)
			listed, _ := fieldOf(fields, "hooks").([]any)

			if !isGroup || listed == nil {
				rebuilt = append(rebuilt, group)

				continue
			}

			kept := slices.DeleteFunc(slices.Clone(listed), func(hook any) bool {
				command, _ := fieldOf(asObject(hook), "command").(string)

				return isOurs(command)
			})

			if len(kept) > 0 {
				fields.Set("hooks", kept)
				rebuilt = append(rebuilt, fields)
			}
		}

		if len(rebuilt) == 0 {
			hooks.Delete(event)
		} else {
			hooks.Set(event, rebuilt)
		}
	}

	return hooks
}

func isOurs(command string) bool {
	if strings.Contains(command, Stamp) {
		return true
	}

	if !strings.Contains(command, "commandments") {
		return false
	}

	command = strings.TrimRight(command, " \t\n\r\x00\x0B")

	for _, subcommand := range legacySubcommands {
		if strings.HasSuffix(command, subcommand) {
			return true
		}
	}

	return false
}

func asObject(value any) *jsonfile.Object {
	object, _ := value.(*jsonfile.Object)

	return object
}

func fieldOf(object *jsonfile.Object, key string) any {
	if object == nil {
		return nil
	}

	value, _ := object.Get(key)

	return value
}

// fingerprint is the file as it would be written, keys in order, to tell whether wiring changed it.
func fingerprint(file *jsonfile.Object) string {
	return jsonfile.Text(file)
}
