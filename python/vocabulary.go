package python

import (
	"regexp"
	"slices"
	"strings"
	"sync"
)

// numeric is a string PHP's is_numeric accepts: a decimal or exponent number, whitespace around it.
var numeric = regexp.MustCompile(`^\s*[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?\s*$`)

// vocabulary is which parameter slots the program fills with a named class constant, and from which classes.
type vocabulary struct {
	once    sync.Once
	classes map[string][]Node
}

// ConstantFor is the constant that already names the string literal handed to the call, `Token.BRACE_OPEN`; none
// when the slot it fills is never spelled by name, which is the answer for almost every string. Indexing slots
// rather than values is what makes a raw string decidable: a slot filled with `Token.COLON` in one place and "{"
// in another, while `Token.BRACE_OPEN` holds "{", is the codebase contradicting itself.
func (p *Program) ConstantFor(call, literal Node) (string, bool) {
	p.vocabulary.once.Do(p.readVocabularies)
	value, _ := literal.Text()
	for slot, argument := range p.slotsFilled(call) {
		if argument != literal {
			continue
		}
		for _, class := range p.vocabulary.classes[slot] {
			if name, ok := class.StringConstants()[value]; ok {
				return class.Name() + "." + name, true
			}
		}
	}

	return "", false
}

// readVocabularies maps every slot some call fills with `Class.CONSTANT` to the classes those constants belong to.
func (p *Program) readVocabularies() {
	p.vocabulary.classes = map[string][]Node{}
	for _, module := range p.modules {
		for _, call := range module.Nodes() {
			if !call.IsCall() || !call.IsEvaluated() {
				continue
			}
			for slot, argument := range p.slotsFilled(call) {
				owner := argument.Child("value")
				if argument.Kind() != "Attribute" || owner.Kind() != "Name" {
					continue
				}
				class, ok := p.ClassCalled(owner.Name())
				if ok && len(class.StringConstants()) > 0 && !slices.Contains(p.vocabulary.classes[slot], class) {
					p.vocabulary.classes[slot] = append(p.vocabulary.classes[slot], class)
				}
			}
		}
	}
}

// slotsFilled is what the call hands each parameter of the def it reaches, keyed `declaration#parameter`.
func (p *Program) slotsFilled(call Node) map[string]Node {
	target, ok := p.TargetOf(call)
	bound, bindable := p.ArgumentsAt(call)
	if !ok || !bindable {
		return nil
	}
	slots := map[string]Node{}
	for name, argument := range bound {
		slots[DeclarationOf(target)+"#"+name] = argument
	}

	return slots
}

// StringConstants is the class's public string constants by the value they hold, the first name for a value
// kept: `{"{": "BRACE_OPEN"}`. A value that reads as a number names nothing.
func (n Node) StringConstants() map[string]string {
	constants := map[string]string{}
	for _, statement := range n.ChildrenIn("body") {
		targets := statement.ChildrenIn("targets")
		if statement.Kind() != "Assign" || len(targets) != 1 || targets[0].Kind() != "Name" || statement.Child("value").Node().Literal != "string" {
			continue
		}
		name := targets[0].Name()
		value, _ := statement.Child("value").Text()
		if _, taken := constants[value]; value != "" && !numeric.MatchString(value) && !strings.HasPrefix(name, "_") && !taken {
			constants[value] = name
		}
	}

	return constants
}
