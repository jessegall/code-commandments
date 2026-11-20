package shop

import (
	"encoding/json"
	"path/filepath"

	"commandments.fixture/shop/typeports"
)

type NightAudit struct {
	source   *typeports.TillGraphService
	finished []string
}

func NewNightAudit(
	// @sin ConcreteCrossModuleDependency
	audit *typeports.TillGraphService,
	receipts <-chan string,
	expected int,
) NightAudit {
	closed := make([]string, 0, expected)
	for ticket := range receipts {
		if ticket != "" {
			closed = append(closed, ticket)
		}
	}
	return NightAudit{source: audit, finished: closed}
}

func (audit NightAudit) Balance() string {
	return audit.source.Read()
}

func (audit NightAudit) SendArchive(queue chan<- string, drafts []int) {
	if len(audit.finished) == 0 {
		return
	}
	for _, draft := range drafts {
		if draft < len(audit.finished) && draft >= 0 {
			// @sin RepeatedFolderPath
			queue <- filepath.Join("warehouse", "saved-labels", "audit.pdf")
		}
	}
}

func (audit NightAudit) Review(drafts []Return, notify func(Return)) int {
	reviewed := 0
	for _, draft := range drafts {
		// @sin UnnamedCondition
		if draft.Cents > 0 && draft.Reason != "" && len(audit.finished) >= 2 {
			notify(draft)
		}
		reviewed++
	}
	return reviewed
}

func readAuditNotes(payload []byte, skipped func()) (any, error) {
	var note any
	decodeError := json.Unmarshal(payload, &note)
	switch {
	case decodeError != nil:
		return nil, decodeError
	case len(payload) == 0:
		skipped()
		return nil, nil
	}
	// @sin RawDecodedArrayReturn
	return note, nil
}
