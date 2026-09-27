package fixture

import (
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/skill/render"
)

// Marked is one marked piece of a fixture as a worked example shows it: the code, the file it is in, the lines it
// spans there, the comment line that names where it came from (none when empty), and what a Bad and a Good must
// share to be one before and after — the type for PHP, else the file.
type Marked struct {
	File     string
	Source   string
	Heading  string
	Scenario string
	First    int
	Last     int
}

// shows says whether the source shows the line of the file.
func (m Marked) shows(file string, line int) bool {
	return m.File == file && m.First <= line && line <= m.Last
}

func (m Marked) sharesScenarioWith(other Marked) bool {
	return m.scenario() == other.scenario()
}

func (m Marked) sharesFileWith(other Marked) bool {
	return m.File == other.File
}

func (m Marked) scenario() string {
	if m.Scenario == "" {
		return m.File
	}

	return m.Scenario
}

// sources are marked sources by the name each marker gives.
type sources map[string][]Marked

// CarveDeclarations is the worked examples a PHP fixture carves: each detector's attribute-marked declarations, the
// sinful against its fixed repair, else its righteous look-alike, with each half's docblock lifted into comments
// unless the skill teaches docblocks. Files marked @example tell a rule's example instead.
func CarveDeclarations(root string, codebase *engine.Codebase, proven []detectors.Detector) render.Examples {
	sinful, fixed, righteous := declarationSources(codebase, Sinful), declarationSources(codebase, Fixed), declarationSources(codebase, Righteous)
	groups := recurringGroups(codebase, proven)
	examples := render.Examples{}
	for _, detector := range proven {
		sin := detector.Sin().Definition()
		keys := []string{catalog.Name(detector.Sin()), catalog.Name(detector), sin.Slug(), sin.Name}
		bad := forKeys(sinful, keys)
		lift := !sin.Skill.Definition().ExamplesKeepDocblocks
		resolution := resolutionOf(bad, forKeys(fixed, keys))
		good := resolution
		if len(good) == 0 {
			good = forKeys(righteous, keys)
		}
		example := pair(bad, good, source.PHP)
		if lift {
			example = lifted(example)
		}
		if len(resolution) > 1 {
			example.Good = pointer(group(resolution, lift))
		}
		if _, recurs := detector.(detectors.Grouped); recurs && len(bad) > 0 {
			anchor, anchored := anchorOf(bad, good)
			if recurring := recurringOf(bad, anchor, anchored, groups[render.Key(detector)]); len(recurring) > 1 {
				example.Bad = pointer(group(recurring, lift))
			}
		}
		examples[render.Key(detector)] = []render.Example{example}
	}

	return exampleFiles(root).over(examples, proven)
}

// CarveMarks is the worked examples a fixture marked in comments carves: each detector's @sin sources against its
// @fixed repair, else its @righteous look-alike, one example per language the rule is marked in; fallback is the
// language of an example no marked file names. Files marked @example tell a rule's example instead.
func CarveMarks(root string, codebase *engine.Codebase, proven []detectors.Detector, fallback source.Language) render.Examples {
	sinful, fixed, righteous := commentSources(codebase, Sinful), commentSources(codebase, Fixed), commentSources(codebase, Righteous)
	groups := recurringGroups(codebase, proven)
	examples := render.Examples{}
	for _, detector := range proven {
		keys := []string{catalog.Name(detector.Sin()), catalog.Name(detector)}
		examples[render.Key(detector)] = perLanguage(detector, forKeys(sinful, keys), forKeys(fixed, keys), forKeys(righteous, keys), fallback, groups[render.Key(detector)])
	}

	return exampleFiles(root).over(examples, proven)
}

