package judge

import (
	"bytes"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// judgeReader is the reader the plugin ships for the judge, read the way the journal settles a run with it.
type judgeReader struct {
	failed  *regexp.Regexp
	summary *regexp.Regexp
}

func shippedJudgeReader(t *testing.T) judgeReader {
	t.Helper()
	content, err := os.ReadFile(filepath.Join("..", "..", ".journal-plugin", "readers", "judge.pattern"))
	if err != nil {
		t.Fatal(err)
	}

	patterns := map[string]*regexp.Regexp{}
	for _, line := range strings.Split(string(content), "\n") {
		key, value, named := strings.Cut(line, ": ")
		if !named {
			continue
		}
		patterns[key] = regexp.MustCompile(value)
	}

	return judgeReader{failed: patterns["failed"], summary: patterns["summary"]}
}

func (r judgeReader) settle(output string) (failed bool, summary string) {
	for _, line := range strings.Split(output, "\n") {
		failed = failed || r.failed.MatchString(line)
		if r.summary.MatchString(line) {
			summary = line
		}
	}

	return failed, summary
}

func TestAWarningOnARunThatEndsCleanReadsAsPassed(t *testing.T) {
	output := "⚠ no .NET SDK is installed here, so C#'s framework types do not resolve\n✓ No sins found in 3 files.\n"

	failed, summary := shippedJudgeReader(t).settle(output)

	if failed || summary != "✓ No sins found in 3 files." {
		t.Errorf("a run with a warning that ends clean reads as failed=%v, summarised %q", failed, summary)
	}
}

func TestEveryEarlyEndOfTheJudgeIsAFailureTheReaderSummarises(t *testing.T) {
	noJournalVariables(t)
	project := emptyProject(t)

	ends := map[string]string{}
	_, _, ends["nothing at the path"] = judgedIn(t, project, "nope")
	_, _, ends["no rule matched"] = judgedIn(t, project, "--sin=zzz-matches-nothing")
	ends["an unknown option"] = kernelWarns([]string{"judge", "--nope"})
	ends["an unknown command"] = kernelWarns([]string{"nope"})
	t.Setenv("JOURNAL_PROJECT_ROOT", "/somewhere")
	_, _, ends["a refused cold read"] = judgedIn(t, project, ".", "--no-checklist")

	reader := shippedJudgeReader(t)
	for end, warned := range ends {
		failed, summary := reader.settle(warned)
		if !failed || !strings.HasPrefix(summary, "✗ ") {
			t.Errorf("the judge ending on %s reads as failed=%v, summarised %q, from %q", end, failed, summary, warned)
		}
	}
}

func kernelWarns(args []string) string {
	var out, warned bytes.Buffer
	cli.NewKernel("dev", Command{}).Run(args, cli.Console{Out: &out, Err: &warned})

	return warned.String()
}
