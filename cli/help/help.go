// Package help is what a command says about itself, and the one place that says it on screen. Help is
// declared ON the command, beside the code that reads its flags; the overview, every command's page and
// every usage error are projected from it, so there is no second list to keep in step.
package help

import "strings"

// The overview headings a command can sit under: the plumbing verbs a human never types are grouped away
// from the ones they do.
const (
	Commands = "Commands"
	Hooks    = "Hooks (wired automatically — you rarely run these by hand)"
)

// Form is one invocation, as typed after `commandments`, with the half-line beside it.
type Form struct {
	Syntax string
	Does   string
}

// Option is one flag, spelled as a user types it (`--branch[=BASE]`), with what it does.
type Option struct {
	Spec string
	Does string
}

// Name is the flag a user actually types, read from how it is spelled: `--branch[=BASE]` is `branch`.
func (o Option) Name() string {
	return NameOf(o.Spec)
}

// NameOf reads a flag's name from its spelling, up to the first `=` or `[`.
func NameOf(spec string) string {
	name, _, _ := strings.Cut(spec, "=")
	name, _, _ = strings.Cut(name, "[")

	return strings.TrimLeft(name, "-")
}

// Help is a command's summary, the forms it answers to, the options it reads and any longer notes.
type Help struct {
	Summary string
	Forms   []Form
	Options []Option
	Notes   []string
	Section string
}

// Of starts a command's help from its one-line summary, under the Commands heading.
func Of(summary string) Help {
	return Help{Summary: summary, Section: Commands}
}

// Form adds one invocation form.
func (h Help) Form(syntax string, does ...string) Help {
	h.Forms = append(append([]Form(nil), h.Forms...), Form{syntax, strings.Join(does, "")})

	return h
}

// Option adds one flag the command reads.
func (h Help) Option(spec, does string) Help {
	h.Options = append(append([]Option(nil), h.Options...), Option{spec, does})

	return h
}

// Adopt takes flags another collaborator owns and parses, so every command that hands it the raw tail
// documents them from that one declaration.
func (h Help) Adopt(options []Option) Help {
	h.Options = append(append([]Option(nil), h.Options...), options...)

	return h
}

// Note adds a free paragraph printed under the options.
func (h Help) Note(paragraph string) Help {
	h.Notes = append(append([]string(nil), h.Notes...), paragraph)

	return h
}

// In files the command under another overview heading.
func (h Help) In(section string) Help {
	h.Section = section

	return h
}

// OptionNames is the name of every declared flag: what an unknown flag is measured against.
func (h Help) OptionNames() []string {
	var names []string

	for _, option := range h.Options {
		if name := option.Name(); name != "" {
			names = append(names, name)
		}
	}

	return names
}

// Synopsis is the first form's syntax, what the overview shows beside the verb.
func (h Help) Synopsis() string {
	if len(h.Forms) == 0 {
		return ""
	}

	return h.Forms[0].Syntax
}

// Documented is anything that answers to verbs and describes itself: every command.
type Documented interface {
	Names() []string
	Help() Help
}