// perLanguage is one example per language the rule is marked in: a reader working in .ts needs the .ts one.
func perLanguage(detector detectors.Detector, bad, fixed, righteous []Marked, fallback source.Language, groups map[string]map[int]string) []render.Example {
	badByLanguage, languages := byLanguage(bad)
	if len(languages) == 0 {
		return []render.Example{markedExample(detector, bad, fixed, righteous, fallback, bad, groups)}
	}
	fixedByLanguage, _ := byLanguage(fixed)
	righteousByLanguage, _ := byLanguage(righteous)
	var examples []render.Example
	for _, language := range languages {
		examples = append(examples, markedExample(detector, badByLanguage[language], fixedByLanguage[language], righteousByLanguage[language], language, bad, groups))
	}

	return examples
}

// markedExample is one language's example: the pair, a resolution spanning blocks shown whole, and a recurring
// rule's Bad shown as the group the example is anchored on. everyBad is the rule's sinful sources in every
// language, since a recurring group may cross from a module into a template.
func markedExample(detector detectors.Detector, bad, fixed, righteous []Marked, language source.Language, everyBad []Marked, groups map[string]map[int]string) render.Example {
	resolution := resolutionOf(bad, fixed)
	good := resolution
	if len(good) == 0 {
		good = righteous
	}
	example := pair(bad, good, language)
	if len(resolution) > 1 {
		example.Good = pointer(group(resolution, false))
	}
	anchor, anchored := anchorOf(bad, good)
	if _, recurs := detector.(detectors.Grouped); !recurs || !anchored {
		return example
	}
	if recurring := recurringOf(everyBad, anchor, true, groups); len(recurring) > 1 {
		example.Bad = pointer(group(recurring, false))
	}

	return example
}

// byLanguage is the sources grouped by the language of their file, and the languages in the order first met.
func byLanguage(marked []Marked) (map[source.Language][]Marked, []source.Language) {
	grouped := map[source.Language][]Marked{}
	var languages []source.Language
	for _, each := range marked {
		language := source.OfFile(each.File)
		if _, met := grouped[language]; !met {
			languages = append(languages, language)
		}
		grouped[language] = append(grouped[language], each)
	}

	return grouped, languages
}

// recurringGroups is every recurring rule's findings in the fixture, by file and line, with the group each recurs
// in: what tells a recurrence example which of the marked groups its Good answers.
func recurringGroups(codebase *engine.Codebase, proven []detectors.Detector) map[string]map[string]map[int]string {
	groups := map[string]map[string]map[int]string{}
	for _, detector := range proven {
		grouped, recurs := detector.(detectors.Grouped)
		if !recurs {
			continue
		}
		byFile := map[string]map[int]string{}
		for _, finding := range detector.Find(codebase) {
			if key, ok := grouped.GroupKey(finding); ok {
				if byFile[finding.File()] == nil {
					byFile[finding.File()] = map[int]string{}
				}
				byFile[finding.File()][finding.Line()] = key
			}
		}
		groups[render.Key(detector)] = byFile
	}

	return groups
}

// declarationSources is every PHP declaration an attribute of the tag marks, by the name the attribute gives: the
// tightest declaration — the function it sits in, else the type — sliced from the first comment above it, without
// the marker lines, dedented.
func declarationSources(codebase *engine.Codebase, tag Tag) sources {
	marked := sources{}
	for _, attribute := range codebase.WhereKind("Attribute").Get() {
		if attributeTags[ShortName(attribute.Child("name").Name())] != tag {
			continue
		}
		named := markedName(attribute.Child("args").Child("value"))
		if named == "" {
			continue
		}
		shown, heading, scenario := attribute.EnclosingFunction(), "", attribute.File()
		if declaration := attribute.EnclosingType(); declaration.Exists() {
			scenario = declaration.Identity()
			if shown.Exists() {
				heading = "// in " + declaration.Identity()
			}
		}
		if !shown.Exists() {
			shown = attribute.EnclosingType()
		}
		if !shown.Exists() {
			shown = attribute.Parent()
		}
		text, first, last := declarationText(shown)
		marked[named] = append(marked[named], Marked{File: attribute.File(), Source: text, Heading: heading, Scenario: scenario, First: first, Last: last})
	}

	return marked
}

