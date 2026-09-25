package frontend

import (
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/frontend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/scribes"
)

// The ways a subtree is found worth a component of its own.
const (
	duplicates = "duplicates"
	deepReach  = "deep-reach"
	nesting    = "nesting"
	compound   = "compound"
)

// uninformative are the words that name a container, not what it holds.
var uninformative = []string{
	"value", "values", "data", "item", "items", "config", "state", "props", "meta",
	"info", "detail", "details", "result", "results", "payload", "context", "entry",
}

func init() {
	scribes.Fixes(rules.DuplicateElementDetector{}, extractor(duplicates))
	scribes.Fixes(rules.DeepDataReachDetector{}, extractor(deepReach))
	scribes.Fixes(&rules.DeepNestedDetector{}, extractor(nesting))
	scribes.Fixes(rules.CompoundInlineComponentDetector{}, extractor(compound))
}

func extractor(strategy string) func() scribes.Scribe {
	return func() scribes.Scribe { return &ExtractComponentScribe{strategy: strategy} }
}

// ExtractComponentScribe lifts a subtree into a component of its own and puts a call to it in its place: a repeated
// subtree once for every copy, a deep one, a compound one, or one reaching deep into one object, that object its prop.
// An existing component of the same shape is reused instead.
type ExtractComponentScribe struct {
	strategy  string
	library   *vue.ComponentLibrary
	propTypes vue.PropTypes
	sfcs      map[string]*vue.Sfc
	oracle    vue.TypeOracle
	queries   *[]vue.TypeQuery
	resolved  map[string]map[string]string
}

// Situate hands the scribe the project's own vue-tsc, when it ships one, to type the props no reading of the source
// could.
func (s *ExtractComponentScribe) Situate(roots []string) {
	if len(roots) == 0 {
		return
	}
	if oracle, ok := vue.LocateVueTsc(roots[0], vue.ShellRunner{}); ok {
		s.oracle = oracle
	}
}

// Stage is after every in-place fix.
func (s *ExtractComponentScribe) Stage() scribes.Stage {
	return scribes.Extracting
}

// Rewrite extracts the outermost findings, each by the scribe's strategy.
func (s *ExtractComponentScribe) Rewrite(findings []engine.Match, codebase *engine.Codebase) (scribes.Rewrites, error) {
	components := s.read(codebase)
	s.library = vue.LibraryOf(components)
	s.propTypes = vue.PropTypesOver(vue.GraphOf(components))
	blocks := s.outermost(s.blocks(findings))
	if s.oracle != nil {
		s.prime(blocks)
	}

	return s.dispatch(blocks), nil
}

// prime runs the checker once for the whole run: a throwaway extraction, over a copy of the library so nothing it
// registers is reused by the real one, collects every prop it left unknown, and the oracle resolves them all.
func (s *ExtractComponentScribe) prime(blocks []block) {
	dry := *s
	dry.library = s.library.Clone()
	dry.queries = &[]vue.TypeQuery{}
	dry.dispatch(blocks)
	s.resolved = s.oracle.ResolveAll(*dry.queries)
}

func (s *ExtractComponentScribe) dispatch(blocks []block) scribes.Rewrites {
	switch s.strategy {
	case duplicates:
		return s.duplicates(blocks)
	case deepReach:
		return s.deepReach(blocks)
	case compound:
		return s.compound(blocks)
	}

	return s.nesting(blocks)
}

// block is a finding read as the PHP tool's template tree holds it.
type block struct {
	node *vue.Markup
	sfc  *vue.Sfc
}

func (b block) extraction() *vue.Extraction { return vue.ExtractionAt(b.node, b.sfc) }

// sibling is a file beside the block's own.
func (b block) sibling(name string) string { return filepath.Dir(b.sfc.Path) + "/" + name }

// read parses each component of the codebase as the PHP tool's tokenizer reads it.
func (s *ExtractComponentScribe) read(codebase *engine.Codebase) []*vue.Sfc {
	s.sfcs = map[string]*vue.Sfc{}
	var components []*vue.Sfc
	for _, file := range codebase.Files() {
		if !strings.HasSuffix(file.Path, ".vue") {
			continue
		}
		source, err := file.Source()
		if err != nil {
			continue
		}
		sfc := vue.ParseSfc(string(source), file.Path)
		s.sfcs[file.Path] = sfc
		components = append(components, sfc)
	}

	return components
}

