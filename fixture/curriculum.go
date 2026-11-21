package fixture

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rules"
	"github.com/jessegall/code-commandments/skill/render"
)

func Curriculum(fixtures string) (render.Examples, error) {
	csharp := filepath.Join(fixtures, "csharp")
	if _, err := bridge.Roslyn(csharp); err != nil {
		return nil, err
	}
	examples := render.Examples{}
	for _, engine := range []struct {
		folder    string
		languages []source.Language
		proven    []Rule
		fallback  source.Language
	}{
		{"backend", []source.Language{source.PHP}, Proven(rules.Of(catalog.PHP)), source.PHP},
		{"frontend", []source.Language{source.Vue, source.TypeScript}, Proven(detectors.OnePerSin(append(rules.Of(catalog.Vue), rules.Of(catalog.TypeScript)...))), source.Vue},
		{"python", []source.Language{source.Python}, Proven(rules.Of(catalog.Python)), source.Python},
		{"csharp", []source.Language{source.CSharp}, Proven(rules.Of(catalog.CSharp)), source.CSharp},
		{"go", []source.Language{source.Go}, Proven(rules.Of(catalog.Go)), source.Go},
	} {
		root := filepath.Join(fixtures, engine.folder)
		codebase, err := scan.Walk([]string{root}, source.Under(root, nil)).Only(engine.languages...).Load()
		if err != nil {
			return nil, err
		}
		if engine.fallback == source.PHP {
			examples.Add(CarveDeclarations(root, codebase, engine.proven))
			continue
		}
		examples.Add(CarveMarks(root, codebase, engine.proven, engine.fallback))
	}

	return examples, nil
}

// Proven is every detector of the catalogue as a rule a fixture proves.
func Proven(found []detectors.Detector) []Rule {
	held := make([]Rule, len(found))
	for at, each := range found {
		held[at] = each
	}

	return held
}
