package config

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
)

const Judges = "judges"

func (c Config) Judged(detector Rule) []string {
	return c.judged(func(target Rule) bool { return target.ID() == detector.ID() })
}

func (c Config) JudgedDespiteExclusion() []string {
	return c.judged(func(Rule) bool { return true })
}

func (c Config) judged(wanted func(Rule) bool) []string {
	var folders []string
	for _, configurator := range c.Configurators {
		if !wanted(configurator.Target) {
			continue
		}
		for _, folder := range judgedBy(configurator) {
			if slices.Contains(folders, folder) {
				continue
			}
			folders = append(folders, folder)
		}
	}

	return folders
}

func judgedBy(configurator Configurator) []string {
	var folders []string
	for _, call := range configurator.Calls {
		if call.Method != Judges {
			continue
		}
		folders = append(folders, folderNamesIn(call.Args)...)
	}

	return folders
}

func folderNamesIn(args []Arg) []string {
	var folders []string
	for _, argument := range args {
		if folder := folderOf(argument.Value); folder != "" {
			folders = append(folders, folder)

			continue
		}
		list, isList := argument.Value.([]any)
		if !isList {
			continue
		}
		for _, held := range list {
			if folder := folderOf(held); folder != "" {
				folders = append(folders, folder)
			}
		}
	}

	return folders
}

func folderOf(value any) string {
	named, isText := value.(string)
	if !isText {
		return ""
	}

	return strings.Trim(strings.TrimSpace(named), "/")
}

func (c Config) RulesFor(root, file string, rules []detectors.Detector) []detectors.Detector {
	inside := c.judgedFolderOf(root, file)
	if inside == "" {
		return rules
	}

	var kept []detectors.Detector
	for _, rule := range rules {
		if slices.ContainsFunc(namesOf(rule), func(named Rule) bool { return slices.Contains(c.Judged(named), inside) }) {
			kept = append(kept, rule)
		}
	}

	return kept
}

func namesOf(detector detectors.Detector) []Rule {
	engine, _ := detectors.EngineOf(detector)
	name := catalog.Name(detector)

	return []Rule{{Kind: Detector, Engine: engine, Name: name}, {Kind: Detector, Name: name}}
}

func (c Config) judgedFolderOf(root, file string) string {
	home := strings.TrimRight(root, "/")
	absolute := file
	if !strings.HasPrefix(file, "/") {
		absolute = home + "/" + strings.TrimLeft(file, "/")
	}

	for _, folder := range c.JudgedDespiteExclusion() {
		dir := home + "/" + folder
		if absolute == dir || strings.HasPrefix(absolute, dir+"/") {
			return folder
		}
	}

	return ""
}

// Declaring is a rule that names the folders it judges in its own file.
type Declaring interface {
	Name() string
	Judges() []string
}

func (c Config) Judging(rules []detectors.Detector) Config {
	var declared []Configurator

	for _, rule := range rules {
		declaring, declares := rule.(Declaring)
		if !declares {
			continue
		}
		folders := declaring.Judges()
		if len(folders) == 0 {
			continue
		}

		var args []Arg
		for _, folder := range folders {
			args = append(args, Arg{Value: folder})
		}
		declared = append(declared, Configurator{
			Target: Rule{Kind: Detector, Name: declaring.Name()},
			Calls:  []Call{{Method: Judges, Args: args}},
		})
	}

	if len(declared) == 0 {
		return c
	}
	c.Configurators = append(slices.Clone(c.Configurators), declared...)

	return c
}
