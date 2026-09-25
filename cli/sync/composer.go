package sync

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/jsonfile"
)

// composerEvents are the composer scripts that call sync, so a fresh install or an update resyncs.
var composerEvents = []string{"post-update-cmd", "post-install-cmd"}

// EnsureComposerHook makes each composer event end with the call to sync, dropping any older call of ours;
// changed is false when it already did, and has is false when the project has no composer.json to wire.
func EnsureComposerHook(root string) (changed, has bool, err error) {
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

// isOurs says whether a composer script is a call to sync the tool wrote.
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
