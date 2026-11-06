// Package help is what a command says about itself, and the one place that says it on screen.
package help

import "strings"

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

func (o Option) Name() string {
	return NameOf(o.Spec)
}

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

func Of(summary string) Help {
	return Help{Summary: summary, Section: Commands}
}

func (h Help) Form(syntax string, does ...string) Help {
	h.Forms = append(append([]Form(nil), h.Forms...), Form{syntax, strings.Join(does, "")})

	return h
}

func (h Help) Option(spec, does string) Help {
	h.Options = append(append([]Option(nil), h.Options...), Option{spec, does})

	return h
}

func (h Help) Adopt(options []Option) Help {
	h.Options = append(append([]Option(nil), h.Options...), options...)

	return h
}

func (h Help) Note(paragraph string) Help {
	h.Notes = append(append([]string(nil), h.Notes...), paragraph)

	return h
}

func (h Help) In(section string) Help {
	h.Section = section

	return h
}

func (h Help) OptionNames() []string {
	var names []string

	for _, option := range h.Options {
		if name := option.Name(); name != "" {
			names = append(names, name)
		}
	}

	return names
}

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
