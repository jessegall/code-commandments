package hooks

import (
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
)

// swept is the files that still carry a chat mark, judged again, and every sin they hold now: what the settlement
// reads as the whole truth about those files when the agent goes idle.
//
// An edit settles the mark of the file it changed, and of no other. A sin taken out by a shell command the plugin
// saw no file of, by `repent`, by a checkout, or while the agent was editing something else leaves a mark nothing
// would ever settle — and the file it names may never be written again, so the mark stays orange for good. Going
// idle is the moment to look: the files are the few the record names, the rules are the ones that judge a file on
// its own, and the answer is worked out off the hook's own path, so nothing waits on it.
func sweptMarks(record Announced, root string) (marks []SinMark, read []string) {
	project, err := config.Load(root)
	if err != nil {
		return nil, nil
	}

	files := marked(record, root, project)
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

	// No lines are the moment's own: a sweep says what each file holds, and marks nothing as just committed, so
	// going idle never raises a sin as new — only settles one that is gone. A file inside a folder the project
	// excludes is read by the rules let in there and no others, as the check that marked it read it.
	// Every marked file in ONE ask. A bridge's cost is per ask and not per file, so reading them one at a time
	// paid a whole ask for each: measured here at 2.5 seconds for nine files, at every stop and every heartbeat
	// of every open agent, and the edits waiting behind it were answered eleven seconds late.
	codebase, err := scan.Walk(files, source.Excluded{}).Load()
	if err != nil || codebase == nil {
		return nil, nil
	}

	for _, file := range files {
		marks = append(marks, marksOver(codebase, marking, file, project.RulesFor(root, file, single), git.ChangedLines{})...)
	}

	return marks, files
}

// stillToRead are the marked files worth reading again: the ones the held tree does not know to be unchanged. A
// mark's sin can only be GONE if its file has changed, and every edit is judged as it lands, so a file the tree
// knows stands as it read it cannot have lost the sin it carries. Reading them all cost a bridge ask at every
// stop and every heartbeat of every open agent, and in a measured run of nine edits it settled nothing at all:
// 28 marks over 7 files, re-read, no change.
//
// Where no tree is held nothing is known, and every marked file is read as it always was.
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

// stands says whether the tree held for that file knows it stands as it was read: the project's base for a file
// of the project's own checkout, and the worker's own overlay for one of a worktree, since a base never holds a
// worktree's files and would call every one of them unknown.
func stands(named branchRoot, base *warm.Base, root, file string) bool {
	if checkout := checkoutOf(root, file); checkout != named.project {
		unchanged, known := kept.Over(checkout).Holds(file)

		return known && unchanged
	}

	unchanged, known := base.Holds(file)

	return known && unchanged
}

// marked are the files the record holds sins for, as this project judges them. A file the project no longer judges
// is left alone: nothing of ours will look at it again, so nothing of ours should claim it is clean.
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
