package shared

import (
	"strings"
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// InterfaceOverlap finds an interface that could reuse another accessible contract.
type InterfaceOverlap struct{}

func (InterfaceOverlap) Find(*engine.Codebase) []engine.Match { return nil }

func (InterfaceOverlap) Sin() Text {
	return Text{
		Name: "interface-overlap", Skill: skills.Dependencies{},
		Description: "An interface repeats the signatures of a contract it can reuse.",
		Rule:        "Use the accessible contract for identical signatures. Extend it to add the remaining signatures.",
		Suggestion:  "Check that the contracts promise the same behaviour before reusing the existing one.",
	}
}

func (InterfaceOverlap) CrossFile() {}

// InterfaceOverlaps judges signatures within the dependency direction declared for a language.
type InterfaceOverlaps struct {
	Detector
	layers             engine.LayerStack
	separator          string
	caseSensitive      bool
	public, registrars []string
}

func BindInterfaceOverlaps[L Language](separator string, caseSensitive bool) InterfaceOverlaps {
	return register(InterfaceOverlaps{
		Detector: bound[InterfaceOverlap, L](nil), separator: separator, caseSensitive: caseSensitive,
		layers: engine.Layers(nil, separator, caseSensitive),
	})
}

func (d InterfaceOverlaps) WithLayers(layers map[string][]string) detectors.Detector {
	d.layers = engine.Layers(layers, d.separator, d.caseSensitive)
	return d
}

func (d InterfaceOverlaps) WithPublicContracts(symbols, registrars []string) detectors.Detector {
	d.public, d.registrars = slices.Clone(symbols), slices.Clone(registrars)
	return d
}

func (d InterfaceOverlaps) Find(codebase *engine.Codebase) []engine.Match {
	codebase = d.part(codebase)
	public := d.publicInterfaces(codebase)
	return codebase.WhereTypeDeclaration().Where(engine.Match.IsInterfaceType).
		Where(func(declaration engine.Match) bool {
			return declaration.ReusableInterface(d.layers, public).Contract.Exists()
		}).
		Get()
}

func (d InterfaceOverlaps) AdviceFor(finding engine.Match) string {
	reuse := finding.ReusableInterface(d.layers, d.publicInterfaces(d.part(finding.Codebase())))
	if !reuse.Contract.Exists() {
		return ""
	}
	if reuse.Exact {
		return "Reuse " + reuse.Contract.Identity() + " if it promises the same behaviour."
	}

	return "Extend " + reuse.Contract.Identity() + " and declare only the added methods if the shared methods promise the same behaviour."
}

func (d InterfaceOverlaps) part(codebase *engine.Codebase) *engine.Codebase {
	return codebase.Of(catalog.LanguagesOf(d.Engine())...)
}

func (d InterfaceOverlaps) publicInterfaces(codebase *engine.Codebase) map[string]bool {
	public := map[string]bool{}
	for _, name := range publishedInterfaces(codebase, d.public, d.registrars) {
		public[name] = true
	}
	return public
}

type interfacePublications struct{ symbols, registrars string }

func publishedInterfaces(codebase *engine.Codebase, symbols, registrars []string) []string {
	if len(registrars) == 0 {
		return symbols
	}
	key := interfacePublications{strings.Join(symbols, "\x00"), strings.Join(registrars, "\x00")}
	return engine.Analysis(codebase, key, func(codebase *engine.Codebase) []string {
		public := slices.Clone(symbols)
		for _, call := range codebase.Reporting().WhereCall().Get() {
			public = append(public, call.PublishedContracts(registrars)...)
		}
		return public
	})
}
