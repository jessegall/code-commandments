package packages

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// Ancestry answers whether a class is another, extends it or implements it, and whether it uses a trait.
type Ancestry interface {
	IsA(class, base string) bool
	Uses(class, trait string) bool
}

// Clause is what one tag excuses: whole classes, methods of a class, methods by name anywhere, and attributes.
type Clause struct {
	classes      []string
	classMethods map[string][]string
	methods      []string
	attributes   []string
}

func (c *Clause) Classes(classes ...string) *Clause {
	c.classes = append(c.classes, classes...)

	return c
}

func (c *Clause) On(class string, methods ...string) *Clause {
	if len(methods) == 0 {
		return c.Classes(class)
	}
	if c.classMethods == nil {
		c.classMethods = map[string][]string{}
	}
	c.classMethods[class] = append(c.classMethods[class], methods...)

	return c
}

func (c *Clause) Methods(methods ...string) *Clause {
	c.methods = append(c.methods, methods...)

	return c
}

func (c *Clause) Attributes(attributes ...string) *Clause {
	c.attributes = append(c.attributes, attributes...)

	return c
}

func (c *Clause) Matches(ancestry Ancestry, class, method string) bool {
	if class != "" && isA(ancestry, class, c.classes) {
		return true
	}
	if method == "" {
		return false
	}
	if slices.Contains(c.methods, method) {
		return true
	}
	for base, methods := range c.classMethods {
		if slices.Contains(methods, method) && isA(ancestry, class, []string{base}) {
			return true
		}
	}

	return false
}

func (c *Clause) MatchesAttribute(ancestry Ancestry, attribute string) bool {
	return attribute != "" && isA(ancestry, strings.TrimLeft(attribute, `\`), c.attributes)
}

func isA(ancestry Ancestry, class string, bases []string) bool {
	return slices.ContainsFunc(bases, func(base string) bool { return ancestry.IsA(class, base) || ancestry.Uses(class, base) })
}

// Exemptions is every clause the packages in force registered, by tag.
type Exemptions struct {
	clauses map[string]*Clause
}

func (e *Exemptions) Exempt(tag Tag) *Clause {
	if e.clauses == nil {
		e.clauses = map[string]*Clause{}
	}
	if e.clauses[tag.Slug] == nil {
		e.clauses[tag.Slug] = &Clause{}
	}

	return e.clauses[tag.Slug]
}

func (e *Exemptions) Has(tag Tag, ancestry Ancestry, class, method string) bool {
	clause, ok := e.clauses[tag.Slug]

	return ok && clause.Matches(ancestry, class, method)
}

func (e *Exemptions) HasAttribute(tag Tag, ancestry Ancestry, attribute string) bool {
	clause, ok := e.clauses[tag.Slug]

	return ok && clause.MatchesAttribute(ancestry, attribute)
}

// Package is a dependency that excuses types of its own from general rules.
type Package interface {
	Register(exemptions *Exemptions)
}

var Shipped = []Package{Laravel{}, Spatie{}, LaravelActions{}}

func For(consumers ...Package) *Exemptions {
	exemptions := &Exemptions{}
	for _, pack := range append(slices.Clone(Shipped), consumers...) {
		pack.Register(exemptions)
	}

	return exemptions
}

var inForce = engine.Memoised(func(*engine.Codebase) *Exemptions { return For() })

func Of(codebase *engine.Codebase) *Exemptions {
	return inForce.Of(codebase)
}

func Use(codebase *engine.Codebase, exemptions *Exemptions) {
	inForce.Keep(codebase, exemptions)
}

func Excuses(codebase *engine.Codebase, tag Tag, class, method string) bool {
	return Of(codebase).Has(tag, php.ProgramOf(codebase), class, method)
}

func ExcusesAttribute(codebase *engine.Codebase, tag Tag, attribute string) bool {
	return Of(codebase).HasAttribute(tag, php.ProgramOf(codebase), attribute)
}
