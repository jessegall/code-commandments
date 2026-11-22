package config

import (
	"fmt"
	"path"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/skill"
)

var ownRuleName = regexp.MustCompile(ownRule)

func (r Rule) Names(detector rule.Detector) bool {
	if r.Kind == Skill {
		return r.namesSkill(detector.Sin().Definition().Skill)
	}

	return (r.Kind == Detector || r.Kind == Sin) && len(rule.EveryNamedIn([]rule.Detector{detector}, r.ID())) == 1
}

func (r Rule) namesSkill(taught skill.Skill) bool {
	slug := taught.Definition().Slug
	if r.Engine == "" {
		return catalog.Normalise(r.Name) == catalog.Normalise(path.Base(slug)) || catalog.Normalise(r.Name) == catalog.Normalise(catalog.Name(taught))
	}

	return catalog.Normalise(r.ID()) == catalog.Normalise(slug)
}

func (r Rule) mayBeTheProjects() bool {
	return r.Engine == "" && ownRuleName.MatchString(r.Name)
}

func (c Config) TurnsOff(detector rule.Detector) bool {
	return slices.ContainsFunc(c.Disabled, func(off Rule) bool { return off.Names(detector) })
}

func (c Config) resolved() error {
	every := rule.All()
	var lines []string

	for _, off := range c.Disabled {
		switch {
		case off.Kind == Hook || off.Kind == Agent || off.mayBeTheProjects():
		case off.Kind == Skill && slices.ContainsFunc(skill.All(), off.namesSkill):
		case off.Kind != Skill && slices.ContainsFunc(every, off.Names):
		default:
			lines = append(lines, fmt.Sprintf("disable.%s: %q is no %s this version ships", strings.ToLower(string(off.Kind)), off.ID(), strings.ToLower(strings.TrimSuffix(string(off.Kind), "s"))))
		}
	}

	for _, tuned := range c.Configurators {
		at := indexOf(every, tuned.Target)
		if at < 0 {
			if !tuned.Target.mayBeTheProjects() {
				lines = append(lines, fmt.Sprintf("configure: %q is no rule this version ships", tuned.Target.ID()))
			}

			continue
		}
		for _, call := range tuned.Calls {
			if call.Method == Judges {
				continue
			}
			if _, err := apply(every[at], call); err != nil {
				lines = append(lines, fmt.Sprintf("configure.%s.%s: %v", tuned.Target.ID(), call.Method, err))
			}
		}
	}

	if len(lines) == 0 {
		return nil
	}

	return &cli.InvalidConfiguration{Reason: ".commandments/config.json names what this version cannot run; change each line, then run again:\n  " + strings.Join(lines, "\n  ")}
}
