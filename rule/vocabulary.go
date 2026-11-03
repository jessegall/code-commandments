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

// VocabularyFolder is where the committed vocabularies live, one file per language.
const VocabularyFolder = "vocabulary"

//go:embed vocabulary/*.json
var vocabularies embed.FS

// VocabularySources are the recorded streams the census reads, each by its path from the repository root: the
// streams the contract keeps as its samples, and the whole backend and frontend fixtures as the PHP and frontend
// bridges once wrote them. The generator and the test that holds the vocabularies to them read this one list, so
// a stream added to the census is a stream the test checks.
var VocabularySources = []string{
	"contract/samples/php.jsonl",
	"contract/samples/python.jsonl",
	"contract/samples/csharp.jsonl",
	"contract/samples/typescript.jsonl",
	"contract/samples/vue.jsonl",
	"engine/php/testdata/shop.jsonl.gz",
	"engine/php/testdata/shop-frontend.jsonl.gz",
}

// Construct is one node shape a language's bridge can emit: the kind as the bridge writes it, and every fact that
// kind is seen carrying. It is what a rule author cannot discover today — a kind, a field, an operator, a flag or
// a modifier is the language's own word, and a hand-written list of them goes stale without anybody noticing.
type Construct struct {
	// Kind is the kind exactly as the bridge writes it, such as `Expr_StaticCall`.
	Kind string `json:"kind"`
	// Spells is the kind as a rule may write it instead: derived mechanically from the kind and never curated, so
	// `Expr_StaticCall` is `static-call` and `InvocationExpression` is `invocation`. It is EMPTY where another
	// kind of the same language derives the same spelling, since one word that means two constructs is no name;
	// those kinds are named in the vocabulary's Collided and a rule writes them as the language spells them.
	Spells string `json:"spells"`
	// Roles are the roles it is seen in, which is more than one where a kind is written in several places: an
	// identifier is `other` in a name and `type` inside a written type.
	Roles []string `json:"roles"`
	// Is are the neutral kinds it answers.
	Is []string `json:"is,omitempty"`
	// Fields are the slots its children fill.
	Fields []string `json:"fields,omitempty"`
	// Operators are the operator tokens it carries.
	Operators []string `json:"operators,omitempty"`
	// Modifiers are the modifier keywords it is written with.
	Modifiers []string `json:"modifiers,omitempty"`
	// Flags are the syntax facts it carries that are no keyword.
	Flags []string `json:"flags,omitempty"`
	// Vouched says who answers for the row: `observed` means a recorded stream really carries it.
	Vouched []string `json:"vouched"`
	// Example is where the census first met it, as the stream names the place: `path:line`.
	Example string `json:"example,omitempty"`
}

// Vocabulary is everything one language's bridge is seen emitting: the value space of the kind, field, operator,
// flag and modifier checks for that language. It is generated, never curated, and a test holds it to the recorded
// streams so a bridge that starts emitting something new fails the suite rather than going unlisted.
type Vocabulary struct {
	Language contract.Language `json:"language"`
	// Bridges are the bridges the rows were read from, each by name and version, so a row can be dated.
	Bridges []string `json:"bridges"`
	// Sources are the recorded streams the census read, so what the rows do and do not cover is readable.
	Sources []string `json:"sources"`
	// Collided are the spellings more than one kind derives, each with the kinds that derive it. Those kinds carry
	// no spelling at all: a rule writing one word that means two constructs could not be read back, and choosing
	// a winner would be the curated table this is generated to avoid.
	Collided map[string][]string `json:"collided,omitempty"`
	// Constructs are the kinds, in the order a reader looks them up: by kind.
	Constructs []Construct `json:"constructs"`
}

// read is each language's vocabulary once it has been read, since a check asks for one per node and parsing the
// file each time would read a hundred kilobytes of JSON for every node of a scan.
var read sync.Map

// VocabularyOf is the committed vocabulary of the language, read once; no vocabulary for one nothing was recorded
// for.
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

// parsed is the language's committed vocabulary, read from the embedded file.
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

// Vocabularies are every committed vocabulary, by language.
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

// Kinds are every kind the language is seen emitting.
func (v Vocabulary) Kinds() []string {
	kinds := make([]string, 0, len(v.Constructs))
	for _, construct := range v.Constructs {
		kinds = append(kinds, construct.Kind)
	}

	return kinds
}

// Fields are every slot a child fills in the language, each once.
func (v Vocabulary) Fields() []string {
	return v.gathered(func(construct Construct) []string { return construct.Fields })
}

// Operators are every operator token the language carries, each once.
func (v Vocabulary) Operators() []string {
	return v.gathered(func(construct Construct) []string { return construct.Operators })
}

// Modifiers are every modifier keyword the language writes, each once.
func (v Vocabulary) Modifiers() []string {
	return v.gathered(func(construct Construct) []string { return construct.Modifiers })
}

// Flags are every syntax flag the language carries, each once.
func (v Vocabulary) Flags() []string {
	return v.gathered(func(construct Construct) []string { return construct.Flags })
}

// padding is what a language pads a kind with to say what sort of thing it is, which the neutral `role` and `is`
// already say: PHP's prefixes, and the suffix C# and TypeScript give every node of their trees.
var padding = struct {
	prefixes []string
	suffixes []string
}{
	prefixes: []string{"Expr_", "Stmt_", "Scalar_"},
	suffixes: []string{"Expression", "Statement", "Declaration", "Syntax"},
}

// Spelling is the kind as a rule may write it: the padding dropped and the rest kebab-cased. It is mechanical, so
// a bridge that adds a kind gets a spelling with nobody deciding one, and two kinds that spell the same are a
// collision the generator refuses rather than a winner it picks.
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

// kebab is a name written as words joined by dashes: each run of capitals opens a word, and an underscore is a
// word break the language wrote itself.
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

// Spelled is the row whose kind the spelling names, so a rule may write either; no row for a spelling nothing
// answers.
func (v Vocabulary) Spelled(spelling string) (Construct, bool) {
	for _, construct := range v.Constructs {
		if construct.Spells == spelling {
			return construct, true
		}
	}

	return Construct{}, false
}

// Spellings are every spelling the language answers to, each once.
func (v Vocabulary) Spellings() []string {
	spellings := make([]string, 0, len(v.Constructs))
	for _, construct := range v.Constructs {
		if construct.Spells != "" {
			spellings = append(spellings, construct.Spells)
		}
	}

	return spellings
}

// Collisions are the spellings more than one kind of the language derives, by spelling, read from the kinds
// themselves rather than from what was written: it is what the generator clears the colliding spellings by.
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

// Construct is the row of that kind; no row for a kind nothing recorded.
func (v Vocabulary) Construct(kind string) (Construct, bool) {
	at, found := slices.BinarySearchFunc(v.Constructs, kind, func(construct Construct, kind string) int {
		return cmp.Compare(construct.Kind, kind)
	})
	if !found {
		return Construct{}, false
	}

	return v.Constructs[at], true
}

// gathered is one fact of every construct, sorted and said once.
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
