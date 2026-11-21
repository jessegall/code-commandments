package php_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/shop"
	"github.com/jessegall/code-commandments/prose"
)

func TestCommentTextReadsAsPhpReadsIt(t *testing.T) {
	for _, php := range shop.PhpProse(t) {
		got := struct {
			history, strawman, inline bool
			words, references         []string
			paragraphs                int
		}{prose.History(php.Text), prose.Strawman(php.Text), phpInline(php.Text), prose.Words(php.Text), phpReferences(php.Text), phpParagraphs(php.Text)}
		if got.history != php.History {
			t.Errorf("history %v, PHP %v: %q", got.history, php.History, php.Text)
		}
		if got.strawman != php.Strawman {
			t.Errorf("strawman %v, PHP %v: %q", got.strawman, php.Strawman, php.Text)
		}
		if got.inline != php.Inline {
			t.Errorf("inline %v, PHP %v: %q", got.inline, php.Inline, php.Text)
		}
		if !slices.Equal(got.words, php.Words) {
			t.Errorf("words %v, PHP %v: %q", got.words, php.Words, php.Text)
		}
		if !slices.Equal(got.references, php.References) && len(got.references)+len(php.References) > 0 {
			t.Errorf("references %v, PHP %v: %q", got.references, php.References, php.Text)
		}
		if got.paragraphs != php.Paragraphs {
			t.Errorf("paragraphs %d, PHP %d: %q", got.paragraphs, php.Paragraphs, php.Text)
		}
	}
}

func phpInline(text string) bool         { return php.DocblockIsInline(text) }
func phpReferences(text string) []string { return php.DocReferences(text) }
func phpParagraphs(text string) int      { return php.DocParagraphs(text) }

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
