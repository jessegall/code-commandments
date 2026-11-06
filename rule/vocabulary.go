package rule

import (
	"cmp"
	"embed"
	"encoding/json"
	"fmt"
	"io/fs"
	"path"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

const VocabularyFolder = "vocabulary"

//go:embed vocabulary/*.json
var vocabularies embed.FS

const DeclarationsFolder = "contract/declares"

var VocabularySources = []string{
	"contract/samples/php.jsonl",
	"contract/samples/python.jsonl",
	"contract/samples/csharp.jsonl",
	"contract/samples/typescript.jsonl",
	"contract/samples/vue.jsonl",
	"engine/php/testdata/shop.jsonl.gz",
	"engine/php/testdata/shop-frontend.jsonl.gz",
}

// Construct is one node shape a language's bridge can emit: the kind as the bridge writes it, and every fact that kind is seen carrying.
type Construct struct {
	Kind      string   `json:"kind"`
	Spells    string   `json:"spells"`
	Roles     []string `json:"roles"`
	Is        []string `json:"is,omitempty"`
	Fields    []string `json:"fields,omitempty"`
	Operators []string `json:"operators,omitempty"`
	Modifiers []string `json:"modifiers,omitempty"`
	Flags     []string `json:"flags,omitempty"`
	Vouched   []string `json:"vouched"`
	Example   string   `json:"example,omitempty"`
}

func (c Construct) Observed() bool {
	return slices.Contains(c.Vouched, "observed")
}

func (c Construct) Declared() bool {
	return slices.Contains(c.Vouched, "declared")
}

// Vocabulary is everything one language's bridge is seen emitting: the value space of the kind, field, operator, flag and modifier checks for that language.
type Vocabulary struct {
	Language       contract.Language   `json:"language"`
	Bridges        []string            `json:"bridges"`
	Sources        []string            `json:"sources"`
	Parser         string              `json:"parser,omitempty"`
	Declares       string              `json:"declares,omitempty"`
	DeclaredFields []string            `json:"fields,omitempty"`
	DeclaresKeys   []string            `json:"declaresKeys,omitempty"`
	Collided       map[string][]string `json:"collided,omitempty"`
	Constructs     []Construct         `json:"constructs"`
}

var read sync.Map

func VocabularyOf(language contract.Language) (Vocabulary, bool) {
	if held, ok := read.Load(language); ok {
		vocabulary, held := held.(Vocabulary)

		return vocabulary, held
	}

	vocabulary, held := parsed(language)
	if held {
		read.Store(language, vocabulary)

		return vocabulary, true
	}

	read.Store(language, nil)

	return Vocabulary{}, false
}

func parsed(language contract.Language) (Vocabulary, bool) {
	read, err := vocabularies.ReadFile(path.Join(VocabularyFolder, string(language)+".json"))
	if err != nil {
		return Vocabulary{}, false
	}

	var vocabulary Vocabulary
	if err := json.Unmarshal(read, &vocabulary); err != nil {
		return Vocabulary{}, false
	}

	return vocabulary, true
}

func Vocabularies() (map[contract.Language]Vocabulary, error) {
	found := map[contract.Language]Vocabulary{}
	files, err := fs.ReadDir(vocabularies, VocabularyFolder)
	if err != nil {
		return nil, err
	}

	for _, file := range files {
		language := contract.Language(strings.TrimSuffix(file.Name(), ".json"))
		vocabulary, held := VocabularyOf(language)
		if !held {
			return nil, fmt.Errorf("%s is no vocabulary", file.Name())
		}
		found[language] = vocabulary
	}

	return found, nil
}

func (v Vocabulary) DeclaresKey(key string) bool {
	return slices.Contains(v.DeclaresKeys, key)
}

func (v Vocabulary) Kinds() []string {
	kinds := make([]string, 0, len(v.Constructs))
	for _, construct := range v.Constructs {
		kinds = append(kinds, construct.Kind)
	}

	return kinds
}

func (v Vocabulary) Fields() []string {
	fields := v.gathered(func(construct Construct) []string { return construct.Fields })
	for _, field := range v.DeclaredFields {
		if !slices.Contains(fields, field) {
			fields = append(fields, field)
		}
	}
	slices.Sort(fields)

	return fields
}

func (v Vocabulary) Operators() []string {
	return v.gathered(func(construct Construct) []string { return construct.Operators })
}

func (v Vocabulary) Modifiers() []string {
	return v.gathered(func(construct Construct) []string { return construct.Modifiers })
}

func (v Vocabulary) Flags() []string {
	return v.gathered(func(construct Construct) []string { return construct.Flags })
}

var padding = struct {
	prefixes []string
	suffixes []string
}{
	prefixes: []string{"Expr_", "Stmt_", "Scalar_"},
	suffixes: []string{"Expression", "Statement", "Declaration", "Syntax"},
}

func Spelling(kind string) string {
	bare := kind
	for _, prefix := range padding.prefixes {
		bare = strings.TrimPrefix(bare, prefix)
	}

	for _, suffix := range padding.suffixes {
		if trimmed := strings.TrimSuffix(bare, suffix); trimmed != "" && trimmed != bare {
			bare = trimmed

			break
		}
	}

	return kebab(bare)
}

func kebab(name string) string {
	var written strings.Builder
	for at, letter := range name {
		switch {
		case letter == '_':
			written.WriteByte('-')
		case letter >= 'A' && letter <= 'Z':
			if at > 0 && !strings.HasSuffix(written.String(), "-") {
				written.WriteByte('-')
			}
			written.WriteRune(letter + ('a' - 'A'))
		default:
			written.WriteRune(letter)
		}
	}

	return strings.Trim(written.String(), "-")
}

func (v Vocabulary) Spelled(spelling string) (Construct, bool) {
	for _, construct := range v.Constructs {
		if construct.Spells == spelling {
			return construct, true
		}
	}

	return Construct{}, false
}

func (v Vocabulary) Spellings() []string {
	spellings := make([]string, 0, len(v.Constructs))
	for _, construct := range v.Constructs {
		if construct.Spells != "" {
			spellings = append(spellings, construct.Spells)
		}
	}

	return spellings
}

func (v Vocabulary) Collisions() map[string][]string {
	spelled := map[string][]string{}
	for _, construct := range v.Constructs {
		spelled[Spelling(construct.Kind)] = append(spelled[Spelling(construct.Kind)], construct.Kind)
	}

	for spelling, kinds := range spelled {
		if len(kinds) < 2 {
			delete(spelled, spelling)
		}
	}

	return spelled
}

func (v Vocabulary) Construct(kind string) (Construct, bool) {
	at, found := slices.BinarySearchFunc(v.Constructs, kind, func(construct Construct, kind string) int {
		return cmp.Compare(construct.Kind, kind)
	})
	if !found {
		return Construct{}, false
	}

	return v.Constructs[at], true
}

func (v Vocabulary) gathered(read func(Construct) []string) []string {
	var gathered []string
	for _, construct := range v.Constructs {
		for _, value := range read(construct) {
			if !slices.Contains(gathered, value) {
				gathered = append(gathered, value)
			}
		}
	}
	slices.Sort(gathered)

	return gathered
}
