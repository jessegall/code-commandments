package contract

// Lexicon is how a language writes its comments and its quoted text: enough to tell a comment from text that only
// looks like one inside a string.
type Lexicon struct {
	LineComments []string
	NotComments  []string
	BlockComment [2]string
	Quotes       []Quote
	Regions      []Region
}

// Quote is one form of quoted text: how it opens and closes, whether a backslash escapes the next byte, the pair that
// opens code inside it (a template's `${ }`), and whether it is a heredoc closed by the word it opens with.
type Quote struct {
	Open    string
	Close   string
	Escapes bool
	Code    [2]string
	Heredoc bool
}

// Region is a part of a file written in another language, such as a Vue component's script: where it opens and
// closes, and the language between.
type Region struct {
	Open     string
	Close    string
	Language Language
}

func LexiconOf(language Language) Lexicon {
	return described[language].lexicon
}