// blocks is each finding's node in its component's tree.
func (s *ExtractComponentScribe) blocks(findings []engine.Match) []block {
	var blocks []block
	for _, finding := range findings {
		sfc, ok := s.sfcs[finding.File()]
		if !ok {
			continue
		}
		if node := nodeAt(sfc.Template, finding.Node().Span.Start); node != nil {
			blocks = append(blocks, block{node: node, sfc: sfc})
		}
	}

	return blocks
}

func nodeAt(root *vue.Markup, start int) *vue.Markup {
	for _, node := range root.Descendants() {
		if node.Start == start {
			return node
		}
	}

	return nil
}

// outermost drops each block nested inside another.
func (s *ExtractComponentScribe) outermost(blocks []block) []block {
	var kept []block
	for index, candidate := range blocks {
		nested := false
		for other, outer := range blocks {
			if index != other && outer.sfc.Path == candidate.sfc.Path && outer.node.Start <= candidate.node.Start && candidate.node.End <= outer.node.End &&
				(outer.node.Start != candidate.node.Start || outer.node.End != candidate.node.End) {
				nested = true

				break
			}
		}
		if !nested {
			kept = append(kept, candidate)
		}
	}

	return kept
}

func (s *ExtractComponentScribe) duplicates(blocks []block) scribes.Rewrites {
	draft := scribes.NewDraft()
	used := map[string]bool{}
	for _, members := range groups(blocks) {
		boundary := members[0].extraction()
		if !boundary.Extractable() {
			continue
		}
		if reuse, ok := s.library.Match(boundary); ok {
			for _, occurrence := range members {
				s.placeReuse(draft, occurrence.extraction(), reuse)
			}

			continue
		}
		props, statics := liftStatics(boundary, withLoopVars(boundary, boundary.Props()))
		name := unique(filepath.Dir(members[0].sfc.Path), boundary.Name(), used)
		component := members[0].sibling(name + ".vue")
		if !s.create(draft, boundary, component, s.render(boundary, props, boundary.Markup(), nil, "", statics)) {
			continue
		}
		for _, occurrence := range members {
			s.place(draft, occurrence.extraction(), component, name, selfBindings(boundary, props))
		}
	}

	return draft.Rewrites()
}

func (s *ExtractComponentScribe) nesting(blocks []block) scribes.Rewrites {
	return s.eachExtracted(blocks, func(boundary *vue.Extraction) string { return boundary.Name() })
}

func (s *ExtractComponentScribe) compound(blocks []block) scribes.Rewrites {
	return s.eachExtracted(blocks, compoundName)
}

// eachExtracted lifts each block on its own, named by name.
func (s *ExtractComponentScribe) eachExtracted(blocks []block, name func(*vue.Extraction) string) scribes.Rewrites {
	draft := scribes.NewDraft()
	used := map[string]bool{}
	for _, found := range blocks {
		boundary := found.extraction()
		if !boundary.Extractable() || s.reused(draft, boundary) {
			continue
		}
		props, statics := liftStatics(boundary, withLoopVars(boundary, boundary.Props()))
		named := unique(filepath.Dir(found.sfc.Path), name(boundary), used)
		component := found.sibling(named + ".vue")
		if s.create(draft, boundary, component, s.render(boundary, props, boundary.Markup(), nil, "", statics)) {
			s.place(draft, boundary, component, named, selfBindings(boundary, props))
		}
	}

	return draft.Rewrites()
}

func (s *ExtractComponentScribe) deepReach(blocks []block) scribes.Rewrites {
	draft := scribes.NewDraft()
	used := map[string]bool{}
	for _, found := range blocks {
		boundary := found.extraction()
		if !boundary.Extractable() || s.reused(draft, boundary) {
			continue
		}
		prefix, prop := midObject(found.node)
		props, statics := liftStatics(boundary, withLoopVars(boundary, reachProps(boundary, found.node, prefix, prop)))
		name := unique(filepath.Dir(found.sfc.Path), reachName(prefix, prop, boundary), used)
		component := found.sibling(name + ".vue")
		markup := boundary.Markup()
		if len(prefix) > 0 {
			markup = strings.ReplaceAll(markup, strings.Join(prefix, "."), prop)
		}
		if s.create(draft, boundary, component, s.render(boundary, props, markup, prefix, prop, statics)) {
			s.place(draft, boundary, component, name, reachBindings(props, prefix, prop))
		}
	}

	return draft.Rewrites()
}