// declarationText is the declaration from the first comment above it, less its marker lines, dedented; with the
// lines the declaration itself opens and closes on.
func declarationText(declaration engine.Match) (string, int, int) {
	span, err := declaration.Span()
	if err != nil {
		return "", 0, 0
	}
	first, last := lineAt(span.Source, span.Start), lineAt(span.Source, max(span.Start, span.End-1))
	from := first
	for _, comment := range declaration.Comments() {
		if comment.Span.Start < span.Start {
			from = min(from, comment.Span.Line)
		}
	}
	lines := strings.Split(string(span.Source), "\n")
	var kept []string
	for _, line := range lines[from-1 : min(last, len(lines))] {
		if !strings.Contains(line, "#[Sinful(") && !strings.Contains(line, "#[Righteous(") && !strings.Contains(line, "#[Fixed(") {
			kept = append(kept, line)
		}
	}

	return dedent(kept), first, last
}

// markerWord is a marker a comment's words hold anywhere: @sin Name.
var markerWord = regexp.MustCompile(`@(\w+)\s+(\w+)`)

// commentSources is every source a comment of the tag marks, by the name it gives: a template element in a Vue
// file, a declaration or statement in a module — each .ts, .py and .cs file and each Vue script block — with a
// template's elements first.
func commentSources(codebase *engine.Codebase, tag Tag) sources {
	marked := sources{}
	var modules []moduleSpan
	for _, file := range codebase.Files() {
		if source.OfFile(file.Path) != source.Vue {
			modules = append(modules, moduleSpan{file: file, root: file.Match(0)})
			continue
		}
		for _, block := range file.Match(0).Children() {
			if block.Name() == "template" {
				elementSources(file, block, tag, marked)
			}
		}
	}
	for _, file := range codebase.Files() {
		if source.OfFile(file.Path) != source.Vue {
			continue
		}
		for _, block := range file.Match(0).Children() {
			if block.Name() == "script" {
				modules = append(modules, moduleSpan{file: file, root: block})
			}
		}
	}
	sort.SliceStable(modules, func(i, j int) bool {
		return source.OfFile(modules[i].file.Path) != source.Vue && source.OfFile(modules[j].file.Path) == source.Vue
	})
	for _, module := range modules {
		module.collect(tag, marked)
	}

	return marked
}

// elementSources files every template element a marker comment of the tag leads: the first element after the
// comment among the children of the node holding it, shown from the indent of its line, dedented.
func elementSources(file *engine.File, template engine.Match, tag Tag, marked sources) {
	text, err := file.Source()
	if err != nil {
		return
	}
	for _, comment := range file.File.Comments {
		if comment.Span.Start < template.Node().Span.Start || comment.Span.End > template.Node().Span.End {
			continue
		}
		var names []string
		for _, found := range markerWord.FindAllStringSubmatch(comment.Text, -1) {
			if Tag(found[1]) == tag {
				names = append(names, found[2])
			}
		}
		element, found := elementAfter(holding(template, comment.Span.Start, comment.Span.End), comment.Span.End)
		if len(names) == 0 || !found {
			continue
		}
		start, end := element.Node().Span.Start, element.Node().Span.End
		lineStart := strings.LastIndexByte(string(text[:start]), '\n') + 1
		shown := dedent(strings.Split(string(text[lineStart:end]), "\n"))
		for _, name := range names {
			marked[name] = append(marked[name], Marked{
				File:    file.Path,
				Source:  shown,
				Heading: source.Vue.Comment("in " + filepath.Base(file.Path)),
				First:   lineAt(text, start),
				Last:    lineAt(text, end),
			})
		}
	}
}

// holding is the innermost node under node whose span holds [start, end).
func holding(node engine.Match, start, end int) engine.Match {
	for _, child := range node.Children() {
		if span := child.Node().Span; span.Start <= start && end <= span.End {
			return holding(child, start, end)
		}
	}

	return node
}

