package scan

import (
	"os"

	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// IsFrozen says whether the file on disk is frozen, as the engine answers it: over the file's own tree where
// its language declares a freeze in syntax, over its lines otherwise. A file whose text names no freeze is
// not parsed.
func IsFrozen(path string) bool {
	raw, err := os.ReadFile(path)
	if err != nil || !engine.MayBeFrozen(path, raw) {
		return false
	}

	stream := &contract.Stream{Files: []*contract.File{{Path: path}}}

	if engine.FreezeReadsATree(path) {
		stream, err = streamOf(source.OfFile(path), path)
		if err != nil {
			return false
		}
	}

	files := engine.Load(stream).Files()

	return len(files) == 1 && files[0].IsFrozen()
}

// streamOf is the files read through their language's bridge.
func streamOf(language source.Language, files ...string) (*contract.Stream, error) {
	for _, read := range readers {
		for _, reads := range read.languages {
			if reads == language {
				return read.stream(files)
			}
		}
	}

	return &contract.Stream{}, nil
}