// groups is the blocks grouped by their markup's structure, in the order each structure first appears.
func groups(blocks []block) [][]block {
	var order []string
	byShape := map[string][]block{}
	for _, found := range blocks {
		hash := found.node.StructureHash()
		if _, seen := byShape[hash]; !seen {
			order = append(order, hash)
		}
		byShape[hash] = append(byShape[hash], found)
	}
	grouped := make([][]block, 0, len(order))
	for _, hash := range order {
		grouped = append(grouped, byShape[hash])
	}

	return grouped
}

// reused places a call to an existing component of the subtree's shape, when the library holds one.
func (s *ExtractComponentScribe) reused(draft *scribes.Draft, boundary *vue.Extraction) bool {
	reuse, ok := s.library.Match(boundary)
	if !ok {
		return false
	}
	s.placeReuse(draft, boundary, reuse)

	return true
}

func (s *ExtractComponentScribe) placeReuse(draft *scribes.Draft, boundary *vue.Extraction, reuse vue.ComponentReuse) {
	draft.Edit(boundary.ContentSpan(), tag(reuse.Name, reuse.Bindings, tagShape{carried: boundary.Carried()}))
	if !mentions(boundary.Sfc.ScriptContent(), reuse.Name) {
		importComponent(draft, boundary.Sfc, reuse.Path, reuse.Name)
	}
}

// create drafts the component's file, unless it would be its own source or render itself.
func (s *ExtractComponentScribe) create(draft *scribes.Draft, boundary *vue.Extraction, path, content string) bool {
	if path == boundary.Sfc.Path || boundary.Node.Renders(strings.TrimSuffix(filepath.Base(path), ".vue")) {
		return false
	}
	draft.Add(path, content)
	s.library.Register(path, content)

	return true
}

// place puts the call to the new component where the subtree stood, and imports it.
func (s *ExtractComponentScribe) place(draft *scribes.Draft, boundary *vue.Extraction, component, name string, bindings []vue.Binding) {
	events, _ := boundary.EmitEvents()
	shape := tagShape{carried: boundary.Carried(), models: boundary.Models(), forwardsSlots: boundary.HasSlots(), column: boundary.ContentSpan().Column(), emits: events}
	draft.Edit(boundary.ContentSpan(), tag(name, bindings, shape))
	importComponent(draft, boundary.Sfc, component, name)
}

// tagShape is what a call site carries besides its bindings.
type tagShape struct {
	carried       []vue.WrittenAttribute
	models        []string
	forwardsSlots bool
	column        int
	emits         []string
}

// tag is the call to a component: its carried directives, a binding per prop, a model for each prop it writes, a
// listener per forwarded emit, and the host's slots passed through when it renders any.
func tag(name string, bindings []vue.Binding, shape tagShape) string {
	var attributes []string
	for _, carried := range shape.carried {
		attributes = append(attributes, carried.Render())
	}
	for _, binding := range bindings {
		if slices.Contains(shape.models, binding.Prop) {
			attributes = append(attributes, "v-model:"+kebab(binding.Prop)+`="`+binding.Expression+`"`)
		} else {
			attributes = append(attributes, ":"+kebab(binding.Prop)+`="`+binding.Expression+`"`)
		}
	}
	for _, event := range shape.emits {
		attributes = append(attributes, "@"+kebab(event)+`="`+event+`"`)
	}
	open := "<" + name
	if len(attributes) > 0 {
		open += " " + strings.Join(attributes, " ")
	}
	if !shape.forwardsSlots {
		return open + " />"
	}
	indent := strings.Repeat(" ", shape.column)

	return open + ">\n" +
		indent + `    <template v-for="(_, name) in $slots" :key="name" #[name]="slotProps">` + "\n" +
		indent + `        <slot :name="name" v-bind="slotProps" />` + "\n" +
		indent + "    </template>\n" +
		indent + "</" + name + ">"
}

