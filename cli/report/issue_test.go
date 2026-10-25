package report

import (
	"errors"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// TestAnIssueExistsOnlyWhereGhSaidSoAndPrintedIt holds what a report may believe about its own filing. The record
// of a reported sin is keyed to an issue number, so a number read from anything but a created issue's own URL
// would key a row to an issue that is not there, and the next run would file the same report again.
func TestAnIssueExistsOnlyWhereGhSaidSoAndPrintedIt(t *testing.T) {
	for name, test := range map[string]struct {
		stdout string
		stderr string
		err    error
		filed  string
		issue  int
		code   int
	}{
		"gh created the issue": {
			stdout: "https://github.com/jessegall/code-commandments/issues/814\n",
			filed:  "jessegall/code-commandments",
			issue:  814,
		},
		"gh warned before it said so": {
			stdout: "! Your token is about to expire\nhttps://github.com/jessegall/code-commandments/issues/9\n",
			filed:  "jessegall/code-commandments",
			issue:  9,
		},
		"gh failed and its words name the repository": {
			stderr: "could not reach https://github.com/jessegall/code-commandments: dial tcp: lookup failed\n",
			err:    errors.New("exit status 1"),
			code:   1,
		},
		"gh failed after printing a URL of something else": {
			stdout: "see https://github.com/jessegall/code-commandments/issues/1 for the template\n",
			err:    errors.New("exit status 1"),
			code:   1,
		},
		"gh is not logged in": {
			stderr: "gh: To get started with GitHub CLI, please run: gh auth login\n",
			err:    errors.New("exit status 4"),
			code:   1,
		},
		"gh said nothing at all": {
			code: 1,
		},
	} {
		t.Run(name, func(t *testing.T) {
			found, ran := looking, filing
			looking = func() (string, error) { return "gh", nil }
			filing = func(string, ...string) (string, string, error) { return test.stdout, test.stderr, test.err }
			defer func() { looking, filing = found, ran }()

			said := &strings.Builder{}
			filed, code := File("[bug-report] a title", "a body", cli.Console{Out: said, Err: said})

			if code != test.code {
				t.Errorf("answered %d, want %d", code, test.code)
			}

			if filed.Repository != test.filed || filed.Issue != test.issue {
				t.Errorf("filed %+v, want %s#%d", filed, test.filed, test.issue)
			}

			if filed.Exists() != (test.issue > 0) {
				t.Errorf("Exists says %v for %+v", filed.Exists(), filed)
			}

			if !strings.Contains(said.String(), strings.TrimSpace(test.stdout+test.stderr)) {
				t.Errorf("the console was not told what gh answered: %q", said.String())
			}
		})
	}
}

// TestNoGitHubCLIFilesNothingAndSaysHowToDoItByHand holds the case the record depends on most: with no gh there is
// no issue, so there must be no row either, and the person is told where to open one.
func TestNoGitHubCLIFilesNothingAndSaysHowToDoItByHand(t *testing.T) {
	found := looking
	looking = func() (string, error) { return "", errors.New("gh: not found") }
	defer func() { looking = found }()

	said := &strings.Builder{}
	filed, code := File("[bug-report] a title", "a body", cli.Console{Out: said, Err: said})

	if code != 2 {
		t.Errorf("answered %d, want 2: there is no gh to ask", code)
	}

	if filed.Exists() {
		t.Errorf("filed %+v with no gh to file it", filed)
	}

	if !strings.Contains(said.String(), "issues/new") {
		t.Errorf("the person was not told where to open one: %q", said.String())
	}
}

// TestAFiledIssueNamesTheURLItWasReadFrom holds the one thing a row keeps besides the number: a reader of the
// record, or of the dashboard, opens the issue without knowing how the URL is built.
func TestAFiledIssueNamesTheURLItWasReadFrom(t *testing.T) {
	filed := Filed{Repository: "jessegall/code-commandments", Issue: 814}

	if filed.URL() != "https://github.com/jessegall/code-commandments/issues/814" {
		t.Errorf("the issue opens at %q", filed.URL())
	}

	if (Filed{}).URL() != "" {
		t.Error("a report that filed nothing still answers a URL")
	}
}