// elementAfter is the first element among the node's children that starts at or after the offset.
func elementAfter(node engine.Match, offset int) (engine.Match, bool) {
	for _, child := range node.Children() {
		if child.Kind() == "Element" && child.Node().Span.Start >= offset {
			return child, true
		}
	}

	return engine.Match{}, false
}

// moduleSpan is a module's tree: a whole file, or a Vue file's script block.
type moduleSpan struct {
	file *engine.File
	root engine.Match
}

// collect files every node the module's markers of the tag lead: the outermost node a marked line opens, shown as the
// function it sits in, else the type, else itself, with the comments directly above it. A node shown twice under a
// name is filed once.
func (m moduleSpan) collect(tag Tag, marked sources) {
	text, err := m.file.Source()
	if err != nil {
		return
	}
	language := source.OfFile(m.file.Path)
	if language == source.Vue {
		language = source.TypeScript
	}
	lines := strings.Split(string(text), "\n")
	nodes := append([]engine.Match{m.root}, m.root.Descendants()...)
	var functions, types [][2]int
	for _, node := range nodes {
		span := [2]int{node.Node().Span.Start, node.Node().Span.End}
		if node.RunsABody() {
			functions = append(functions, span)
		}
		if node.Is(engine.TypeDeclaration) {
			types = append(types, span)
		}
	}
	shown := map[int]bool{}
	filed := map[string]bool{}
	for _, node := range nodes {
		start, end := node.Node().Span.Start, node.Node().Span.End
		line := lineAt(text, start)
		if shown[line] {
			continue
		}
		shown[line] = true
		names := markersAbove(lines, line, tag)
		if len(names) == 0 {
			continue
		}
		span, found := innermost(functions, start, end)
		if !found {
			span, found = innermost(types, start, end)
		}
		if !found {
			span = [2]int{start, end}
		}
		opens := lineAt(text, span[0])
		indent := leadingSpace(lines[opens-1])
		shownLines := append(commentsAbove(lines, opens, language), strings.Split(indent+string(text[span[0]:span[1]]), "\n")...)
		var kept []string
		for _, each := range shownLines {
			if !isMarkerLine(each, language) {
				kept = append(kept, each)
			}
		}
		for _, name := range names {
			key := name + "\x00" + m.file.Path + ":" + strconv.Itoa(span[0])
			if filed[key] {
				continue
			}
			filed[key] = true
			marked[name] = append(marked[name], Marked{
				File:    m.file.Path,
				Source:  dedent(kept),
				Heading: language.Comment("in " + filepath.Base(m.file.Path)),
				First:   lineAt(text, span[0]),
				Last:    lineAt(text, max(span[0], span[1]-1)),
			})
		}
	}
}

// innermost is the innermost span holding [start, end).
func innermost(spans [][2]int, start, end int) ([2]int, bool) {
	var inner [2]int
	found := false
	for _, span := range spans {
		if span[0] <= start && end <= span[1] && (!found || span[0] >= inner[0]) {
			inner, found = span, true
		}
	}

	return inner, found
}

// commentsAbove is the run of comment lines directly above line at (1-based), in order.
func commentsAbove(lines []string, at int, language source.Language) []string {
	var comments []string
	for n := at - 1; n >= 1 && strings.TrimSpace(lines[n-1]) != "" && language.IsCommentLine(lines[n-1]); n-- {
		comments = append([]string{lines[n-1]}, comments...)
	}

	return comments
}

// markersAbove is the names in the run of marker comments of the tag above line at (1-based): blank lines and
// markers of other tags are stepped over, anything else ends the run.
func markersAbove(lines []string, at int, tag Tag) []string {
	var names []string
	for n := min(at-1, len(lines)); n >= 1; n-- {
		text := strings.TrimSpace(lines[n-1])
		if text == "" {
			continue
		}
		found := markerWord.FindStringSubmatch(text)
		if found == nil {
			break
		}
		if Tag(found[1]) == tag {
			names = append(names, found[2])
		}
	}

	return names
}