// kebab spells a camelCase name kebab-case, an acronym left whole.
func kebab(name string) string {
	var out strings.Builder
	for index := 0; index < len(name); index++ {
		char := name[index]
		upper := char >= 'A' && char <= 'Z'
		if upper && index > 0 {
			previous := name[index-1]
			if !(previous >= 'A' && previous <= 'Z') && previous != '-' {
				out.WriteByte('-')
				out.WriteByte(char - 'A' + 'a')

				continue
			}
		}
		out.WriteByte(char)
	}

	return out.String()
}

func withLoopVars(boundary *vue.Extraction, props []string) []string {
	if !boundary.HasCarriedLoop() {
		return props
	}
	var all []string
	for _, prop := range append(slices.Clone(props), boundary.OwnLoopVars()...) {
		if !slices.Contains(all, prop) {
			all = append(all, prop)
		}
	}

	return all
}

// importComponent imports the component into the file: just inside its script, or in a `<script setup>` of its own.
func importComponent(draft *scribes.Draft, sfc *vue.Sfc, component, name string) {
	statement := "import " + name + " from '" + relativeImport(sfc.Path, component) + "';\n"
	source := []byte(sfc.Source)
	if at, ok := sfc.ScriptContentStart(); ok {
		draft.Edit(engine.Span{Path: sfc.Path, Source: source, Start: at, End: at}, "\n"+statement)

		return
	}
	draft.Edit(engine.Span{Path: sfc.Path, Source: source}, "<script setup lang=\"ts\">\n"+statement+"</script>\n\n")
}

// relativeImport is the specifier one file imports another by.
func relativeImport(from, to string) string {
	if filepath.Dir(from) == filepath.Dir(to) {
		return "./" + filepath.Base(to)
	}
	fromDir, toDir := segmentsOf(filepath.Dir(from)), segmentsOf(filepath.Dir(to))
	shared := 0
	for shared < len(fromDir) && shared < len(toDir) && fromDir[shared] == toDir[shared] {
		shared++
	}
	up := strings.Repeat("../", len(fromDir)-shared)
	if up == "" {
		up = "./"
	}
	down := strings.Join(toDir[shared:], "/")
	if down != "" {
		down += "/"
	}

	return up + down + filepath.Base(to)
}

func segmentsOf(dir string) []string {
	var segments []string
	for _, part := range strings.Split(dir, "/") {
		if part != "" && part != "." {
			segments = append(segments, part)
		}
	}

	return segments
}

func selfBindings(boundary *vue.Extraction, props []string) []vue.Binding {
	bindings := make([]vue.Binding, 0, len(props))
	for _, prop := range props {
		bindings = append(bindings, vue.Binding{Prop: prop, Expression: boundary.CallSiteExpression(prop)})
	}

	return bindings
}

func reachBindings(props, prefix []string, prop string) []vue.Binding {
	bindings := make([]vue.Binding, 0, len(props))
	for _, name := range props {
		expression := name
		if name == prop && len(prefix) > 0 {
			expression = strings.Join(prefix, ".")
		}
		bindings = append(bindings, vue.Binding{Prop: name, Expression: expression})
	}

	return bindings
}

// liftStatics is the props a component takes, and the module constants among them it carries in instead.
func liftStatics(boundary *vue.Extraction, props []string) ([]string, []string) {
	script := vue.ReadScript(boundary.Sfc.ScriptContent())
	var kept, statics []string
	for _, prop := range props {
		if declaration, ok := script.StaticConst(prop); ok {
			statics = append(statics, declaration)
		} else {
			kept = append(kept, prop)
		}
	}

	return kept, statics
}

