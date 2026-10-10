package laravel

import (
	"bytes"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Migration is the class every Laravel migration extends.
const Migration = `Illuminate\Database\Migrations\Migration`

func init() {
	engine.Freezes(contract.PHP, declaresMigration, func(_ string, source []byte) bool {
		return bytes.Contains(source, []byte("Migration"))
	})
}

// declaresMigration says whether the file declares a migration: a class, named or the anonymous one Laravel writes,
// that extends Migration. A migration is run once and stands as it ran, so it is read for every rule but rewritten by
// none, as a file marked frozen is.
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