// isMarkerLine says whether the line is a comment of the language holding a marker.
func isMarkerLine(line string, language source.Language) bool {
	return language.IsCommentLine(line) && markerWord.MatchString(strings.TrimSpace(line))
}

// forKeys is the first non-empty list of sources under the keys, in order.
func forKeys(marked sources, keys []string) []Marked {
	for _, key := range keys {
		if len(marked[key]) > 0 {
			return marked[key]
		}
	}

	return nil
}

// pair is the first bad and good of one scenario, so the before and after is one piece of code repaired.
func pair(bad, good []Marked, language source.Language) render.Example {
	example := render.Example{Language: language}
	resolution, resolves := counterpart(bad, good)
	if resolves {
		example.Good = pointer(resolution.Source)
	}
	if sinful, found := answered(bad, resolution, resolves); found {
		example.Bad = pointer(sinful.Source)
	}

	return example
}

// anchorOf is the sinful source the example is anchored on: the one the resolution answers, else the first.
func anchorOf(bad, good []Marked) (Marked, bool) {
	resolution, resolves := counterpart(bad, good)

	return answered(bad, resolution, resolves)
}

// recurringOf is the sources in the one recurring group the anchor belongs to, else all of them.
func recurringOf(marked []Marked, anchor Marked, anchored bool, groups map[string]map[int]string) []Marked {
	if !anchored {
		return marked
	}
	group, grouped := groupOf(anchor, groups)
	if !grouped {
		return marked
	}
	var recurring []Marked
	for _, each := range marked {
		if other, ok := groupOf(each, groups); ok && other == group {
			recurring = append(recurring, each)
		}
	}

	return recurring
}

// groupOf is the group of a finding inside the lines the source shows, the lowest line first.
func groupOf(marked Marked, groups map[string]map[int]string) (string, bool) {
	lines := make([]int, 0, len(groups[marked.File]))
	for line := range groups[marked.File] {
		lines = append(lines, line)
	}
	slices.Sort(lines)
	for _, line := range lines {
		if marked.shows(marked.File, line) {
			return groups[marked.File][line], true
		}
	}

	return "", false
}

// resolutionOf is the resolutions that together form one fix: the counterpart of the sinful code first, then every
// other resolution in its file, or in a file holding no sinful code of the rule.
func resolutionOf(bad, good []Marked) []Marked {
	resolution, resolves := counterpart(bad, good)
	if !resolves {
		return nil
	}
	fix := []Marked{resolution}
	for i, one := range good {
		if i == indexOf(good, resolution) {
			continue
		}
		shared := false
		for _, sinful := range bad {
			shared = shared || sinful.sharesFileWith(one)
		}
		if one.sharesFileWith(resolution) || !shared {
			fix = append(fix, one)
		}
	}

	return fix
}

// indexOf is the position of the source in the list, compared as PHP compares objects: the first equal one.
func indexOf(marked []Marked, one Marked) int {
	for i, each := range marked {
		if each == one {
			return i
		}
	}

	return -1
}

// sharing is what makes a bad and a good one before and after, strongest first: the scenario, then the file.
var sharing = []func(one, other Marked) bool{
	func(one, other Marked) bool { return one.sharesScenarioWith(other) },
	func(one, other Marked) bool { return one.sharesFileWith(other) },
}

// counterpart is the resolution that answers one of the bad, the first found scanning the sinful in order; else
// the first resolution.
func counterpart(bad, good []Marked) (Marked, bool) {
	for _, shared := range sharing {
		for _, one := range bad {
			for _, other := range good {
				if shared(one, other) {
					return other, true
				}
			}
		}
	}
	if len(good) > 0 {
		return good[0], true
	}

	return Marked{}, false
}

// answered is the sinful source the resolution repairs, else the first.
func answered(bad []Marked, resolution Marked, resolves bool) (Marked, bool) {
	if resolves {
		for _, shared := range sharing {
			for _, one := range bad {
				if shared(one, resolution) {
					return one, true
				}
			}
		}
	}
	if len(bad) > 0 {
		return bad[0], true
	}

	return Marked{}, false
}