// render is the new component's file: its imports, the types it carries in, its constants, models, props and emits,
// and its markup.
func (s *ExtractComponentScribe) render(boundary *vue.Extraction, props []string, markup string, prefix []string, reachProp string, statics []string) string {
	script := vue.ReadScript(boundary.Sfc.ScriptContent())
	if variable, ok := script.PropsVariable(); ok {
		markup = strings.ReplaceAll(markup, variable+".", "")
	}
	types := s.resolveTypes(boundary, props, script, prefix, reachProp)
	written := boundary.Models()
	var models, readProps []string
	for _, prop := range props {
		if slices.Contains(written, prop) {
			models = append(models, prop)
		} else {
			readProps = append(readProps, prop)
		}
	}
	var defineModels strings.Builder
	for _, model := range models {
		defineModels.WriteString("const " + model + " = defineModel<" + types[model] + ">('" + model + "');\n")
	}
	defineProps := ""
	if len(readProps) > 0 {
		fields := make([]string, 0, len(readProps))
		for _, prop := range readProps {
			fields = append(fields, prop+": "+types[prop])
		}
		defineProps = "defineProps<{ " + strings.Join(fields, "; ") + " }>();\n"
	}
	events, arity := boundary.EmitEvents()
	carriedConsts := ""
	if len(statics) > 0 {
		carriedConsts = strings.Join(statics, "\n") + "\n"
	}
	scriptSetup := carriedConsts + defineModels.String() + defineProps + defineEmits(events, arity)
	var typeList []string
	for _, prop := range props {
		typeList = append(typeList, types[prop])
	}
	imports := strings.Trim(usedImports(script, markup+"\n"+scriptSetup)+"\n"+syntheticTypeImports(script, typeList), " \t\n\r\x00\x0b")
	head := ""
	if imports != "" {
		head = imports + "\n\n"
	}

	return "<script setup lang=\"ts\">\n" + head + carriedTypes(script, typeList) + scriptSetup + "</script>\n\n<template>\n" + markup + "\n</template>\n"
}

func carriedTypes(script vue.Script, types []string) string {
	var referenced []string
	for _, typed := range types {
		referenced = append(referenced, typescript.ParseType(typed).References()...)
	}
	local := script.LocalTypes(referenced)
	if len(local.Names) == 0 {
		return ""
	}
	rendered := make([]string, 0, len(local.Names))
	for _, pair := range local.Pairs() {
		rendered = append(rendered, pair[1])
	}

	return strings.Join(rendered, "\n\n") + "\n\n"
}

func defineEmits(events []string, arity map[string]int) string {
	if len(events) == 0 {
		return ""
	}
	signatures := make([]string, 0, len(events))
	for _, event := range events {
		unknowns := make([]string, arity[event])
		for index := range unknowns {
			unknowns[index] = "unknown"
		}
		signatures = append(signatures, event+": ["+strings.Join(unknowns, ", ")+"]")
	}

	return "defineEmits<{ " + strings.Join(signatures, "; ") + " }>();\n"
}

// resolveTypes is each prop's type: the reached object's through its path, a loop variable's element type, else what
// the script, a composable, an Inertia form or the render tree says, its ref unwrapped, unknown when nothing does.
func (s *ExtractComponentScribe) resolveTypes(boundary *vue.Extraction, props []string, script vue.Script, prefix []string, reachProp string) map[string]string {
	source := script.PropTypes()
	types := map[string]string{}
	for _, prop := range props {
		iterable, iterates := boundary.IterableOf(prop)
		switch {
		case prop == reachProp && len(prefix) > 0:
			types[prop] = accessType(prefix, source, script)
		case iterates:
			if segments, ok := typescript.ParseExpression(iterable).AsChain(); ok {
				types[prop] = elementType(accessType(segments, source, script))

				continue
			}
			types[prop] = s.propType(boundary, script, source, prop)
		default:
			types[prop] = s.propType(boundary, script, source, prop)
		}
	}

	return s.consultOracle(boundary.Sfc, props, types)
}

// consultOracle is where the checker joins the type chain: the throwaway extraction records each component's props
// still unknown, and the real one takes the types the checker resolved for them, their ref unwrapped.
func (s *ExtractComponentScribe) consultOracle(component *vue.Sfc, props []string, types map[string]string) map[string]string {
	var unknown []string
	for _, prop := range props {
		if types[prop] == "unknown" && !slices.Contains(unknown, prop) {
			unknown = append(unknown, prop)
		}
	}
	if len(unknown) == 0 {
		return types
	}
	if s.queries != nil {
		s.record(component, unknown)

		return types
	}
	for _, name := range unknown {
		if typed, ok := s.resolved[component.Path][name]; ok {
			types[name] = typescript.UnwrapRefText(typed)
		}
	}

	return types
}

func (s *ExtractComponentScribe) record(component *vue.Sfc, unknown []string) {
	for index, query := range *s.queries {
		if query.Sfc.Path != component.Path {
			continue
		}
		for _, name := range unknown {
			if !slices.Contains(query.Names, name) {
				(*s.queries)[index].Names = append((*s.queries)[index].Names, name)
			}
		}

		return
	}
	*s.queries = append(*s.queries, vue.TypeQuery{Sfc: component, Names: unknown})
}

