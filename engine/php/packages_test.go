package php_test

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/concurrent"
	"github.com/jessegall/code-commandments/engine/php/phptypes"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestTheSmallPackageDecoratorsAnswerAsPhpDoes(t *testing.T) {
	shop.Parity(t, "smallpackages", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		switch ask {
		case "extendsConcurrent":
			return concurrent.Node{}.Decorate(node).ExtendsConcurrent()
		case "declaresNullableOption":
			return phptypes.Node{}.Decorate(node).DeclaresNullableOption()
		case "isUnwrapOrNull":
			return phptypes.Node{}.Decorate(node).IsUnwrapOrNull()
		case "isOption":
			return phptypes.IsOption(node.Name())
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}
