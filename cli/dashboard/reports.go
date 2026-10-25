package dashboard

import (
	"sort"
	"strconv"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// reportsPage is the dashboard page of what this project has reported.
const reportsPage = "reports"

// Redraw writes the dashboard again from the findings already stored, without judging anything. It is what a
// command that changed the record calls, since a report filed or settled changes the page and nothing else does
// until the next judge.
func Redraw(space workspace.Workspace) error {
	drawn, err := jsonfile.Pretty(Render(storedIn(space), space.Root()), true)
	if err != nil {
		return err
	}

	return atomic.Write(space.Cache(File), drawn)
}

// reports is the page listing every report this project has filed: the rule, where the finding stood, the issue,
// and what the last look at that issue found. The local path is shown because the dashboard is local; the record
// itself keeps no line of the project's code.
func reports(rows []reported.Row) *jsonfile.Object {
	if len(rows) == 0 {
		return jsonfile.NewObject("type", "stack", "gap", 16, "children", []any{
			jsonfile.NewObject("type", "text", "body", "No reports filed. A finding this project reports as wrong is listed here with its issue until the answer settles it."),
		})
	}

	waiting := 0
	for _, row := range rows {
		if isWaiting(row) {
			waiting++
		}
	}

	tiles := jsonfile.NewObject("type", "row", "gap", 12, "children", []any{
		jsonfile.NewObject("type", "stat", "label", "Reported", "value", strconv.Itoa(len(rows)), "tone", "note", "note", "findings judged as reported, not as sins"),
		jsonfile.NewObject("type", "stat", "label", "Waiting", "value", strconv.Itoa(waiting), "tone", tone(waiting), "note", "no answer from the maintainer yet"),
	})

	cells := []any{}
	for _, row := range rows {
		cells = append(cells, jsonfile.NewObject(
			"cells", []string{row.Detector, row.File + ":" + strconv.Itoa(row.Line), "#" + strconv.Itoa(row.Issue), state(row), answered(row)},
			"tone", rowTone(row),
		))
	}

	return jsonfile.NewObject("type", "stack", "gap", 16, "children", []any{
		tiles,
		jsonfile.NewObject("type", "table", "columns", []string{"Rule", "Where", "Issue", "State", "Answer"}, "rows", cells),
		jsonfile.NewObject("type", "fact", "label", "What a reported finding means",
			"body", "A judge reads these lines as reported rather than as sins to fix, and leaves them out of its count, "+
				"so a run is clean while the claim is being answered. They are still shown, because a finding that "+
				"disappeared because somebody disagreed with it would be a rule switched off by the back door."),
	})
}

// waitingRows are the reports in the order a reader wants them: the ones still waiting for an answer first, then
// the rest, newest first within each.
func waitingRows(record reported.Record) []reported.Row {
	rows := record.Rows()
	sort.SliceStable(rows, func(one, other int) bool {
		return isWaiting(rows[one]) && !isWaiting(rows[other])
	})

	return rows
}

// isWaiting says whether the report is still waiting on the maintainer: nothing has been looked at yet, or the
// last look found it open with nothing said. A row the project has accepted waits on nobody.
func isWaiting(row reported.Row) bool {
	return row.Accepted == "" && row.Seen.State != "CLOSED" && row.Seen.Comments == 0
}

// state is the issue's state as the last look found it, or what the project has decided about it.
func state(row reported.Row) string {
	switch {
	case row.Accepted != "":
		return "fixed upstream, not applied"
	case row.Seen.State == "":
		return "not looked at yet"
	default:
		return row.Seen.State
	}
}

// answered says what the last look found of the maintainer's answer, or why the project accepted the answer it
// cannot act on.
func answered(row reported.Row) string {
	if row.Accepted != "" {
		return row.Accepted
	}

	switch {
	case row.Seen.At == "":
		return "—"
	case row.Seen.Comments > 0:
		return strconv.Itoa(row.Seen.Comments) + " comment(s)"
	default:
		return "nothing said"
	}
}

// rowTone colours a row by whether it still waits on an answer.
func rowTone(row reported.Row) string {
	if isWaiting(row) {
		return "warn"
	}

	return "note"
}

// tone colours a count: nothing waiting is good, anything waiting is worth a look.
func tone(waiting int) string {
	if waiting == 0 {
		return "good"
	}

	return "warn"
}