// lifted is the example with each half's leading docblock turned into // lines above it, its tags dropped.
func lifted(example render.Example) render.Example {
	if example.Bad != nil {
		example.Bad = pointer(liftDocblock(*example.Bad))
	}
	if example.Good != nil {
		example.Good = pointer(liftDocblock(*example.Good))
	}

	return example
}

func liftDocblock(text string) string {
	lines := strings.Split(strings.TrimLeft(text, "\n"), "\n")
	if strings.TrimSpace(lines[0]) != "/**" {
		return text
	}
	var prose []string
	at := 1
	for ; at < len(lines); at++ {
		line := strings.TrimSpace(lines[at])
		if line == "*/" {
			at++
			break
		}
		line = strings.TrimSpace(strings.TrimLeft(line, "*"))
		if line != "" && !strings.HasPrefix(line, "@") {
			prose = append(prose, "// "+line)
		}
	}
	code := lines[min(at, len(lines)):]
	if len(prose) == 0 {
		return strings.Join(code, "\n")
	}

	return strings.Join(append(append(prose, ""), code...), "\n")
}

// whitespace is a run of whitespace, what two blocks are compared by.
var whitespace = regexp.MustCompile(`\s+`)

// group is several sources shown as one example, each headed by where it lives; a source another already shows
// whole is left out.
func group(marked []Marked, lift bool) string {
	flat := make([]string, len(marked))
	for i, each := range marked {
		flat[i] = flattened(each.Source)
	}
	var blocks []string
	for i, each := range marked {
		inside := false
		for _, other := range flat {
			inside = inside || other != flat[i] && strings.Contains(other, flat[i])
		}
		if inside {
			continue
		}
		text := each.Source
		if lift {
			text = liftDocblock(text)
		}
		if each.Heading != "" {
			text = each.Heading + "\n" + text
		}
		blocks = append(blocks, text)
	}

	return strings.Join(blocks, "\n\n")
}

func flattened(text string) string {
	return whitespace.ReplaceAllString(strings.TrimSpace(text), " ")
}

// dedent is the lines with their common leading indentation removed, blank lines not measured.
func dedent(lines []string) string {
	least := -1
	for _, line := range lines {
		if strings.TrimSpace(line) != "" {
			if indent := len(leadingSpace(line)); least < 0 || indent < least {
				least = indent
			}
		}
	}
	least = max(least, 0)
	out := make([]string, len(lines))
	for i, line := range lines {
		if len(line) >= least {
			out[i] = line[least:]
		}
	}

	return strings.Join(out, "\n")
}

func leadingSpace(line string) string {
	return line[:len(line)-len(strings.TrimLeft(line, " \t\n\r\x00\x0B"))]
}

// lineAt is the 1-based line the offset is on.
func lineAt(text []byte, offset int) int {
	return 1 + strings.Count(string(text[:min(offset, len(text))]), "\n")
}

func pointer(text string) *string {
	return &text
}


// exampleMarker is an @example line's words: the name, and which half the file tells.
var exampleMarker = regexp.MustCompile(`^@example\s+(\w+)\s+(bad|good)$`)

// markerOnly is a comment's words that are one fixture marker and nothing else.
var markerOnly = regexp.MustCompile(`^@(?:(?:sin|fixed|righteous)\s+\w+|example\s+\w+\s+(?:bad|good))$`)

// commentWords strips the delimiters that open and close a comment line.
var commentWords = regexp.MustCompile(`^\s*(?:\/\/|#|<!--|\/\*+|\*)\s*|\s*(?:-->|\*\/)\s*$`)

// fileScopeHost is what a PHP file with no class or function hangs its marker on.
const fileScopeHost = "static fn (): null => null;"

// examples are the fixture's @example files: each half's files, by the name each is marked with.
type examples struct {
	bad, good sources
}

