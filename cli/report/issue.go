package report

import (
	"os/exec"
	"regexp"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
)

// repository is where issues about the tool are filed.
const repository = "jessegall/code-commandments"

// Filed is the issue a report became: the repository it was filed to and its number. A report that filed nothing
// has neither, which Exists says.
type Filed struct {
	Repository string
	Issue      int
}

// Exists says whether an issue was really filed.
func (f Filed) Exists() bool {
	return f.Repository != "" && f.Issue > 0
}

// URL is the issue as a reader opens it.
func (f Filed) URL() string {
	if !f.Exists() {
		return ""
	}

	return "https://github.com/" + f.Repository + "/issues/" + strconv.Itoa(f.Issue)
}

// filedIssue is the URL the GitHub CLI prints when it has created an issue, which is the only thing that says one
// exists. Its error text can name the repository too, so the number is read from the URL and nothing else.
var filedIssue = regexp.MustCompile(`https://github\.com/([^/\s]+/[^/\s]+)/issues/(\d+)`)

// looking finds the GitHub CLI, and filing runs it; a test puts its own in the place of either.
var looking = func() (string, error) { return exec.LookPath("gh") }

// filing runs the GitHub CLI; a test puts its own in its place.
var filing = func(gh string, args ...string) (stdout string, stderr string, err error) {
	command := exec.Command(gh, args...)
	out, said := &strings.Builder{}, &strings.Builder{}
	command.Stdout, command.Stderr = out, said
	err = command.Run()

	return out.String(), said.String(), err
}

// File files an issue through the GitHub CLI and prints what it answered: the issue it filed and 0 when gh created
// one, 1 when it did not, and 2 when there is no gh to ask.
//
// An issue exists only where gh exited cleanly AND printed the new issue's URL on its own output. Its error text
// names the repository too, and a warning of its own can stand above the URL, so neither the mention of GitHub nor
// the exit code alone is read as success.
func File(title, body string, console cli.Console) (Filed, int) {
	gh, err := looking()
	if err != nil {
		console.Warn("GitHub CLI (`gh`) is required to file the issue automatically.",
			"Install it and run `gh auth login`, or open one directly at:",
			"  https://github.com/"+repository+"/issues/new")

		return Filed{}, 2
	}

	out, said, err := filing(gh, "issue", "create", "--repo", repository, "--title", title, "--body", body)
	console.Write(out + said)

	if err != nil {
		return Filed{}, 1
	}

	named := filedIssue.FindStringSubmatch(out)
	if named == nil {
		return Filed{}, 1
	}

	number, _ := strconv.Atoi(named[2])

	return Filed{Repository: named[1], Issue: number}, 0
}
