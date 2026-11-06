package dashboard

import (
	"sort"
	"strconv"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const reportsPage = "reports"

func Redraw(space workspace.Workspace) error {
	drawn, err := jsonfile.Pretty(Render(storedIn(space), space.Root()), true)
	if err != nil {
		return err
	}

	return atomic.Write(space.Cache(File), drawn)
}

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

func waitingRows(record reported.Record) []reported.Row {
	rows := record.Rows()
	sort.SliceStable(rows, func(one, other int) bool {
		return isWaiting(rows[one]) && !isWaiting(rows[other])
	})

	return rows
}

func isWaiting(row reported.Row) bool {
	return row.Accepted == "" && row.Seen.State != "CLOSED" && row.Seen.Comments == 0
}

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

func rowTone(row reported.Row) string {
	if isWaiting(row) {
		return "warn"
	}

	return "note"
}

func tone(waiting int) string {
	if waiting == 0 {
		return "good"
	}

	return "warn"
}