func (s *ExtractComponentScribe) propType(boundary *vue.Extraction, script vue.Script, source typescript.Fields, prop string) string {
	if typed, ok := source.Get(prop); ok {
		return typescript.UnwrapRefText(typed)
	}
	if typed, ok := script.DeclaredType(prop); ok {
		return typescript.UnwrapRefText(typed)
	}
	if typed, ok := tracedType(boundary, script, prop); ok {
		return typescript.UnwrapRefText(typed)
	}
	if typed, ok := inertiaFormType(script, prop); ok {
		return typescript.UnwrapRefText(typed)
	}
	if typed, ok := s.propTypes.TypeOf(boundary.Sfc, prop); ok {
		return typescript.UnwrapRefText(typed)
	}

	return typescript.UnwrapRefText("unknown")
}

func inertiaFormType(script vue.Script, prop string) (string, bool) {
	init, declared := script.DeclaratorValue(prop)
	source, imported := script.ImportSpecifier("useForm")
	trimmed := strings.TrimLeft(init, " \t\n\r\x00\x0b")
	if !declared || !strings.HasPrefix(trimmed, "useForm") || !imported || !strings.Contains(source, "@inertiajs") {
		return "", false
	}
	if generic, ok := genericArgument(trimmed); ok {
		return "InertiaForm<" + generic + ">", true
	}
	parsed := typescript.ParseExpression(init)
	if callee, ok := parsed.Callee(); !ok || callee != "useForm" {
		return "", false
	}
	argument, ok := parsed.Argument(0)
	if !ok {
		return "", false
	}
	shape, ok := argument.ObjectShape()
	if !ok {
		return "", false
	}

	return "InertiaForm<" + shape + ">", true
}

func genericArgument(init string) (string, bool) {
	rest := strings.TrimLeft(init[len("useForm"):], " \t\n\r\x00\x0b")
	if rest == "" || rest[0] != '<' {
		return "", false
	}
	depth := 0
	for index := 0; index < len(rest); index++ {
		switch rest[index] {
		case '<':
			depth++
		case '>':
			depth--
		}
		if depth == 0 {
			generic := strings.Trim(rest[1:index], " \t\n\r\x00\x0b")

			return generic, generic != "" && generic != "0"
		}
	}

	return "", false
}

func syntheticTypeImports(script vue.Script, types []string) string {
	for _, typed := range types {
		if !strings.Contains(typed, "InertiaForm<") {
			continue
		}
		source, ok := script.ImportSpecifier("useForm")
		if !ok {
			source = "@inertiajs/vue3"
		}

		return "import type { InertiaForm } from '" + source + "';"
	}

	return ""
}

// tracedType is a composable's field's type, for a prop destructured from a composable's call.
func tracedType(boundary *vue.Extraction, script vue.Script, prop string) (string, bool) {
	callee, ok := script.DestructuredCall(prop)
	if !ok {
		return "", false
	}
	module := script
	if specifier, imported := script.ImportSpecifier(callee); imported {
		path, found := vue.ResolverFor(boundary.Sfc.Path).Resolve(boundary.Sfc.Path, specifier)
		if !found {
			return "", false
		}
		module = vue.ScriptOf(path)
	}
	if returned, declares := module.ReturnTypeName(callee); declares {
		return module.FieldType(returned, prop)
	}

	return module.InferredReturnFields(callee).Get(prop)
}

func accessType(segments []string, source typescript.Fields, script vue.Script) string {
	typed, ok := source.Get(segments[0])
	if !ok {
		typed, ok = script.DeclaredType(segments[0])
	}
	if !ok {
		return "unknown"
	}
	for _, segment := range segments[1:] {
		typed += "['" + segment + "']"
	}

	return typed
}

func elementType(typed string) string {
	switch {
	case typed == "unknown":
		return "unknown"
	case strings.HasSuffix(typed, "[]"):
		return strings.TrimSuffix(typed, "[]")
	}

	return typed + "[number]"
}

func usedImports(script vue.Script, used string) string {
	var kept []string
	for _, imported := range script.Imports() {
		if imported.BindsAny(func(name string) bool { return mentions(used, name) }) {
			kept = append(kept, imported.Statement)
		}
	}

	return strings.Join(kept, "\n")
}

