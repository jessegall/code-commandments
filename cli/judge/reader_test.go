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
	patterns := map[string]*regexp.Regexp{}
	for key, value := range shippedReaderSettings(t, "judge.pattern") {
		if key != "commands" {
			patterns[key] = regexp.MustCompile(value)
		}
	}

	return judgeReader{failed: patterns["failed"], summary: patterns["summary"]}
}

func shippedReaderSettings(t *testing.T, file string) map[string]string {
	t.Helper()
	content, err := os.ReadFile(filepath.Join("..", "..", ".journal-plugin", "readers", file))
	if err != nil {
		t.Fatal(err)
	}
	settings := map[string]string{}
	for _, line := range strings.Split(string(content), "\n") {
		if key, value, named := strings.Cut(line, ": "); named {
			settings[key] = value
		}
	}

	return settings
}

var variablesInFront = regexp.MustCompile(`^(?:[A-Za-z_]\w*=\S*\s+)+`)

func readsCommand(commands, command string) bool {
	bare := variablesInFront.ReplaceAllString(command, "")
	for _, pattern := range strings.Split(commands, ",") {
		wildcards := strings.NewReplacer(`\*`, ".*", `\?`, ".").Replace(regexp.QuoteMeta(strings.TrimSpace(pattern)))
		if regexp.MustCompile("^(?s:" + wildcards + ")$").MatchString(bare) {
			return true
		}
	}

	return false
}

func TestTheShippedReadersReadEveryWayTheirCommandIsStarted(t *testing.T) {
	judge := shippedReaderSettings(t, "judge.pattern")["commands"]
	rule := shippedReaderSettings(t, "rule.pattern")["commands"]

	for _, started := range []string{
		"vendor/bin/commandments judge app",
		"bin/commandments-go judge --changes",
		"/project/.journal/plugins/code-commandments/bin/commandments-release judge --changes",
		"COMMANDMENTS_QUEUED=1 .journal/plugins/code-commandments/bin/commandments-release judge",
		"env COMMANDMENTS_QUEUED=1 COMMANDMENTS_PLAIN_PROGRESS=1 /project/.journal/plugins/code-commandments/bin/commandments-release judge",
		"( export COMMANDMENTS_PLAIN_PROGRESS=1; /project/.journal/plugins/code-commandments/bin/commandments-release judge --changes  )",
	} {
		if !readsCommand(judge, started) {
			t.Errorf("the judge's reader names the commands %q, which the journal matches as wildcards, and they do not read a judge started as %q", judge, started)
		}
		if readsCommand(rule, started) {
			t.Errorf("the rule verbs' reader also reads a judge started as %q, whose lines it cannot summarise", started)
		}
	}
	for _, started := range []string{
		"vendor/bin/commandments rule prove",
		"/project/.journal/plugins/code-commandments/bin/commandments-release rule try NoRawSql app",
	} {
		if !readsCommand(rule, started) {
			t.Errorf("the rule verbs' reader names the commands %q, and they do not read %q", rule, started)
		}
		if readsCommand(judge, started) {
			t.Errorf("the judge's reader also reads %q, whose lines it cannot summarise", started)
		}
	}
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
	project := emptyProject(t)

	ends := map[string]string{}
	_, _, ends["nothing at the path"] = judgedIn(t, project, "nope")
	_, _, ends["no rule matched"] = judgedIn(t, project, "--sin=zzz-matches-nothing")
	ends["an unknown option"] = kernelWarns([]string{"judge", "--nope"})
	ends["an unknown command"] = kernelWarns([]string{"nope"})

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