// exampleFiles is the files under the fixture marked `@example Name bad|good`, in path order, each published whole.
func exampleFiles(root string) examples {
	found := examples{bad: sources{}, good: sources{}}
	files := source.Sources(root, source.Under(root, nil))
	sort.Strings(files)
	for _, file := range files {
		raw, err := os.ReadFile(file)
		if err != nil {
			continue
		}
		language := source.OfFile(file)
		lines := strings.Split(string(raw), "\n")
		for _, line := range lines {
			if !language.IsCommentLine(line) {
				continue
			}
			marker := exampleMarker.FindStringSubmatch(words(line))
			if marker == nil {
				continue
			}
			half := found.bad
			if marker[2] == "good" {
				half = found.good
			}
			half[marker[1]] = append(half[marker[1]], Marked{
				File:    file,
				Source:  published(lines, language),
				Heading: language.Comment("in " + strings.TrimPrefix(file, strings.TrimRight(root, "/")+"/")),
			})
		}
	}

	return found
}

// over is the examples with every rule that has example files told by them instead, per language, so a rule marked
// in a template and a module keeps the half no file replaced.
func (e examples) over(carved render.Examples, proven []detectors.Detector) render.Examples {
	for _, detector := range proven {
		bad, badLanguages := byLanguage(namedFor(e.bad, detector))
		good, goodLanguages := byLanguage(namedFor(e.good, detector))
		for _, language := range union(badLanguages, goodLanguages) {
			example := render.Example{Language: language}
			if len(bad[language]) > 0 {
				example.Bad = pointer(group(bad[language], false))
			}
			if len(good[language]) > 0 {
				example.Good = pointer(group(good[language], false))
			}
			var kept []render.Example
			for _, other := range carved[render.Key(detector)] {
				if other.Language != language {
					kept = append(kept, other)
				}
			}
			carved[render.Key(detector)] = append(kept, example)
		}
	}

	return carved
}

// namedFor is the files of a half marked with any name the detector goes by.
func namedFor(half sources, detector detectors.Detector) []Marked {
	var marked []Marked
	for _, name := range namesOf(detector) {
		marked = append(marked, half[name]...)
	}

	return marked
}

// namesOf is every name a marker may call the detector by: its own, its sin's, or the sin's id.
func namesOf(detector detectors.Detector) []string {
	var names []string
	for _, name := range []string{catalog.Name(detector), catalog.Name(detector.Sin()), detector.Sin().Definition().Name} {
		if !slices.Contains(names, name) {
			names = append(names, name)
		}
	}

	return names
}

func union(first, second []source.Language) []source.Language {
	all := append([]source.Language{}, first...)
	for _, language := range second {
		if !slices.Contains(all, language) {
			all = append(all, language)
		}
	}

	return all
}

// published is the file as a reader of the example sees it: without the fixture's markers, the imports that bring
// them in, the host a config file's marker rides, or the PHP opening, with no run of blank lines left where they
// stood.
func published(lines []string, language source.Language) string {
	var kept []string
	for _, line := range lines {
		if !isCeremony(line, language) {
			kept = append(kept, line)
		}
	}

	return strings.TrimSpace(blankRuns.ReplaceAllString(strings.Join(kept, "\n"), "\n\n"))
}

var blankRuns = regexp.MustCompile(`\n{3,}`)

func isCeremony(line string, language source.Language) bool {
	text := strings.TrimSpace(line)
	if language == source.PHP && (text == "<?php" || text == "declare(strict_types=1);" || text == fileScopeHost ||
		strings.HasPrefix(text, `use JesseGall\CodeCommandments\`) || strings.HasPrefix(text, "#[Sinful(") ||
		strings.HasPrefix(text, "#[Fixed(") || strings.HasPrefix(text, "#[Righteous(")) {
		return true
	}
	for _, any := range source.Languages {
		if any.IsCommentLine(line) && markerOnly.MatchString(strings.TrimSpace(words(line))) {
			return true
		}
	}

	return false
}

func words(line string) string {
	return strings.TrimSpace(commentWords.ReplaceAllString(line, ""))
}
