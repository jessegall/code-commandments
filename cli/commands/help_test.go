package commands

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

func ran(args ...string) (code int, out string, warned string) {
	var screen, errs bytes.Buffer
	code = Kernel("1.2.3").Run(args, cli.Console{Out: &screen, Err: &errs})

	return code, screen.String(), errs.String()
}

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