// mentions says whether the text spells the name as a whole identifier.
func mentions(text, name string) bool {
	return slices.Contains(identifiers(text), name)
}

func identifiers(text string) []string {
	var tokens []string
	current := ""
	for index := 0; index < len(text); index++ {
		if c := text[index]; c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z' || c >= '0' && c <= '9' || c == '_' || c == '$' {
			current += string(c)

			continue
		}
		if current != "" {
			tokens = append(tokens, current)
			current = ""
		}
	}
	if current != "" {
		tokens = append(tokens, current)
	}

	return tokens
}

// unique is a name no component beside the directory's others takes yet: Name, Name2, Name3…
func unique(dir, name string, used map[string]bool) string {
	candidate := name
	for n := 2; used[dir+"\x00"+candidate]; n++ {
		candidate = name + strconv.Itoa(n)
	}
	used[dir+"\x00"+candidate] = true

	return candidate
}

func reachName(prefix []string, prop string, boundary *vue.Extraction) string {
	if prop == "" {
		return boundary.Name()
	}
	for index := len(prefix) - 1; index >= 0; index-- {
		if !slices.Contains(uninformative, strings.ToLower(prefix[index])) {
			return upperFirst(prefix[index]) + "Section"
		}
	}

	return boundary.Name()
}

func compoundName(boundary *vue.Extraction) string {
	title, _ := boundary.Node.StaticTextIn("Title")
	if title == "" {
		return boundary.Name()
	}
	family := boundary.Node.Tag
	base := pascal(title)
	if strings.HasSuffix(base, family) {
		return base
	}

	return base + family
}

// pascal is the text's alphanumeric words, each capitalised and joined.
func pascal(text string) string {
	var out strings.Builder
	word := ""
	for index := 0; index <= len(text); index++ {
		if index < len(text) && isAlnum(text[index]) {
			word += string(text[index])

			continue
		}
		if word != "" {
			out.WriteString(upperFirst(strings.ToLower(word)))
			word = ""
		}
	}

	return out.String()
}

func isAlnum(c byte) bool {
	return c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z' || c >= '0' && c <= '9'
}

func upperFirst(text string) string {
	if text == "" || text[0] < 'a' || text[0] > 'z' {
		return text
	}

	return string(text[0]-'a'+'A') + text[1:]
}

// midObject is the object every deep chain the block reads passes through, and its last segment, the prop it becomes.
func midObject(node *vue.Markup) ([]string, string) {
	var deep [][]string
	for _, chain := range nodeChains(node) {
		if len(chain) >= 3 {
			deep = append(deep, chain)
		}
	}
	if len(deep) == 0 {
		return nil, ""
	}
	prefix := deep[0]
	shortest := len(prefix)
	for _, chain := range deep[1:] {
		prefix = commonPrefix(prefix, chain)
		shortest = min(shortest, len(chain))
	}
	mid := prefix[:min(len(prefix), shortest-1)]
	if len(mid) == 0 {
		return mid, ""
	}

	return mid, mid[len(mid)-1]
}

func reachProps(boundary *vue.Extraction, node *vue.Markup, prefix []string, prop string) []string {
	free := boundary.Props()
	if len(prefix) == 0 {
		return free
	}
	root := prefix[0]
	readElsewhere := false
	for _, chain := range nodeChains(node) {
		if len(chain) > 0 && chain[0] == root && !slices.Equal(chain[:min(len(chain), len(prefix))], prefix) {
			readElsewhere = true

			break
		}
	}
	props := free
	if !readElsewhere {
		props = slices.DeleteFunc(slices.Clone(free), func(name string) bool { return name == root })
	}
	if slices.Contains(props, prop) {
		return props
	}

	return append(props, prop)
}

// nodeChains is every chain the node and the tags below it read.
func nodeChains(node *vue.Markup) [][]string {
	var chains [][]string
	var walk func(*vue.Markup)
	walk = func(at *vue.Markup) {
		if at.IsElement() {
			for _, expression := range at.Expressions() {
				chains = append(chains, expression.Chains()...)
			}
		}
		for _, child := range at.Children {
			walk(child)
		}
	}
	walk(node)

	return chains
}

func commonPrefix(a, b []string) []string {
	var prefix []string
	for index, segment := range a {
		if index >= len(b) || b[index] != segment {
			break
		}
		prefix = append(prefix, segment)
	}

	return prefix
}
