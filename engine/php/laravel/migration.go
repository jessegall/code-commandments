package laravel

import (
	"bytes"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

const Migration = `Illuminate\Database\Migrations\Migration`

func init() {
	engine.Freezes(contract.PHP, declaresMigration, func(_ string, source []byte) bool {
		return bytes.Contains(source, []byte("Migration"))
	})
}

func declaresMigration(file *engine.File) bool {
	for _, node := range file.File.Nodes() {
		if node.Kind != "Stmt_Class" {
			continue
		}
		for _, child := range node.Children {
			if child.Field == "extends" && strings.TrimLeft(child.Name, `\`) == Migration {
				return true
			}
		}
	}

	return false
}
