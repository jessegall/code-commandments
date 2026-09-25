package report

import (
	"os/exec"
	"strings"

	"github.com/jessegall/code-commandments/cli"
)

// repository is where issues about the tool are filed.
const repository = "jessegall/code-commandments"

// File files an issue through the GitHub CLI and prints what it answered: 0 when it names the new issue,
// 1 when it does not, and 2 when there is no gh to ask.
func File(title, body string, console cli.Console) int {
	gh, err := exec.LookPath("gh")
	if err != nil {
		console.Warn("GitHub CLI (`gh`) is required to file the issue automatically.",
			"Install it and run `gh auth login`, or open one directly at:",
			"  https://github.com/"+repository+"/issues/new")

		return 2
	}

	output, _ := exec.Command(gh, "issue", "create", "--repo", repository, "--title", title, "--body", body).CombinedOutput()

	if len(output) == 0 {
		console.Write("Filed.\n")
	}

	console.Write(string(output))

	if strings.Contains(string(output), "github.com") {
		return 0
	}

	return 1
}
