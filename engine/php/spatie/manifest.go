package spatie

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// TransformerManifest is the file the TypeScript transformer writes beside the types it generates, naming each and
// its hash, so it knows what it wrote.
const TransformerManifest = "typescript-transformer-manifest.json"

// TransformerOutputsBeside are the folders the TypeScript transformer has written into, found from the frontend files
// themselves: the nearest folder above one that holds the transformer's manifest. They hold wherever the transformer
// is configured, in a file the scan never reads or in no file at all.
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
