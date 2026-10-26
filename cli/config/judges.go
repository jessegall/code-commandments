package config

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
)

// Judges is the call a project writes under a detector to say that this one rule judges a folder the project
// excludes, and nothing else does:
//
//	"configure": {"custom/TestsDriveTheRealPath": [{"judges": ["tests"]}]}
//
// An excluded folder stays excluded for every other rule. A project's tests are the case it was built for: the
// rules about naming read a test's name as a sentence, which is what a test name is, so judging tests with
// everything buries the few rules that belong there — measured on one project at 9,773 findings in 85 files,
// of which nearly all were names. One rule allowed in is the whole of what is wanted.
//
// The call is the config layer's own and is never sent to the detector. No shipped detector has such a method,
// and a project's own rule is a JSON file with no methods at all, so a whitelist that worked by reflection
// would work for neither.
const Judges = "judges"

// Judged are the excluded folders this detector judges anyway, as the project's config says.
func (c Config) Judged(detector Rule) []string {
	return c.judged(func(target Rule) bool { return target.ID() == detector.ID() })
}

// JudgedDespiteExclusion are every excluded folder some detector judges anyway, each once: what a scan has to
// read although the project excluded it, since a folder nothing reads is a folder no rule can judge.
func (c Config) JudgedDespiteExclusion() []string {
	return c.judged(func(Rule) bool { return true })
}

// judged are the folders the judges calls of the configurators wanted name, each once.
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

// judgedBy are the folders one configurator's judges calls name, in the order it names them.
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

// folderNamesIn are the folders a judges call's arguments name, relative to the project and without slashes
// around them. A list is read through, so `{"judges": [["tests", "spec"]]}` says the same as
// `{"judges": ["tests", "spec"]}`.
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

// folderOf is the folder one argument names; empty for an argument that names none.
func folderOf(value any) string {
	named, isText := value.(string)
	if !isText {
		return ""
	}

	return strings.Trim(strings.TrimSpace(named), "/")
}

// RulesFor are the rules that may mark this file: every rule given, or, for a file inside a folder the project
// excludes and one rule judges, only the rules that judge that folder.
//
// The reading belongs here rather than in the hook, because it is the config that says both things: which
// folders are excluded, and which rule was let into one. A rule is matched by the same id a configure block
// names it with, so a project's own rule and a shipped one are asked the same way.
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

// namesOf are the ids a config may name this rule by: the engine and the name for a shipped rule, and the bare
// name as well, which is how a project names a rule of its own everywhere else — `detectors` turns one on by its
// bare name and `disable` turns it off by the same. A lookup that asked for only one of the two would miss the
// project's own rules, which are the ones most likely to be let into an excluded folder.
func namesOf(detector detectors.Detector) []Rule {
	engine, _ := detectors.EngineOf(detector)
	name := catalog.Name(detector)

	return []Rule{{Kind: Detector, Engine: engine, Name: name}, {Kind: Detector, Name: name}}
}

// judgedFolderOf is the excluded-but-judged folder the file sits in; empty for a file in none of them, which is
// every file of the project's declared paths.
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

// Declaring is a rule that names the folders it judges in its own file. A project's own rule is such a file: the
// project wrote it, no install rewrites it, and the rule and the folders it judges belong together in it, so the
// whitelist survives a config written again. A shipped rule declares none, since a folder list inside a rule the
// tool ships could not be changed by the project that runs it; those folders are named in the config, which is
// where a consumer can change them.
type Declaring interface {
	Name() string
	Judges() []string
}

// Judging is this config read with what the rules themselves declare, which is how the two sources become one: a
// rule's own judges key is written as the very call the config writes under that rule, so every reader below, and
// the walk, the report filter and the hook above them, answers from this one place and a folder named in both
// sources counts once.
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
