package shared

import (
	"maps"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// NamespaceCycle finds two modules that import each other.
type NamespaceCycle struct{}

func (NamespaceCycle) Find(codebase *engine.Codebase) []engine.Match {
	return engine.ImportArrows(codebase).Independent("/").ClosingAMutualPair()
}

func (NamespaceCycle) Sin() Text {
	return Text{Name: "namespace-cycle", Skill: skills.Dependencies{}, Description: "Two independent modules import each other", Rule: "Give the dependency one direction.", Suggestion: "Move the shared contract to a module both can use."}
}

func (NamespaceCycle) CrossFile() {}

// NamespaceDependency finds imports crossing a configured layer boundary.
type NamespaceDependency struct{}

func (NamespaceDependency) Find(*engine.Codebase) []engine.Match { return nil }

func (NamespaceDependency) Sin() Text {
	return Text{Name: "namespace-dependency", Skill: skills.Dependencies{}, Description: "An import crosses a layer boundary its source may not use", Rule: "Keep references within the layers declared for their source.", Suggestion: "Move the call or its contract to a layer this source may use."}
}

func (NamespaceDependency) CrossFile() {}

// LayerDependency judges imports using the layers configured for one language.
type LayerDependency[L Language] struct {
	Detector[NamespaceDependency, L]
	layers map[string][]string
}

func BindLayerDependency[L Language]() LayerDependency[L] {
	detector := LayerDependency[L]{Detector: bound[NamespaceDependency, L](nil)}
	register[L](detector)
	return detector
}

func (d LayerDependency[L]) Layer(name string, mayUse ...string) LayerDependency[L] {
	layers := make(map[string][]string, len(d.layers)+1)
	maps.Copy(layers, d.layers)
	layers[name] = mayUse
	d.layers = layers
	return d
}

func (d LayerDependency[L]) Find(codebase *engine.Codebase) []engine.Match {
	if len(d.layers) == 0 {
		return nil
	}
	var language L
	part := codebase.Of(catalog.LanguagesOf(language.Engine())...)
	return engine.ImportArrows(part).LayerViolations(engine.Layers(d.layers, "/", true))
}
