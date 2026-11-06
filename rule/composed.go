package rule

import (
	"fmt"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

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

func reference(name string) string {
	return catalog.Normalise(strings.TrimSuffix(name, "Detector"))
}

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

func (r *Rule) named(name string, known map[string]*Rule, chain []string) (*Rule, error) {
	found, declares := known[reference(name)]
	if !declares {
		return nil, fmt.Errorf("%s: no rule is called %q, among the project's own", r.name, name)
	}

	if !slices.Equal(found.engines, r.engines) {
		return nil, fmt.Errorf("%s: rule %s judges %s, and this one judges %s", r.name, found.Name(), engineList(found.engines), engineList(r.engines))
	}

	if err := found.resolve(known, chain); err != nil {
		return nil, err
	}

	return found, nil
}

func ComposedWith(one Rule, known []Rule) (Rule, error) {
	composed, refused := Composed(append(slices.Clone(known), one))
	if len(refused) > 0 {
		return Rule{}, refused[0]
	}

	return composed[len(composed)-1], nil
}
