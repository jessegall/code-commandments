package rules

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// migrate brings a project's own rules up to what this release reads, and says what it could not.
//
// There are two jobs in it and they are not the same. The first is to READ every rule with the parser of this
// release and report each that no longer parses, because a release may refuse what an older one ran: a rule
// naming a construct no parser writes used to run and quietly find nothing, which reads as a rule that found no
// sins. Such a rule cannot be repaired here, since the nearest real word is a guess and a guess written into a
// project's own file is worse than a message, so it is named with its reason and left alone.
//
// The second is the one that actually rewrites: a rule the neutral kinds let judge several engines no longer has
// to be written once per engine, so copies that differ in nothing but their engine are MERGED into one file
// naming them all. That is mechanical, because sameness is decided by the files themselves rather than by
// reading them for intent.
//
// A failure never stops the next rule. Every rule is read, every merge that can be made is made, and the run
// ends by naming what is left.
func (c Command) migrate(in *cli.Input, root string, console cli.Console) (int, error) {
	dir := workspace.CustomDir(root)
	if _, err := os.Stat(dir); err != nil {
		return console.Say("No rules of your own: " + shortPath(root, dir) + " holds none, so there is nothing to migrate."), nil
	}

	preview := in.HasFlag("dry-run")
	files, err := ruleFiles(dir)
	if err != nil {
		return 0, err
	}

	merged, failures := mergeByEngine(files, preview)
	for _, said := range merged {
		console.Say(said)
	}

	// Read what is there AFTER merging, so a rule the merge wrote is read as this release reads it.
	project := custom.Load(root)
	for _, unreadable := range project.Unreadable {
		console.Warn("✗ " + unreadable)
	}

	failures = append(failures, project.Unreadable...)

	switch {
	case len(failures) == 0 && len(merged) == 0:
		return console.Say("✓ Every rule of your own reads as it is: " + plural(len(project.Rules), "rule", "rules") + ", nothing to change."), nil
	case len(failures) == 0:
		return console.Say("✓ " + plural(len(merged), "rule", "rules") + " merged, and every rule reads."), nil
	}

	console.Warn("✗ " + plural(len(failures), "rule", "rules") + " could not be settled here, each named above. A rule naming a construct no parser writes is a rule that found nothing before this release said so: fix the word, or name the engine that writes it.")

	return cli.Refused, nil
}

// ruleFiles are the project's own rule files, by path, in a settled order.
func ruleFiles(dir string) ([]string, error) {
	var found []string
	err := filepath.WalkDir(dir, func(path string, entry os.DirEntry, err error) error {
		if err != nil || entry.IsDir() || filepath.Ext(path) != ".json" {
			return nil
		}
		if filepath.Base(path) == "lists.json" || strings.Contains(path, string(filepath.Separator)+custom.SkillsFolder+string(filepath.Separator)) {
			return nil
		}

		found = append(found, path)

		return nil
	})
	sort.Strings(found)

	return found, err
}

// mergeByEngine merges the rule files that differ in nothing but the engine they judge into one file naming them
// all, and says what it did. A file it cannot read as JSON is no merge candidate and is left for the parser to
// report, which says more about it than this could.
func mergeByEngine(files []string, preview bool) (said []string, failures []string) {
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
	}

	return said, failures
}

// apartFromEngine is the rule's own text with its engine left out, as one string: two rules with the same one are
// the same rule written twice. The schema line goes with the engine, since an editor's pointer says nothing about
// what a rule finds.
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
	// A sin's own name differs per copy as often as not, since two files cannot both be called one thing; what
	// the rule FINDS is what decides sameness.
	if sin, held := read["sin"].(map[string]any); held {
		delete(sin, "name")
	}

	apart, err := json.Marshal(read)
	if err != nil {
		return "", false
	}

	return string(apart), true
}

// merge writes the first file of the group naming every engine the group judges, and removes the rest. The first
// is kept because a name is a rule's identity everywhere a config mentions it, and keeping the first by path
// keeps the choice the same from one run to the next.
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

	if err := os.WriteFile(into, append(written, '\n'), 0o644); err != nil {
		return "", nil, err
	}

	for _, path := range group[1:] {
		if err := os.Remove(path); err != nil {
			return "", nil, err
		}
	}

	return into, engines, nil
}

// enginesOf are the engines a rule file names, whether it names one or a list.
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

// shortPath is the path as the project names it, relative where it lies inside.
func shortPath(root, path string) string {
	if relative, err := filepath.Rel(root, path); err == nil {
		return relative
	}

	return path
}

// plural is a count with the word that fits it.
func plural(count int, one, many string) string {
	if count == 1 {
		return "1 " + one
	}

	return fmt.Sprintf("%d %s", count, many)
}
