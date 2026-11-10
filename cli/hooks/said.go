package hooks

import (
	"maps"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

var saidLegend = &state.Legend{
	About: "Code-commandments — which shortfall each file's reader was last told about, so the same words are not said on every edit.",
	List:  "one file per line: the file, a tab, then the reason its reader was last told about",
	Safe:  "the next edit of each file says once more what was not asked about it",
}

// Said is the record of which shortfall each file's reader has already been told about.
type Said struct {
	file state.File
}

func SaidIn(space workspace.Workspace) Said {
	return Said{state.At(space.Path(".said-shortfalls"), saidLegend)}
}

func (s Said) Fresh(narrowed map[string]Narrowed) map[string]Narrowed {
	if len(narrowed) == 0 {
		return nil
	}

	read := s.file.Read()
	before := map[string]string{}
	for _, item := range read.Items() {
		file, why, split := strings.Cut(item, "\t")
		if split {
			before[file] = why
		}
	}

	fresh := map[string]Narrowed{}
	for file, why := range narrowed {
		if why == "" {
			delete(before, file)

			continue
		}

		if before[file] != string(why) {
			fresh[file] = why
		}

		before[file] = string(why)
	}

	var items []string
	for _, file := range slices.Sorted(maps.Keys(before)) {
		items = append(items, file+"\t"+before[file])
	}

	if err := s.file.Write(read.WithItems(items)); err != nil {
		return narrowed
	}

	return fresh
}
