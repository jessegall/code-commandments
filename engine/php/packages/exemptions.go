package packages

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// Ancestry answers whether a class is another, extends it or implements it.
type Ancestry interface {
	IsA(class, base string) bool
}

// Clause is what one tag excuses: whole classes, methods of a class, methods by name anywhere, and attributes.
type Clause struct {
	classes      []string
	classMethods map[string][]string
	methods      []string
	attributes   []string
}

// Classes excuses these classes and everything that extends or implements them.
func (c *Clause) Classes(classes ...string) *Clause {
	c.classes = append(c.classes, classes...)

	return c
}

// On excuses these methods of the class and its descendants, or the whole class when no method is named.
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

// Methods excuses methods with these names on any class.
func (c *Clause) Methods(methods ...string) *Clause {
	c.methods = append(c.methods, methods...)

	return c
}

// Attributes excuses these attributes and their descendants.
func (c *Clause) Attributes(attributes ...string) *Clause {
	c.attributes = append(c.attributes, attributes...)

	return c
}

// Matches says whether the clause excuses the class, or the method of it; an empty class or method is none.
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

// MatchesAttribute says whether the clause excuses the attribute; an empty one is none.
func (c *Clause) MatchesAttribute(ancestry Ancestry, attribute string) bool {
	return attribute != "" && isA(ancestry, strings.TrimLeft(attribute, `\`), c.attributes)
}

func isA(ancestry Ancestry, class string, bases []string) bool {
	return slices.ContainsFunc(bases, func(base string) bool { return ancestry.IsA(class, base) })
}

// Exemptions is every clause the packages in force registered, by tag.
type Exemptions struct {
	clauses map[string]*Clause
}

// Exempt is the tag's clause, for a package to add to.
func (e *Exemptions) Exempt(tag Tag) *Clause {
	if e.clauses == nil {
		e.clauses = map[string]*Clause{}
	}
	if e.clauses[tag.Slug] == nil {
		e.clauses[tag.Slug] = &Clause{}
	}

	return e.clauses[tag.Slug]
}

// Has says whether a package excuses the class, or the method of it, under the tag.
func (e *Exemptions) Has(tag Tag, ancestry Ancestry, class, method string) bool {
	clause, ok := e.clauses[tag.Slug]

	return ok && clause.Matches(ancestry, class, method)
}

// HasAttribute says whether a package excuses the attribute under the tag.
func (e *Exemptions) HasAttribute(tag Tag, ancestry Ancestry, attribute string) bool {
	clause, ok := e.clauses[tag.Slug]

	return ok && clause.MatchesAttribute(ancestry, attribute)
}

// Package is a dependency that excuses types of its own from general rules.
type Package interface {
	Register(exemptions *Exemptions)
}

// Shipped is every package the tool knows by itself.
var Shipped = []Package{Laravel{}, Spatie{}}

// For is what the shipped packages and the project's own register.
func For(consumers ...Package) *Exemptions {
	exemptions := &Exemptions{}
	for _, pack := range append(slices.Clone(Shipped), consumers...) {
		pack.Register(exemptions)
	}

	return exemptions
}

var inForce = php.Memoised(func(*engine.Codebase) *Exemptions { return For() })

// Of is the exemptions in force for a scan: the shipped packages', unless the scan was handed others.
func Of(codebase *engine.Codebase) *Exemptions {
	return inForce.Of(codebase)
}

// Use hands a scan the exemptions of the packages its project names.
func Use(codebase *engine.Codebase, exemptions *Exemptions) {
	inForce.Keep(codebase, exemptions)
}

// Excuses says whether the scan's packages excuse the class, or the method of it, under the tag.
func Excuses(codebase *engine.Codebase, tag Tag, class, method string) bool {
	return Of(codebase).Has(tag, php.ProgramOf(codebase), class, method)
}

// ExcusesAttribute says whether the scan's packages excuse the attribute under the tag.
func ExcusesAttribute(codebase *engine.Codebase, tag Tag, attribute string) bool {
	return Of(codebase).HasAttribute(tag, php.ProgramOf(codebase), attribute)
}
