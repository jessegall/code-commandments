//go:build story

package edits

import "github.com/jessegall/code-commandments/story"

var (
	elseWithSecondSin = story.Python("an else after a raise beside a mutable default", `def mark(aisle: str, seen: set[str] = set()) -> str:
    if aisle:
        raise ValueError(aisle)
    else:
        seen.add(aisle)
        return aisle
`)

	oldSinNewFunction = story.Python("an else after a raise with a clean function added below it", story.ElseAfterRaise.Text+`

def total(items):
    return sum(items)
`)

	note = story.Sample{Under: "bridge/mypy", Label: "a note in a text file", Text: "refunds are refused past thirty days\n"}

	inVendor = story.Sample{Under: "vendor", Label: "an else after a raise in a skipped folder", Text: story.ElseAfterRaise.Text}

	inScripts = story.Sample{Under: "docker", Label: "an else after a raise outside the judged folders", Text: story.ElseAfterRaise.Text}
)
