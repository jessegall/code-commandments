package report

import (
	"os/exec"
	"regexp"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
)

const repository = "jessegall/code-commandments"

// Filed is the issue a report became: the repository it was filed to and its number.
type Filed struct {
	Repository string
	Issue      int
}

func (f Filed) Exists() bool {
	return f.Repository != "" && f.Issue > 0
}

func (f Filed) URL() string {
	if !f.Exists() {
		return ""
	}

	return "https://github.com/" + f.Repository + "/issues/" + strconv.Itoa(f.Issue)
}

var filedIssue = regexp.MustCompile(`https://github\.com/([^/\s]+/[^/\s]+)/issues/(\d+)`)

var looking = func() (string, error) { return exec.LookPath("gh") }

var filing = func(gh string, args ...string) (stdout string, stderr string, err error) {
	command := exec.Command(gh, args...)
	out, said := &strings.Builder{}, &strings.Builder{}
	command.Stdout, command.Stderr = out, said
	err = command.Run()

	return out.String(), said.String(), err
}

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
