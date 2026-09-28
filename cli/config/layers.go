package config

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

// Layers are the layers the project declares for an engine: each namespace and the ones it may use, as the
// config gives them to that engine's NamespaceDependencyDetector, a leading `\` dropped. Empty when it declares none.
func (c Config) Layers(engine catalog.Engine) map[string][]string {
	layers := map[string][]string{}

	for _, layer := range layersOf(c, engine) {
		var mayUse []string
		for _, used := range layer.MayUse {
			mayUse = append(mayUse, strings.Trim(used, `\`))
		}

		layers[strings.Trim(layer.Namespace, `\`)] = mayUse
	}

	return layers
}
