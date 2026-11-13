package rules

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

func (c Command) migrate(in *cli.Input, root string, console cli.Console) (int, error) {
	dir := workspace.CustomDir(root)
	if !walk.Exists(dir) {
		return console.Say("No rules of your own: " + shortPath(root, dir) + " holds none, so there is nothing to migrate."), nil
	}

	preview := in.HasFlag("dry-run")
	files := custom.RuleFiles(dir)

	merged, failures := mergeByEngine(root, files, preview)
	for _, said := range merged {
		console.Say(said)
	}

	project := custom.Load(root)
	for _, unreadable := range project.Unreadable {
		console.Warn("✗ " + unreadable)
	}

	failures = append(failures, project.Unreadable...)

	switch {
	case len(failures) == 0 && len(merged) == 0:
		return console.Say("✓ Every rule of your own reads as it is: " + plural(len(project.Rules), "rule", "rules") + ", nothing to change."), nil
	case len(failures) == 0:
		return console.Say("✓ Every rule reads, and what could be merged was."), nil
	}

	console.Warn("✗ " + plural(len(failures), "rule", "rules") + " could not be settled here, each named above. A rule naming a construct no parser writes is a rule that found nothing before this release said so: fix the word, or name the engine that writes it.")

	return cli.Refused, nil
}

func mergeByEngine(root string, files []string, preview bool) (said []string, failures []string) {
	same := map[string][]string{}
	var order []string

	for _, path := range files {
		key, named := apartFromEngine(path)
		if !named {
			continue
		}
		if _, seen := same[key]; !seen {
			order = append(order, key)
		}
		same[key] = append(same[key], path)
	}

	for _, key := range order {
		group := same[key]
		if len(group) < 2 {
			continue
		}

		slices.SortStableFunc(group, func(one, other string) int {
			return len(filepath.Base(one)) - len(filepath.Base(other))
		})

		into, engines, err := merge(group, preview)
		if err != nil {
			failures = append(failures, err.Error())

			continue
		}

		was := make([]string, 0, len(group)-1)
		for _, path := range group[1:] {
			was = append(was, filepath.Base(path))
		}

		leading := "↻ "
		if preview {
			leading = "· would merge: "
		}

		said = append(said, leading+filepath.Base(into)+" now judges "+strings.Join(engines, " and ")+", replacing "+strings.Join(was, " and "))

		said = append(said, renamedInConfig(root, group, into, preview)...)
	}

	return said, failures
}

func renamedInConfig(root string, group []string, into string, preview bool) []string {
	if preview {
		return nil
	}

	var said []string
	surviving := nameOf(into)

	for _, path := range group[1:] {
		replaced := nameOf(path)
		renamed, clash, err := config.RenameRule(workspace.JSONConfig(root), replaced, surviving)

		switch {
		case err != nil:
			said = append(said, "  ✗ "+replaced+" could not be renamed in config.json: "+err.Error())
		case clash != "":
			said = append(said, "  ✗ "+clash)
		case renamed:
			said = append(said, "  ↳ config.json now names "+surviving+" where it named "+replaced)
		}
	}

	return said
}

func nameOf(path string) string {
	return strings.TrimSuffix(filepath.Base(path), ".json")
}

func apartFromEngine(path string) (string, bool) {
	text, err := os.ReadFile(path)
	if err != nil {
		return "", false
	}

	var read map[string]any
	if json.Unmarshal(text, &read) != nil {
		return "", false
	}

	if _, names := read["engine"]; !names {
		return "", false
	}

	delete(read, "engine")
	delete(read, "$schema")
	if sin, held := read["sin"].(map[string]any); held {
		delete(sin, "name")
	}

	apart, err := json.Marshal(read)
	if err != nil {
		return "", false
	}

	return string(apart), true
}

func merge(group []string, preview bool) (into string, engines []string, err error) {
	into = group[0]
	for _, path := range group {
		named, err := enginesOf(path)
		if err != nil {
			return "", nil, err
		}
		for _, engine := range named {
			if !slices.Contains(engines, engine) {
				engines = append(engines, engine)
			}
		}
	}

	if preview {
		return into, engines, nil
	}

	text, err := os.ReadFile(into)
	if err != nil {
		return "", nil, err
	}

	var read map[string]any
	if err := json.Unmarshal(text, &read); err != nil {
		return "", nil, fmt.Errorf("%s: %w", filepath.Base(into), err)
	}

	read["engine"] = engines
	written, err := json.MarshalIndent(read, "", "    ")
	if err != nil {
		return "", nil, err
	}

	if err := atomic.Write(into, string(append(written, '\n'))); err != nil {
		return "", nil, err
	}

	for _, path := range group[1:] {
		if err := os.Remove(path); err != nil {
			return "", nil, err
		}
	}

	return into, engines, nil
}

func enginesOf(path string) ([]string, error) {
	text, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var read struct {
		Engine json.RawMessage `json:"engine"`
	}
	if err := json.Unmarshal(text, &read); err != nil {
		return nil, fmt.Errorf("%s: %w", filepath.Base(path), err)
	}

	var one string
	if json.Unmarshal(read.Engine, &one) == nil {
		return []string{one}, nil
	}

	var several []string
	if err := json.Unmarshal(read.Engine, &several); err != nil {
		return nil, fmt.Errorf("%s: engine is one engine's name or a list of them", filepath.Base(path))
	}

	return several, nil
}

func shortPath(root, path string) string {
	if relative, err := filepath.Rel(root, path); err == nil {
		return relative
	}

	return path
}

func plural(count int, one, many string) string {
	if count == 1 {
		return "1 " + one
	}

	return fmt.Sprintf("%d %s", count, many)
}
