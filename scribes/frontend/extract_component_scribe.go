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
	modules   vue.Modules
	sources   scribes.Sources
	oracle    vue.TypeOracle
	queries   *[]vue.TypeQuery
	resolved  map[string]map[string]typescript.TypeNode
}

// Situate hands the scribe the source the pass does not hold, and the project's own vue-tsc when it ships one, to
// type the props no reading of the source could.
func (s *ExtractComponentScribe) Situate(roots []string, sources scribes.Sources) {
	s.sources = sources
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
	var components []vue.Component
	for _, file := range codebase.Files() {
		if root := file.Match(file.Root.ID); root.Kind() == "Component" {
			components = append(components, vue.ComponentOf(root))
		}
	}
	s.modules = vue.ModulesIn(codebase, s.readModule)
	s.library = vue.LibraryOf(components)
	s.propTypes = vue.PropTypesOver(vue.GraphOf(components), s.modules)
	blocks := outermost(elementsOf(findings))
	if s.oracle != nil {
		s.prime(blocks)
	}

	return s.dispatch(blocks), nil
}

// readModule reads the script of a file outside the pass's roots.
func (s *ExtractComponentScribe) readModule(path string) (typescript.Module, bool) {
	if s.sources == nil {
		return typescript.Module{}, false
	}
	read, err := s.sources.ReadFile(path)
	if err != nil {
		return typescript.Module{}, false
	}
	for _, file := range read.Files() {
		return vue.ModuleOf(file.Match(file.Root.ID))
	}

	return typescript.Module{}, false
}

// prime runs the checker once for the whole run: a throwaway extraction, over a copy of the library so nothing it
// registers is reused by the real one, collects every prop it left unknown, and the oracle resolves them all.
func (s *ExtractComponentScribe) prime(blocks []vue.Element) {
	dry := *s
	dry.library = s.library.Clone()
	dry.queries = &[]vue.TypeQuery{}
	dry.dispatch(blocks)
	s.resolved = s.typed(s.oracle.ResolveAll(*dry.queries))
}

// typed is the types the checker wrote, each read as a type.
func (s *ExtractComponentScribe) typed(written map[string]map[string]string) map[string]map[string]typescript.TypeNode {
	resolved := map[string]map[string]typescript.TypeNode{}
	if s.sources == nil {
		return resolved
	}
	var aliases strings.Builder
	var owners [][2]string
	for path, types := range written {
		for name, typed := range types {
			aliases.WriteString("type __cc" + strconv.Itoa(len(owners)) + " = " + typed + ";\n")
			owners = append(owners, [2]string{path, name})
		}
	}
	read, err := s.sources.Parse(map[string]string{"types.ts": aliases.String()})
	if err != nil {
		return resolved
	}
	for _, file := range read.Files() {
		for _, statement := range file.Match(file.Root.ID).ChildrenIn("statements") {
			index, err := strconv.Atoi(strings.TrimPrefix(statement.Name(), "__cc"))
			if statement.Kind() != "TypeAliasDeclaration" || err != nil || index >= len(owners) {
				continue
			}
			path, name := owners[index][0], owners[index][1]
			if resolved[path] == nil {
				resolved[path] = map[string]typescript.TypeNode{}
			}
			resolved[path][name] = typescript.TypeOf(typescript.Of(statement.Child("type")))
		}
	}

	return resolved
}

