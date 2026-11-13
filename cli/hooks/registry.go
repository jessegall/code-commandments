package hooks

import (
	"fmt"
	"path/filepath"
	"runtime"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

const settings = ".claude/settings.json"

const Stamp = "# @code-commandments-managed"

var legacySubcommands = []string{"remind", "judge-reminder", "plan-reminder"}

func Builtins() []Hook {
	return []Hook{JudgeReminder{}, ModelChoiceReminder{}, SessionReset{}, SourceReminder{}, SkillReminder{}}
}

func ForProject(root string) []Hook {
	project, _ := config.Load(root)

	return slices.DeleteFunc(Builtins(), func(hook Hook) bool {
		return project.Disables(config.Rule{Kind: config.Hook, Name: hook.Class()})
	})
}

const namespace = `JesseGall\CodeCommandments\Hooks\Handlers\`

func Named(class string) (Hook, bool) {
	short, qualified := strings.CutPrefix(strings.TrimLeft(class, `\`), namespace)

	for _, hook := range Builtins() {
		if qualified && hook.Class() == short {
			return hook, true
		}
	}

	return nil, false
}

func Wire(root string) (bool, error) {
	path := filepath.Join(root, settings)

	file, read := jsonfile.Read(path)
	if !read {
		if walk.Exists(path) {
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

func Command(root string) string {
	if !binary.ThroughPHP(root) {
		return binary.Invocation(root) + " hooks " + Stamp
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

func fingerprint(file *jsonfile.Object) string {
	return jsonfile.Text(file)
}
