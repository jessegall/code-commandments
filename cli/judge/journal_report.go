package judge

import (
	"encoding/json"
	"strconv"
	"strings"
)

// journalFinding is one finding as a journal check's report lists it: the sin, where it is, and the skill it
// is grouped under.
type journalFinding struct {
	Name  string `json:"name"`
	File  string `json:"file"`
	Line  int    `json:"line"`
	Where string `json:"where"`
	Text  string `json:"text"`
	Group string `json:"group"`
}

// journalReport is the report a journal check reads from the file it names in JOURNAL_REPORT.
type journalReport struct {
	Title    string           `json:"title"`
	Summary  string           `json:"summary"`
	Findings []journalFinding `json:"findings"`
}

// Journal is the run's findings as the journal's check report: a summary, and each finding with its file, its
// line and the skill that fixes it as its group.
func (r Report) Journal() []byte {
	findings := []journalFinding{}
	for _, slug := range r.skills {
		for _, finding := range r.bySkill[slug] {
			file, line := splitLocation(finding.Location)
			findings = append(findings, journalFinding{Name: finding.Sin, File: file, Line: line, Where: finding.Scope, Text: finding.Rule(), Group: slug})
		}
	}
	summary := strconv.Itoa(r.total) + " " + plural(r.total, "sin", "sins") + " across " + strconv.Itoa(len(r.skills)) + " " + plural(len(r.skills), "skill", "skills")
	written, _ := json.Marshal(journalReport{Title: "Sins", Summary: summary, Findings: findings})

	return written
}

// splitLocation is a finding's file and line, from its path:line; a location with no line is the file alone.
func splitLocation(location string) (string, int) {
	at := strings.LastIndex(location, ":")
	if at < 0 {
		return location, 0
	}
	line, err := strconv.Atoi(location[at+1:])
	if err != nil {
		return location, 0
	}

	return location[:at], line
}
