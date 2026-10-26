package commands

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// The tool's whole verb surface is declared in this package, and until now the only thing that read it was the
// binary: the recorded parity cases run `<verb> --help`, an unknown verb and an unknown option against the built
// tool, so a help declaration that had gone wrong could only be caught by a run that builds everything first. The
// kernel answers in process, so these hold the same behaviour here, where a broken declaration is named in seconds.

// ran is what the kernel answered for the arguments: its exit code, what it put on screen, and what it warned.
func ran(args ...string) (code int, out string, warned string) {
	var screen, errs bytes.Buffer
	code = Kernel("1.2.3").Run(args, cli.Console{Out: &screen, Err: &errs})

	return code, screen.String(), errs.String()
}

// TestEveryCommandAnswersItsOwnHelpPage holds every registered command to having a page a reader can use: the verb
// answers, the page names the command and says what it is for, and it shows at least one form to type.
func TestEveryCommandAnswersItsOwnHelpPage(t *testing.T) {
	for _, command := range Kernel("1.2.3").Commands() {
		documented, verb := command, command.Names()[0]

		t.Run(verb, func(t *testing.T) {
			code, page, warned := ran(verb, "--help")

			if code != 0 {
				t.Errorf("`%s --help` exits %d, and says %q", verb, code, warned)
			}
			if !strings.Contains(page, verb) {
				t.Errorf("the page of %s does not name it:\n%s", verb, page)
			}
			if summary := documented.Help().Summary; summary == "" {
				t.Errorf("%s says nothing about what it is for", verb)
			} else if !strings.Contains(page, strings.Fields(summary)[0]) {
				t.Errorf("the page of %s does not carry its summary:\n%s", verb, page)
			}
			if len(documented.Help().Forms) == 0 {
				t.Errorf("%s shows no form to type", verb)
			}
		})
	}
}

// TestEveryOptionIsNamedAsItIsTyped holds the options a command declares to the shape a reader types and the kernel
// reads: a long option, named, with its own description. A spec that lost its dashes is read as no option at all,
// and the flag it stands for is then refused as unknown.
func TestEveryOptionIsNamedAsItIsTyped(t *testing.T) {
	for _, command := range Kernel("1.2.3").Commands() {
		verb := command.Names()[0]

		for _, option := range command.Help().Options {
			if !strings.HasPrefix(option.Spec, "--") {
				t.Errorf("%s declares the option %q, which is not typed as a long option", verb, option.Spec)
			}
			if option.Does == "" {
				t.Errorf("%s declares %q and does not say what it does", verb, option.Spec)
			}
		}
	}
}

// TestTheOverviewHoldsEveryVerbTheToolAnswers holds the screen a reader meets first: `commandments --help` names
// every command, so a command registered here is a command they can find.
func TestTheOverviewHoldsEveryVerbTheToolAnswers(t *testing.T) {
	code, overview, warned := ran("--help")

	if code != 0 {
		t.Fatalf("`--help` exits %d, and says %q", code, warned)
	}

	for _, command := range Kernel("1.2.3").Commands() {
		verb := command.Names()[0]
		if !strings.Contains(overview, verb) {
			t.Errorf("the overview does not name %s", verb)
		}
	}
}

// TestAWrongInvocationSaysWhatToTryAndExitsTwo holds the answer to a verb or an option the tool does not have. Exit
// 2 is what a shell reads as "you typed it wrong", and the line that follows is the only thing that tells the
// reader where to look.
func TestAWrongInvocationSaysWhatToTryAndExitsTwo(t *testing.T) {
	for name, args := range map[string][]string{
		"an unknown verb":                {"tidy"},
		"an unknown option, no verb":     {"--tidy"},
		"an unknown option after a verb": {"judge", "--tidy"},
	} {
		t.Run(name, func(t *testing.T) {
			code, _, warned := ran(args...)

			if code != 2 {
				t.Errorf("exits %d, want 2", code)
			}
			if !strings.Contains(warned, "commandments") {
				t.Errorf("it says %q, which does not say what to try", warned)
			}
		})
	}
}

// TestEveryVerbIsAnsweredByOneCommand holds the registry to one answer per verb: two commands claiming the same
// name means one of them can never be reached, and only the overview would ever show it.
func TestEveryVerbIsAnsweredByOneCommand(t *testing.T) {
	answered := map[string]string{}

	for _, command := range Kernel("1.2.3").Commands() {
		for _, verb := range command.Names() {
			if already, taken := answered[verb]; taken {
				t.Errorf("%s is answered by both %s and %s", verb, already, command.Names()[0])

				continue
			}
			answered[verb] = command.Names()[0]
		}
	}
}
