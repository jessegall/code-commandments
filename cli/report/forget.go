package report

import (
	"strconv"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// The verbs `report` answers to besides filing one.
const (
	followVerb = "follow"
	forgetVerb = "forget"
	acceptVerb = "accept"
)

// forget drops the report of an issue, so the finding it covered is judged as any other again. It is what settles
// a report: a fix that has landed, or an answer that the finding stands and the sin is this project's to fix.
func (c Command) forget(in *cli.Input, console cli.Console) int {
	named, given := in.Argument(1)
	issue, err := strconv.Atoi(named)

	if !given || err != nil || issue <= 0 {
		return help.Usage(console.Err, c, "name the issue to forget: `"+binary.Here()+" report forget 814`")
	}

	root := workspace.ProjectRoot(workingFolder())
	row, dropped, err := reported.Forget(root, issue)

	if err != nil {
		console.Warn("The report could not be dropped: " + err.Error())

		return 1
	}

	if !dropped {
		console.Warn("No report of issue " + named + " is recorded here. `" + binary.Here() + " report follow` lists the ones that are.")

		return 1
	}

	if space := workspace.At(root, ""); space.IsJournalDriven() {
		dashboard.Redraw(space)
	}

	console.Write("Forgot the report of " + row.Detector + " at " + row.File + ":" + strconv.Itoa(row.Line) +
		" (issue " + named + "). A judge reads that finding as a sin again.\n")

	return 0
}

// accept keeps a report whose answer the project cannot act on: the fix is in a release it is pinned away from, so
// nothing asks about the issue again and its finding stays reported. The sin is real and it is fixed upstream;
// what is missing is the upgrade, which is the project's own decision to make.
func (c Command) accept(in *cli.Input, console cli.Console) int {
	named, given := in.Argument(1)
	issue, err := strconv.Atoi(named)
	why, reasoned := in.Option("why")

	if !given || err != nil || issue <= 0 || !reasoned {
		return help.Usage(console.Err, c, "name the issue and why its answer cannot be acted on: `"+binary.Here()+
			" report accept 814 --why=\"pinned to v6.9 until the next quarter\"`")
	}

	root := workspace.ProjectRoot(workingFolder())
	row, accepted, err := reported.Accept(root, issue, why)

	if err != nil {
		console.Warn("The report could not be accepted: " + err.Error())

		return 1
	}

	if !accepted {
		console.Warn("No report of issue " + named + " is recorded here. `" + binary.Here() + " report follow` lists the ones that are.")

		return 1
	}

	if space := workspace.At(root, ""); space.IsJournalDriven() {
		dashboard.Redraw(space)
	}

	console.Write("Accepted the answer to issue " + named + " without applying it: " + why + ". " + row.Detector +
		" at " + row.File + ":" + strconv.Itoa(row.Line) + " stays reported, and nothing asks about the issue again.\n")

	return 0
}
