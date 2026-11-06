package backend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RestatedCommentDetector finds a line comment inside a function that only says again what its code says.
type RestatedCommentDetector struct{}

func init() { detectors.Register(catalog.PHP, RestatedCommentDetector{}) }

const minWords = 2

func (RestatedCommentDetector) Sin() sins.Sin { return backendsins.RestatedComment{} }

func (RestatedCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.HasLineComment)).
		Where(engine.As(func(n php.Node) bool { return n.EnclosingFunctionLike().Exists() })).
		Reject(engine.As(php.Node.IsFunctionDeclaration)).
		Reject(engine.As(php.Node.IsArrayItem)).
		Reject(engine.As(php.Node.HasCommentedOutCode)).
		Where(engine.As(restatesItsCode)).
		Get()
}

func restatesItsCode(node php.Node) bool {
	var texts []string
	for _, comment := range node.LineComments() {
		texts = append(texts, comment.Text)
	}
	comment := slices.Compact(slices.Sorted(slices.Values(prose.Words(strings.Join(texts, " ")))))
	if len(comment) < minWords {
		return false
	}
	code := node.CodeWords()

	return !slices.ContainsFunc(comment, func(word string) bool { return !slices.Contains(code, word) })
}