func (s *ExtractComponentScribe) dispatch(blocks []vue.Element) scribes.Rewrites {
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

// elementsOf is each finding that is a template element.
func elementsOf(findings []engine.Match) []vue.Element {
	var elements []vue.Element
	for _, finding := range findings {
		if finding.Kind() == "Element" {
			elements = append(elements, vue.Of(finding))
		}
	}

	return elements
}

// outermost drops each element nested inside another.
func outermost(elements []vue.Element) []vue.Element {
	var kept []vue.Element
	for index, candidate := range elements {
		inner := candidate.Node().Span
		nested := false
		for other, outer := range elements {
			around := outer.Node().Span
			if index != other && outer.File() == candidate.File() && around.Start <= inner.Start && inner.End <= around.End &&
				(around.Start != inner.Start || around.End != inner.End) {
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

// sibling is a file beside the element's own.
func sibling(element vue.Element, name string) string {
	return filepath.Dir(element.File()) + "/" + name
}

func (s *ExtractComponentScribe) duplicates(blocks []vue.Element) scribes.Rewrites {
	draft := scribes.NewDraft()
	used := map[string]bool{}
	for _, members := range groups(blocks) {
		boundary := vue.ExtractionAt(members[0])
		if !boundary.Extractable() {
			continue
		}
		if reuse, ok := s.library.Match(boundary); ok {
			for _, occurrence := range members {
				s.placeReuse(draft, vue.ExtractionAt(occurrence), reuse)
			}

			continue
		}
		props, statics := liftStatics(boundary, withLoopVars(boundary, boundary.Props()))
		name := unique(filepath.Dir(boundary.Path()), boundary.Name(), used)
		component := sibling(members[0], name+".vue")
		if !s.create(draft, boundary, component, s.render(boundary, props, boundary.Markup(), nil, "", statics)) {
			continue
		}
		for _, occurrence := range members {
			s.place(draft, vue.ExtractionAt(occurrence), component, name, selfBindings(boundary, props))
		}
	}

	return draft.Rewrites()
}

func (s *ExtractComponentScribe) nesting(blocks []vue.Element) scribes.Rewrites {
	return s.eachExtracted(blocks, func(boundary *vue.Extraction) string { return boundary.Name() })
}

func (s *ExtractComponentScribe) compound(blocks []vue.Element) scribes.Rewrites {
	return s.eachExtracted(blocks, compoundName)
}

// eachExtracted lifts each block on its own, named by name.
func (s *ExtractComponentScribe) eachExtracted(blocks []vue.Element, name func(*vue.Extraction) string) scribes.Rewrites {
	draft := scribes.NewDraft()
	used := map[string]bool{}
	for _, found := range blocks {
		boundary := vue.ExtractionAt(found)
		if !boundary.Extractable() || s.reused(draft, boundary) {
			continue
		}
		props, statics := liftStatics(boundary, withLoopVars(boundary, boundary.Props()))
		named := unique(filepath.Dir(boundary.Path()), name(boundary), used)
		component := sibling(found, named+".vue")
		if s.create(draft, boundary, component, s.render(boundary, props, boundary.Markup(), nil, "", statics)) {
			s.place(draft, boundary, component, named, selfBindings(boundary, props))
		}
	}

	return draft.Rewrites()
}

func (s *ExtractComponentScribe) deepReach(blocks []vue.Element) scribes.Rewrites {
	draft := scribes.NewDraft()
	used := map[string]bool{}
	for _, found := range blocks {
		boundary := vue.ExtractionAt(found)
		if !boundary.Extractable() || s.reused(draft, boundary) {
			continue
		}
		prefix, prop := midObject(found)
		props, statics := liftStatics(boundary, withLoopVars(boundary, reachProps(boundary, found, prefix, prop)))
		name := unique(filepath.Dir(boundary.Path()), reachName(prefix, prop, boundary), used)
		component := sibling(found, name+".vue")
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

// groups is the elements grouped by their markup's structure, in the order each structure first appears.
func groups(blocks []vue.Element) [][]vue.Element {
	var order []string
	byShape := map[string][]vue.Element{}
	for _, found := range blocks {
		hash := found.StructureHash()
		if _, seen := byShape[hash]; !seen {
			order = append(order, hash)
		}
		byShape[hash] = append(byShape[hash], found)
	}
	grouped := make([][]vue.Element, 0, len(order))
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
	if !mentions(boundary.Component.ScriptText(), reuse.Name) {
		importComponent(draft, boundary.Component, reuse.Path, reuse.Name)
	}
}

// create drafts the component's file, unless it would be its own source or render itself, and holds it in the
// library for a later subtree to reuse.
func (s *ExtractComponentScribe) create(draft *scribes.Draft, boundary *vue.Extraction, path, content string) bool {
	if path == boundary.Path() || boundary.Renders(strings.TrimSuffix(filepath.Base(path), ".vue")) {
		return false
	}
	draft.Add(path, content)
	if s.sources == nil {
		return true
	}
	read, err := s.sources.Parse(map[string]string{filepath.Base(path): content})
	if err != nil {
		return true
	}
	for _, file := range read.Files() {
		s.library.RegisterAt(vue.ComponentOf(file.Match(file.Root.ID)), path)
	}

	return true
}

// place puts the call to the new component where the subtree stood, and imports it.
func (s *ExtractComponentScribe) place(draft *scribes.Draft, boundary *vue.Extraction, component, name string, bindings []vue.Binding) {
	events, _ := boundary.EmitEvents()
	shape := tagShape{carried: boundary.Carried(), models: boundary.Models(), forwardsSlots: boundary.HasSlots(), column: boundary.ContentSpan().Column(), emits: events}
	draft.Edit(boundary.ContentSpan(), tag(name, bindings, shape))
	importComponent(draft, boundary.Component, component, name)
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
func importComponent(draft *scribes.Draft, host vue.Component, component, name string) {
	statement := "import " + name + " from '" + relativeImport(host.File(), component) + "';\n"
	whole, err := host.Span()
	if err != nil {
		return
	}
	if at, ok := host.ScriptContentStart(); ok {
		draft.Edit(engine.Span{Path: whole.Path, Source: whole.Source, Start: at, End: at}, "\n"+statement)

		return
	}
	draft.Edit(engine.Span{Path: whole.Path, Source: whole.Source}, "<script setup lang=\"ts\">\n"+statement+"</script>\n\n")
}

func selfBindings(boundary *vue.Extraction, props []string) []vue.Binding {
	bindings := make([]vue.Binding, 0, len(props))
	for _, prop := range props {
		bindings = append(bindings, vue.Binding{Prop: prop, Expression: boundary.CallSiteExpression(prop)})
	}

	return bindings
}

// liftStatics is the props a component takes, and the module constants among them it carries in instead.
func liftStatics(boundary *vue.Extraction, props []string) ([]string, []string) {
	script := boundary.Script()
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
	script := boundary.Script()
	if variable, ok := boundary.PropsVariable(); ok {
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
		defineModels.WriteString("const " + model + " = defineModel<" + types[model].Render() + ">('" + model + "');\n")
	}
	defineProps := ""
	if len(readProps) > 0 {
		fields := make([]string, 0, len(readProps))
		for _, prop := range readProps {
			fields = append(fields, prop+": "+types[prop].Render())
		}
		defineProps = "defineProps<{ " + strings.Join(fields, "; ") + " }>();\n"
	}
	events, arity := boundary.EmitEvents()
	carriedConsts := ""
	if len(statics) > 0 {
		carriedConsts = strings.Join(statics, "\n") + "\n"
	}
	scriptSetup := carriedConsts + defineModels.String() + defineProps + defineEmits(events, arity)
	var typeList []typescript.TypeNode
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

// carriedTypes is each type declaration of the script the types reach, written again.
func carriedTypes(script typescript.Module, types []typescript.TypeNode) string {
	var referenced []string
	for _, typed := range types {
		referenced = append(referenced, typed.References()...)
	}
	local := script.LocalTypes(referenced)
	if len(local) == 0 {
		return ""
	}
	rendered := make([]string, 0, len(local))
	for _, declaration := range local {
		rendered = append(rendered, declaration.Render())
	}

	return strings.Join(rendered, "\n\n") + "\n\n"
}

// resolveTypes is each prop's type: the reached object's through its path, a loop variable's element type, else what
// the script, a composable, an Inertia form or the render tree says, its ref unwrapped, unknown when nothing does.
func (s *ExtractComponentScribe) resolveTypes(boundary *vue.Extraction, props []string, script typescript.Module, prefix []string, reachProp string) map[string]typescript.TypeNode {
	source := boundary.Component.PropTypes()
	types := map[string]typescript.TypeNode{}
	for _, prop := range props {
		iterable, iterates := boundary.IterableOf(prop)
		switch {
		case prop == reachProp && len(prefix) > 0:
			types[prop] = accessType(prefix, source, script)
		case iterates:
			if segments, ok := iterable.Chain(); ok {
				types[prop] = elementType(accessType(segments, source, script))

				continue
			}
			types[prop] = s.propType(boundary, script, source, prop)
		default:
			types[prop] = s.propType(boundary, script, source, prop)
		}
	}

	return s.consultOracle(boundary.Component, props, types)
}

// consultOracle is where the checker joins the type chain: the throwaway extraction records each component's props
// still unknown, and the real one takes the types the checker resolved for them, their ref unwrapped.
func (s *ExtractComponentScribe) consultOracle(component vue.Component, props []string, types map[string]typescript.TypeNode) map[string]typescript.TypeNode {
	var unknown []string
	for _, prop := range props {
		if types[prop].Render() == "unknown" && !slices.Contains(unknown, prop) {
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
		if typed, ok := s.resolved[component.File()][name]; ok {
			types[name] = typed.UnwrapRef()
		}
	}

	return types
}

func (s *ExtractComponentScribe) record(component vue.Component, unknown []string) {
	for index, query := range *s.queries {
		if query.Component.File() != component.File() {
			continue
		}
		for _, name := range unknown {
			if !slices.Contains(query.Names, name) {
				(*s.queries)[index].Names = append((*s.queries)[index].Names, name)
			}
		}

		return
	}
	*s.queries = append(*s.queries, vue.TypeQuery{Component: component, Names: unknown})
}

func (s *ExtractComponentScribe) propType(boundary *vue.Extraction, script typescript.Module, source typescript.Fields, prop string) typescript.TypeNode {
	if typed, ok := source.Get(prop); ok {
		return typed.UnwrapRef()
	}
	if typed, ok := script.DeclaredType(prop); ok {
		return typed.UnwrapRef()
	}
	if typed, ok := s.tracedType(script, prop); ok {
		return typed.UnwrapRef()
	}
	if typed, ok := inertiaFormType(script, prop); ok {
		return typed.UnwrapRef()
	}
	if typed, ok := s.propTypes.TypeOf(boundary.Component, prop); ok {
		return typed.UnwrapRef()
	}

	return typescript.Unknown
}

// inertiaFormType is the type of a local an Inertia `useForm` builds: its type argument's form, else the form of the
// object it seeds, each field its value's type.
func inertiaFormType(script typescript.Module, prop string) (typescript.TypeNode, bool) {
	variable, declared := script.Variable(prop)
	imported, ok := script.ImportOf("useForm")
	if !declared || !ok || !strings.Contains(imported.Specifier, "@inertiajs") || variable.InitCall == nil || variable.InitCall.Callee != "useForm" {
		return nil, false
	}
	call := *variable.InitCall
	if len(call.TypeArguments) > 0 {
		return typescript.NamedType{Name: "InertiaForm", Arguments: call.TypeArguments[:1]}, true
	}
	if len(call.Arguments) == 0 {
		return nil, false
	}
	shape, ok := objectShape(call.Arguments[0])
	if !ok {
		return nil, false
	}

	return typescript.NamedType{Name: "InertiaForm", Arguments: []typescript.TypeNode{shape}}, true
}

// objectShape is an object literal's type, each field its inferred type or unknown.
func objectShape(object typescript.Node) (typescript.TypeNode, bool) {
	if object.Kind() != "ObjectLiteralExpression" {
		return nil, false
	}
	var members []typescript.Member
	for _, property := range object.ChildrenIn("properties") {
		key := property.Child("name")
		if property.Kind() != "PropertyAssignment" && property.Kind() != "ShorthandPropertyAssignment" {
			continue
		}
		typed, inferred := typescript.Of(property.Child("initializer")).InferType()
		if !inferred {
			typed = typescript.Unknown
		}
		members = append(members, typescript.Member{Name: key.Written(), Property: typed})
	}

	return typescript.ObjectType{Members: members}, len(members) > 0
}

func syntheticTypeImports(script typescript.Module, types []typescript.TypeNode) string {
	for _, typed := range types {
		if !strings.Contains(typed.Render(), "InertiaForm<") {
			continue
		}
		source := "@inertiajs/vue3"
		if imported, ok := script.ImportOf("useForm"); ok {
			source = imported.Specifier
		}

		return "import type { InertiaForm } from '" + source + "';"
	}

	return ""
}

// tracedType is a composable's field's type, for a prop destructured from a composable's call.
func (s *ExtractComponentScribe) tracedType(script typescript.Module, prop string) (typescript.TypeNode, bool) {
	callee, ok := script.DestructuredCall(prop)
	if !ok {
		return nil, false
	}
	module := script
	if imported, isImported := script.ImportOf(callee); isImported {
		if imported.Resolves == "" {
			return nil, false
		}
		if module, ok = s.modules(imported.Resolves); !ok {
			return nil, false
		}
	}
	if returned, declares := module.ReturnTypeName(callee); declares {
		return module.FieldType(returned.Render(), prop)
	}

	return module.InferredReturnFields(callee).Get(prop)
}

func accessType(segments []string, source typescript.Fields, script typescript.Module) typescript.TypeNode {
	typed, ok := source.Get(segments[0])
	if !ok {
		typed, ok = script.DeclaredType(segments[0])
	}
	if !ok {
		return typescript.Unknown
	}

	return vue.AccessType(typed, segments[1:])
}

// elementType is what an array of the type holds: its element, else an index into it.
func elementType(typed typescript.TypeNode) typescript.TypeNode {
	if array, ok := typed.(typescript.ArrayType); ok {
		return array.Element
	}
	switch rendered := typed.Render(); {
	case rendered == "unknown":
		return typescript.Unknown
	case strings.HasSuffix(rendered, "[]"):
		return typescript.VerbatimType{Raw: strings.TrimSuffix(rendered, "[]")}
	}

	return typescript.IndexedAccessType{Object: typed, Index: typescript.KeywordType{Name: "number"}}
}

func usedImports(script typescript.Module, used string) string {
	var kept []string
	for _, imported := range script.Imports() {
		if imported.BindsAny(func(name string) bool { return mentions(used, name) }) {
			kept = append(kept, imported.Statement)
		}
	}

	return strings.Join(kept, "\n")
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
	title, _ := boundary.Element.StaticTextIn("Title")
	if title == "" {
		return boundary.Name()
	}
	family := boundary.Element.Tag()
	base := pascal(title)
	if strings.HasSuffix(base, family) {
		return base
	}

	return base + family
}

// midObject is the object every deep chain the element reads passes through, and its last segment, the prop it
// becomes.
func midObject(element vue.Element) ([]string, string) {
	var deep [][]string
	for _, chain := range vue.ChainsIn(element) {
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

func reachProps(boundary *vue.Extraction, element vue.Element, prefix []string, prop string) []string {
	free := boundary.Props()
	if len(prefix) == 0 {
		return free
	}
	root := prefix[0]
	readElsewhere := false
	for _, chain := range vue.ChainsIn(element) {
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
