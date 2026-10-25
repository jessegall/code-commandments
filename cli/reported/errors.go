package reported

import "strconv"

// Unreadable is the refusal to record a report about a place whose own line cannot be read: a file that is gone, a
// line past its end, a line holding nothing but space. Every such finding of one rule in one file would key alike,
// so a row keyed on it would mark code nobody reported.
type Unreadable struct {
	File string
	Line int
}

// Error says what could not be read.
func (u *Unreadable) Error() string {
	return "the line " + u.File + ":" + strconv.Itoa(u.Line) + " could not be read, so the report has no sin to be remembered by"
}

// Already is the refusal to record a second report about a finding a row already holds, naming the issue that has
// it.
// Replacing the row would forget an issue that may still be open and waiting for an answer.
type Already struct {
	Row Row
}

// Error names the issue that already holds this sin.
func (a *Already) Error() string {
	return a.Row.Detector + " at " + a.Row.File + ":" + strconv.Itoa(a.Row.Line) + " is already reported as issue " + strconv.Itoa(a.Row.Issue)
}
