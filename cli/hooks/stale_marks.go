package hooks

import (
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
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
func swept(record Announced, root string) (marks []SinMark, read []string) {
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

	enabled = append(enabled, custom.Load(root).Enabled(project)...)
	single := slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile)

	// No lines are the moment's own: a sweep says what each file holds, and marks nothing as just committed, so
	// going idle never raises a sin as new — only settles one that is gone.
	for _, file := range files {
		marks = append(marks, marksIn(file, single, git.ChangedLines{})...)
	}

	return marks, files
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
