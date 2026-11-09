package hooks

import (
	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/engine"
)

func loadCounted(project string, sources scan.Sources) (*engine.Codebase, error) {
	codebase, err := sources.Load()
	if codebase != nil {
		ledger.Of(project).Counts(ledger.FilesParsedByBridges, len(codebase.Files()), project)
	}

	return codebase, err
}
