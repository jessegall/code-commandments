package scribes

import (
	"slices"
	"strings"
)

// Step is one rewriter the chain runs: it reads the roots as the drafts so far leave them and drafts what it changes.
type Step interface {
	Name() string
	Run(pass Pass) (Rewrites, error)
}

// SinStep is a step that fixes one sin, found by the sin's name as well as its own.
type SinStep interface {
	Step
	MatchesSin(query string) bool
}

// Pass is what a step runs over: the roots, which files it may fix, and every file drafted so far.
type Pass struct {
	Roots  []string
	Scope  Scope
	Frozen Frozen
	Drafts Rewrites
}

// Fixes says whether a finding in the file is one the pass may fix: in scope, and not frozen.
func (p Pass) Fixes(path string) bool {
	return p.Scope.Includes(path) && !p.Frozen.IsFrozen(path)
}

// Scope says which files a run may fix, and whether it is narrowed to a set of files rather than the whole tree.
type Scope interface {
	Includes(path string) bool
	IsScoped() bool
}

// Chain is the steps repent runs, in order.
type Chain struct {
	steps []Step
}

// Steps is every step, in order.
func (c *Chain) Steps() []Step {
	return slices.Clone(c.steps)
}

// Prepend runs a step first.
func (c *Chain) Prepend(step Step) *Chain {
	c.steps = append([]Step{step}, c.steps...)

	return c
}

// Append runs a step last.
func (c *Chain) Append(step Step) *Chain {
	c.steps = append(c.steps, step)

	return c
}

// Before runs a step just before the one named, or last when none is.
func (c *Chain) Before(name string, step Step) *Chain {
	return c.insert(name, step, 0)
}

// After runs a step just after the one named, or last when none is.
func (c *Chain) After(name string, step Step) *Chain {
	return c.insert(name, step, 1)
}

// Replace runs a step in place of every one named.
func (c *Chain) Replace(name string, step Step) *Chain {
	for index, existing := range c.steps {
		if existing.Name() == name {
			c.steps[index] = step
		}
	}

	return c
}

// Remove drops every step named.
func (c *Chain) Remove(name string) *Chain {
	c.steps = slices.DeleteFunc(c.steps, func(step Step) bool { return step.Name() == name })

	return c
}

// Matching keeps the steps whose name holds the query, in any case, or that fix a sin it matches.
func (c *Chain) Matching(query string) *Chain {
	c.steps = slices.DeleteFunc(c.steps, func(step Step) bool {
		if strings.Contains(strings.ToLower(step.Name()), strings.ToLower(query)) {
			return false
		}
		fixer, fixes := step.(SinStep)

		return !fixes || !fixer.MatchesSin(query)
	})

	return c
}

func (c *Chain) insert(name string, step Step, offset int) *Chain {
	for index, existing := range c.steps {
		if existing.Name() == name {
			c.steps = slices.Insert(c.steps, index+offset, step)

			return c
		}
	}

	return c.Append(step)
}
