package spatie

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
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

			if _, err := os.Stat(filepath.Join(folder, TransformerManifest)); err == nil {
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
