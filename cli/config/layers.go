package config

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

// dependencyDetector is the detector a project declares its layers on, the same name in every engine that has one.
const dependencyDetector = "NamespaceDependencyDetector"

// Layers are the layers the project declares for an engine: each namespace and the ones it may use, as the
// config's `configure` gives them to that engine's NamespaceDependencyDetector. Empty when it declares none.
func (c Config) Layers(engine catalog.Engine) map[string][]string {
	layers := map[string][]string{}

	for _, configurator := range c.Configurators {
		if configurator.Target.Engine != engine || configurator.Target.Name != dependencyDetector {
			continue
		}

		for _, call := range configurator.Calls {
			if call.Method != "layer" || len(call.Args) == 0 {
				continue
			}

			if namespace, named := call.Args[0].Value.(string); named && namespace != "" {
				layers[strings.Trim(namespace, `\`)] = namesIn(call.Args[1:])
			}
		}
	}

	return layers
}

// namesIn are the names the arguments give, each alone or in a list.
func namesIn(args []Arg) []string {
	var names []string

	for _, arg := range args {
		switch value := arg.Value.(type) {
		case string:
			names = append(names, strings.Trim(value, `\`))
		case []any:
			for _, item := range value {
				if name, named := item.(string); named {
					names = append(names, strings.Trim(name, `\`))
				}
			}
		}
	}

	return names
}
