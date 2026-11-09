package hooks

import (
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/detectors"
)

func sweptMarks(record Announced, root string) (marks []SinMark, read []string) {
	project, err := config.Load(root)
	if err != nil {
		return nil, nil
	}

	files := stillToRead(root, marked(record, root, project))
	if len(files) == 0 {
		return nil, nil
	}

	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		return nil, nil
	}

	enabled = append(enabled, custom.InForce(root).Enabled(project)...)
	project = project.Judging(enabled)
	single := slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile)
	marking := Marks(root)

	codebase, err := scan.Walk(files, source.Excluded{}).Load()
	if err != nil || codebase == nil {
		return nil, nil
	}

	for _, file := range files {
		marks = append(marks, marksOver(codebase, marking, file, project.RulesFor(root, file, single), git.ChangedLines{})...)
	}

	return marks, files
}

func stillToRead(root string, files []string) []string {
	_, base, held := baseFor(root)
	if !held {
		return files
	}

	named, _, _ := baseFor(root)

	var moved []string
	for _, file := range files {
		if stands(named, base, root, file) {
			continue
		}

		moved = append(moved, file)
	}

	return moved
}

func stands(named branchRoot, base *warm.Base, root, file string) bool {
	if checkout := checkoutOf(root, file); checkout != named.project {
		return kept.Contents().Knows(file)
	}

	unchanged, known := base.Holds(file)

	return known && unchanged
}

func marked(record Announced, root string, project config.Config) []string {
	var files []string
	for _, file := range record.Files() {
		path := filepath.Join(root, file)
		if project.Writes(source.OfFile(path)) {
			files = append(files, path)
		}
	}

	return files
}
