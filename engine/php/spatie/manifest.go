package spatie

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/walk"
)

const TransformerManifest = "typescript-transformer-manifest.json"

func TransformerOutputsBeside(codebase *engine.Codebase) []string {
	var outputs []string
	seen := map[string]bool{}

	for _, file := range codebase.Files() {
		if language := file.Language(); language != contract.TypeScript && language != contract.Vue {
			continue
		}

		for folder := filepath.Dir(file.Path); !seen[folder]; folder = filepath.Dir(folder) {
			seen[folder] = true

			if walk.Exists(filepath.Join(folder, TransformerManifest)) {
				outputs = append(outputs, folder)

				break
			}

			if parent := filepath.Dir(folder); parent == folder {
				break
			}
		}
	}

	return outputs
}
