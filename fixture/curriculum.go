package fixture

import (
	"errors"
	"maps"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/skill/render"
)

// Curriculum is the worked examples every engine's fixture under fixtures carves for the skills: one folder per
// engine (backend, frontend, python, csharp), each read in its own languages alone. Without the C# bridge's image
// it fails naming it, before anything is read: a curriculum missing one engine's examples is not one to publish.
func Curriculum(fixtures string) (render.Examples, error) {
	csharp := filepath.Join(fixtures, "csharp")
	if _, err := bridge.Roslyn(csharp); err != nil {
		return nil, errors.New(bridge.RoslynMissing())
	}
	examples := render.Examples{}
	for _, engine := range []struct {
		folder    string
		languages []source.Language
		proven    []detectors.Detector
		fallback  source.Language
	}{
		{"backend", []source.Language{source.PHP}, detectors.Of(catalog.Backend), source.PHP},
		{"frontend", []source.Language{source.Vue, source.TypeScript}, append(detectors.Of(catalog.Frontend), detectors.Of(catalog.TypeScript)...), source.Vue},
		{"python", []source.Language{source.Python}, detectors.Of(catalog.Python), source.Python},
		{"csharp", []source.Language{source.CSharp}, detectors.Of(catalog.CSharp), source.CSharp},
	} {
		root := filepath.Join(fixtures, engine.folder)
		codebase, err := scan.Walk([]string{root}, source.Under(root, nil)).Only(engine.languages...).Load()
		if err != nil {
			return nil, err
		}
		if engine.fallback == source.PHP {
			maps.Copy(examples, CarveDeclarations(root, codebase, engine.proven))
			continue
		}
		maps.Copy(examples, CarveMarks(root, codebase, engine.proven, engine.fallback))
	}

	return examples, nil
}
