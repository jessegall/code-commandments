package judge

import (
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/engine"
)

// Reported is a finding this project has already reported to the tool's own repository, with the report that
// covers it.
type Reported struct {
	Finding engine.Finding
	Row     reported.Row
}

// apart splits the findings into the ones the project still answers for and the ones it has already reported.
//
// A reported finding is still shown and still written down, because a finding that disappeared because somebody
// disagreed with it would be a rule switched off by the back door. It decides nothing, though: not the exit, not
// the count, not the journal's own report. The claim is filed, a maintainer is answering it, and `report follow`
// is what brings that answer back. A gate that stays red for something already filed is a gate nobody reads.
//
// read is false where the record is there but no longer reads as a record, which the run says out loud: every
// finding is then active, which is the safe way round, and the reason is worth a reader's attention.
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
