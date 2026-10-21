package rule

import (
	"fmt"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

// Composed is every rule with the rules it names found among them: a step's `rule`, and the `from` a rule narrows.
// A name nothing declares, a rule of another engine, and a chain that comes back to itself are refused here, when
// the rules are read, so a judge never meets a reference that cannot be followed. A rule a reference names is a
// copy of its own, so what one rule is built from is never moved by what another rule's refusal drops.
func Composed(read []Rule) ([]Rule, []error) {
	known := map[string]*Rule{}
	for _, one := range read {
		kept := one
		known[reference(one.Name())] = &kept
	}

	refused := map[string]bool{}
	var errs []error
	for at := range read {
		if err := read[at].resolve(known, nil); err != nil {
			refused[read[at].Name()] = true
			errs = append(errs, err)
		}
	}

	return slices.DeleteFunc(read, func(one Rule) bool { return refused[one.Name()] }), errs
}

// reference is the name a rule is named by from another rule: its own, less a Detector suffix, in one case.
func reference(name string) string {
	return catalog.Normalise(strings.TrimSuffix(name, "Detector"))
}

// resolve finds every rule this one names, with the chain that led here so a cycle is named rather than followed.
func (r *Rule) resolve(known map[string]*Rule, chain []string) error {
	if at := slices.Index(chain, reference(r.name)); at >= 0 {
		return fmt.Errorf("%s: a rule cannot be built from itself: %s", r.name, strings.Join(append(chain[at:], reference(r.name)), " -> "))
	}

	chain = append(chain, reference(r.name))
	if r.find.From != "" {
		found, err := r.named(r.find.From, known, chain)
		if err != nil {
			return err
		}

		r.find.inner = found
	}

	for _, steps := range [][]Step{r.find.Where, r.find.Reject} {
		for at := range steps {
			if err := steps[at].resolve(r, known, chain); err != nil {
				return err
			}
		}
	}

	return nil
}

// resolve finds the rule a step names, and the rules the steps nested in it name.
func (s *Step) resolve(within *Rule, known map[string]*Rule, chain []string) error {
	if s.Rule != "" {
		found, err := within.named(s.Rule, known, chain)
		if err != nil {
			return err
		}

		s.named = found
	}

	for _, nested := range s.nested() {
		if err := nested.resolve(within, known, chain); err != nil {
			return err
		}
	}

	return nil
}

// named is the rule the name means, resolved itself first, refused when nothing declares it or it judges another
// engine: a rule reads one language's tree, so it can only be built from a rule of its own engine.
func (r *Rule) named(name string, known map[string]*Rule, chain []string) (*Rule, error) {
	found, declares := known[reference(name)]
	if !declares {
		return nil, fmt.Errorf("%s: no rule is called %q, among the project's own", r.name, name)
	}

	if found.engine != r.engine {
		return nil, fmt.Errorf("%s: rule %s judges the %s engine, and this one judges %s", r.name, found.Name(), found.engine, r.engine)
	}

	if err := found.resolve(known, chain); err != nil {
		return nil, err
	}

	return found, nil
}

// ComposedWith is one rule with the rules it names found among those already read: how a single rule file, read
// on its own, still reaches what it is built from.
func ComposedWith(one Rule, known []Rule) (Rule, error) {
	composed, refused := Composed(append(slices.Clone(known), one))
	if len(refused) > 0 {
		return Rule{}, refused[0]
	}

	return composed[len(composed)-1], nil
}
