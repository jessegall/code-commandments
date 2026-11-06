package python

import (
	"regexp"
	"slices"
	"strings"
	"sync"
)

var numeric = regexp.MustCompile(`^\s*[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?\s*$`)

// vocabulary is which parameter slots the program fills with a named class constant, and from which classes.
type vocabulary struct {
	once    sync.Once
	classes map[string][]Node
}

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
				class, called := p.ClassCalled(owner.Name())
				if !called || len(class.StringConstants()) == 0 {
					continue
				}
				if slices.Contains(p.vocabulary.classes[slot], class) {
					continue
				}
				p.vocabulary.classes[slot] = append(p.vocabulary.classes[slot], class)
			}
		}
	}
}

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
