package config

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

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
