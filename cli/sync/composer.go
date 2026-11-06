package sync

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/jsonfile"
)

var composerEvents = []string{"post-update-cmd", "post-install-cmd"}

func EnsureComposerHook(root string) (changed, wires bool, err error) {
	if !binary.ThroughComposer(root) {
		return false, false, nil
	}

	path := root + "/composer.json"

	composer, read := jsonfile.Read(path)
	if !read {
		return false, false, nil
	}

	call := "@php " + binary.In(root) + " sync"
	scripts := jsonfile.NewObject()

	if existing, has := composer.Get("scripts"); has {
		if object, isObject := existing.(*jsonfile.Object); isObject {
			scripts = object
		}
	}

	for _, event := range composerEvents {
		value, _ := scripts.Get(event)
		hooks := asList(value)
		kept := slices.DeleteFunc(slices.Clone(hooks), isOurs)

		if wanted := append(kept, call); !slices.Equal(wanted, hooks) {
			scripts.Set(event, wanted)
			changed = true
		}
	}

	if !changed {
		return false, true, nil
	}

	composer.Set("scripts", scripts)

	return true, true, jsonfile.Write(path, composer)
}

func isOurs(hook string) bool {
	return strings.Contains(hook, "commandments") && strings.HasSuffix(strings.TrimRight(hook, " \t\n\r\x00\x0B"), " sync")
}

func asList(value any) []string {
	switch typed := value.(type) {
	case []any:
		var hooks []string

		for _, item := range typed {
			if hook, isText := item.(string); isText {
				hooks = append(hooks, hook)
			}
		}

		return hooks
	case string:
		return []string{typed}
	default:
		return nil
	}
}
