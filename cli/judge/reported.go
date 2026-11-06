package judge

import (
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/engine"
)

// Reported is a finding this project has already reported to the tool's own repository, with the report that covers it.
type Reported struct {
	Finding engine.Finding
	Row     reported.Row
}

func apart(root string, findings []engine.Finding) (active []engine.Finding, filed []Reported, read bool) {
	record, read := reported.Of(root)
	if len(record.Reports) == 0 {
		return findings, nil, read
	}

	for _, finding := range findings {
		_, line := splitLocation(finding.Location)
		row, isReported := record.Reported(finding.Detector, root, finding.File, line)

		if !isReported {
			active = append(active, finding)

			continue
		}

		filed = append(filed, Reported{Finding: finding, Row: row})
	}

	return active, filed, read
}
