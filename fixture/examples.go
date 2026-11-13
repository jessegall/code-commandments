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
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/skill/render"
)

// Marked is one marked piece of a fixture as a worked example shows it: the code, the file it is in, the lines it spans there, the comment line that names where it came from (none when empty), and what a Bad and a Good must share to be one before and after — the type for PHP, else the file.
type Marked struct {
	File     string
	Source   string
	Heading  string
	Scenario string
	First    int
	Last     int
}

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

func recurringGroups(codebase *engine.Codebase, proven []detectors.Detector) map[string]map[string]map[int]string {
	groups := map[string]map[string]map[int]string{}
	for _, detector := range proven {
		grouped, recurs := detector.(detectors.Grouped)
		if !recurs {
			continue
		}
		groups[render.Key(detector)] = groupedByFile(grouped, detector.Find(codebase))
	}

	return groups
}

func groupedByFile(grouped detectors.Grouped, found []engine.Match) map[string]map[int]string {
	byFile := map[string]map[int]string{}
	for _, finding := range found {
		key, keyed := grouped.GroupKey(finding)
		if !keyed {
			continue
		}
		if byFile[finding.File()] == nil {
			byFile[finding.File()] = map[int]string{}
		}
		byFile[finding.File()][finding.Line()] = key
	}

	return byFile
}

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

var markerWord = regexp.MustCompile(`@(\w+)\s+(\w+)`)

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

func holding(node engine.Match, start, end int) engine.Match {
	for _, child := range node.Children() {
		if span := child.Node().Span; span.Start <= start && end <= span.End {
			return holding(child, start, end)
		}
	}

	return node
}

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
		names := markersAbove(lines, line, tag, language)
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

func commentsAbove(lines []string, at int, language source.Language) []string {
	var comments []string
	for n := at - 1; n >= 1 && strings.TrimSpace(lines[n-1]) != "" && contract.IsCommentLine(language.Written(), lines[n-1]); n-- {
		comments = append([]string{lines[n-1]}, comments...)
	}

	return comments
}

func markersAbove(lines []string, at int, tag Tag, language source.Language) []string {
	var names []string
	for n := min(at-1, len(lines)); n >= 1; n-- {
		text := strings.TrimSpace(lines[n-1])
		if text == "" {
			continue
		}
		found := markerWord.FindStringSubmatch(text)
		if found == nil && contract.IsCommentLine(language.Written(), lines[n-1]) {
			continue
		}
		if found == nil {
			break
		}
		if Tag(found[1]) == tag {
			names = append(names, found[2])
		}
	}

	return names
}

func isMarkerLine(line string, language source.Language) bool {
	return contract.IsCommentLine(language.Written(), line) && markerWord.MatchString(strings.TrimSpace(line))
}

func forKeys(marked sources, keys []string) []Marked {
	for _, key := range keys {
		if len(marked[key]) > 0 {
			return marked[key]
		}
	}

	return nil
}

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

func anchorOf(bad, good []Marked) (Marked, bool) {
	resolution, resolves := counterpart(bad, good)

	return answered(bad, resolution, resolves)
}

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

func indexOf(marked []Marked, one Marked) int {
	for i, each := range marked {
		if each == one {
			return i
		}
	}

	return -1
}

var sharing = []func(one, other Marked) bool{
	func(one, other Marked) bool { return one.sharesScenarioWith(other) },
	func(one, other Marked) bool { return one.sharesFileWith(other) },
}

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

var whitespace = regexp.MustCompile(`\s+`)

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

func lineAt(text []byte, offset int) int {
	return 1 + strings.Count(string(text[:min(offset, len(text))]), "\n")
}

func pointer(text string) *string {
	return &text
}

var exampleMarker = regexp.MustCompile(`^@example\s+(\w+)\s+(bad|good)$`)

var markerOnly = regexp.MustCompile(`^@(?:(?:sin|fixed|righteous)\s+\w+|example\s+\w+\s+(?:bad|good))$`)

var commentWords = regexp.MustCompile(`^\s*(?:\/\/|#|<!--|\/\*+|\*)\s*|\s*(?:-->|\*\/)\s*$`)

const fileScopeHost = "static fn (): null => null;"

// examples are the fixture's @example files: each half's files, by the name each is marked with.
type examples struct {
	bad, good sources
}

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
			if !contract.IsCommentLine(language.Written(), line) {
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
			others := slices.DeleteFunc(slices.Clone(carved[render.Key(detector)]), func(other render.Example) bool {
				return other.Language == language
			})
			carved[render.Key(detector)] = append(others, example)
		}
	}

	return carved
}

func namedFor(half sources, detector detectors.Detector) []Marked {
	var marked []Marked
	for _, name := range namesOf(detector) {
		marked = append(marked, half[name]...)
	}

	return marked
}

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
		if contract.IsCommentLine(any.Written(), line) && markerOnly.MatchString(strings.TrimSpace(words(line))) {
			return true
		}
	}

	return false
}

func words(line string) string {
	return strings.TrimSpace(commentWords.ReplaceAllString(line, ""))
}
