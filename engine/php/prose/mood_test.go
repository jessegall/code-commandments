package prose_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/php/prose"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestAMethodNameReadsInTheMoodPhpReadsIt(t *testing.T) {
	for _, php := range shop.PhpMoods(t) {
		if got := prose.ReadsAsQuestion(php.Name); got != php.Question {
			t.Errorf("%s reads as a question %v, PHP %v", php.Name, got, php.Question)
		}
		if got := prose.IsThirdPerson(php.Name); got != php.ThirdPerson {
			t.Errorf("%s is third person %v, PHP %v", php.Name, got, php.ThirdPerson)
		}
		if got := prose.IsRelationalCompound(php.Name); got != php.Relational {
			t.Errorf("%s is a relational compound %v, PHP %v", php.Name, got, php.Relational)
		}
	}
}
