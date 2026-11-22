// Package catalog is the one enrolment mechanism every kind of rule shares: a sin, a detector and a skill each register themselves from their own file, and a catalog lists them.
package catalog

import (
	"fmt"
	"reflect"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// CrossFile marks a rule that reads MORE than the file it reports in: a recurrence across sites, a twin, a caller, what refers to a declaration, a layer stack, what another engine published.
type CrossFile interface {
	CrossFile()
}

// ReadsAcrossFiles is a rule that answers for ITSELF whether it reads more than the file it reports in, as a project's own rule does: nothing measures a rule a consumer wrote, so it answers from the checks its file writes.
type ReadsAcrossFiles interface {
	ReadsAcrossFiles() bool
}

// ReadsForward is a cross-file rule whose answer for a file rests on that file and on what the others declare, never on
// what the others do inside their bodies: what a file names resolves, its types, its parents. A rule that reads what
// refers TO a file, its callers, its twins or a recurrence, is not one.
type ReadsForward interface {
	ReadsForward() bool
}

func IsForward(rule any) bool {
	answers, states := rule.(ReadsForward)

	return states && answers.ReadsForward()
}

func IsCrossFile(rule any) bool {
	if answers, states := rule.(ReadsAcrossFiles); states {
		return answers.ReadsAcrossFiles()
	}

	_, crossFile := rule.(CrossFile)

	return crossFile
}

// Engine is the part of the curriculum a rule belongs to.
type Engine string

const (
	PHP        Engine = "php"
	Vue        Engine = "vue"
	TypeScript Engine = "typescript"
	Python     Engine = "python"
	CSharp     Engine = "csharp"
	Go         Engine = "go"
	Shared     Engine = "shared"
)

var Engines = []Engine{PHP, Vue, TypeScript, Python, CSharp, Go}

// Curriculum is every engine a rule registers under: each language's, and the shared one whose rules every language judges.
var Curriculum = append(slices.Clone(Engines), Shared)

var languages = map[Engine][]contract.Language{
	PHP:        {contract.PHP},
	Vue:        {contract.Vue, contract.TypeScript},
	TypeScript: {contract.Vue, contract.TypeScript},
	Python:     {contract.Python},
	CSharp:     {contract.CSharp},
	Go:         {contract.Go},
	Shared:     contract.Languages,
}

func LanguagesOf(engine Engine) []contract.Language {
	if named, known := EngineNamed(string(engine)); known {
		engine = named
	}

	return languages[engine]
}

var formerly = map[string]Engine{"backend": PHP, "frontend": Vue}

func FormerNames() []string {
	var names []string
	for _, engine := range Engines {
		for name, was := range formerly {
			if was == engine {
				names = append(names, name)
			}
		}
	}

	return names
}

func EngineNamed(name string) (Engine, bool) {
	if engine, was := formerly[strings.ToLower(name)]; was {
		return engine, true
	}

	engine := Engine(strings.ToLower(name))
	if slices.Contains(Curriculum, engine) {
		return engine, true
	}

	return "", false
}

func (e Engine) Label() string {
	switch e {
	case Vue:
		return "Vue"
	case TypeScript:
		return "TypeScript"
	case Python:
		return "Python"
	case CSharp:
		return "C#"
	case Go:
		return "Go"
	case Shared:
		return "Shared"
	default:
		return "PHP"
	}
}

// Catalog is every registered rule of one kind, each under its engine.
type Catalog[T any] struct {
	mutex   sync.Mutex
	entries []entry[T]
}

type entry[T any] struct {
	engine Engine
	rule   T
}

func (c *Catalog[T]) Register(engine Engine, rule T) {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	for _, registered := range c.entries {
		if sameRegistration(registered.rule, rule) {
			panic(fmt.Sprintf("%s is registered twice", Name(rule)))
		}
	}
	c.entries = append(c.entries, entry[T]{engine, rule})
}

// All is every published rule once, listed under the engine it is registered for, engines in curriculum order.
func (c *Catalog[T]) All() []T {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	var all []T
	for _, engine := range Curriculum {
		var rules []T
		for _, registered := range c.entries {
			if registered.engine == engine {
				rules = append(rules, registered.rule)
			}
		}
		slices.SortFunc(rules, func(a, b T) int { return strings.Compare(Name(a), Name(b)) })
		all = append(all, rules...)
	}

	return all
}

// Of is every published rule that judges the engine: registered for it, or a rule of several engines naming it.
func (c *Catalog[T]) Of(engine Engine) []T {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	var rules []T
	for _, registered := range c.entries {
		if !judges(registered, engine) {
			continue
		}
		rules = append(rules, registered.rule)
	}
	slices.SortFunc(rules, func(a, b T) int { return strings.Compare(Name(a), Name(b)) })

	return rules
}

// Every is every rule that judges the engine, published or not.
func (c *Catalog[T]) Every(engine Engine) []T {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	var rules []T
	for _, registered := range c.entries {
		if judges(registered, engine) {
			rules = append(rules, registered.rule)
		}
	}

	return rules
}

// severalEngines is a rule that states every engine it judges, as a rule file does.
type severalEngines interface {
	Engines() []Engine
}

func judges[T any](registered entry[T], engine Engine) bool {
	if registered.engine == engine {
		return true
	}
	several, states := any(registered.rule).(severalEngines)

	return states && slices.Contains(several.Engines(), engine)
}

func (c *Catalog[T]) EngineOf(rule T) (Engine, bool) {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	for _, registered := range c.entries {
		if sameRegistration(registered.rule, rule) {
			return registered.engine, true
		}
	}

	return "", false
}

func sameRegistration(left, right any) bool {
	if reflect.TypeOf(left) != reflect.TypeOf(right) {
		return false
	}
	type namedRule interface {
		Name() string
		Engine() Engine
	}
	if named, hasName := left.(namedRule); hasName {
		other := right.(namedRule)
		return named.Name() == other.Name() && named.Engine() == other.Engine()
	}
	return true
}


func Name(rule any) string {
	if named, states := rule.(interface{ Name() string }); states {
		return named.Name()
	}

	kind := reflect.TypeOf(rule)
	for kind.Kind() == reflect.Pointer {
		kind = kind.Elem()
	}

	return kind.Name()
}

// Renamed is a rule that still answers to the names it had before, as one merged into a shared rule does.
type Renamed interface {
	FormerNames() []string
}

func Names(rule any) []string {
	names := []string{Name(rule)}
	if renamed, was := rule.(Renamed); was {
		names = append(names, renamed.FormerNames()...)
	}

	return names
}

func Normalise(value string) string {
	var folded strings.Builder
	for _, r := range strings.ToLower(value) {
		if r >= 'a' && r <= 'z' || r >= '0' && r <= '9' {
			folded.WriteRune(r)
		}
	}

	return folded.String()
}
