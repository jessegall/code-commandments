package hooks

import (
	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func loadCounted(project string, sources scan.Sources) (*engine.Codebase, error) {
	codebase, err := sources.Load()
	if codebase != nil {
		ledger.Of(project).Counts(ledger.FilesParsedByBridges, len(codebase.Files()), project)
	}

	return codebase, err
}

func treesOf(project, checkout string, files []string) ([]*contract.Stream, int, error) {
	contents := kept.Contents()

	streams, unknown, digests := contents.Find(files, checkout)
	ledger.Of(project).Counts(ledger.FilesKnownByContents, len(files)-len(unknown), checkout)

	if len(unknown) == 0 {
		return streams, 0, nil
	}

	rebuilt, err := loadCounted(project, scan.Walk(unknown, source.Excluded{}))
	if rebuilt == nil {
		return nil, 0, err
	}

	contents.Keeps(rebuilt.Streams(), digests, checkout)

	return append(streams, rebuilt.Streams()...), len(unknown), err
}
