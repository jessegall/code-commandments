package backend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/prose"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RestatedCommentDetector finds a line comment inside a function that only says again what its code says.
type RestatedCommentDetector struct{}

func init() { detectors.Register(catalog.Backend, RestatedCommentDetector{}) }

// minWords is how many distinct words a comment needs before it can restate anything.
const minWords = 2

// Sin is the sin the detector finds.
func (RestatedCommentDetector) Sin() sins.Sin { return backendsins.RestatedComment{} }

// Find is every statement in a function whose line comment uses only words its own code says.
func (RestatedCommentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
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
