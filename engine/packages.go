package engine

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"encoding/xml"
	"fmt"
	"maps"
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"strconv"
	"strings"
	"sync"
)

// PackageFacts is what one library states about its own symbols, for a version range: each entry an effect a call
// bound to one of its methods has, and when it happens. It names the declaration a call resolves to and never the
// code around the call: a package entry cannot say that a loop is wrong.
type PackageFacts struct {
	Package    string             `json:"package"`
	Ecosystem  string             `json:"ecosystem"`
	Versions   string             `json:"versions"`
	Provenance string             `json:"provenance"`
	Entries    []PackageEntry     `json:"entries"`
	Exemptions []PackageExemption `json:"exemptions,omitempty"`
	Identities []PackageIdentity  `json:"identities,omitempty"`
	Forwards   []PackageForward   `json:"forwards,omitempty"`
}

// PackageForward is a library type that answers a method it does not declare by handing the call to another type, as
// an Eloquent model hands a static query call to its query builder.
type PackageForward struct {
	Type string `json:"type"`
	To   string `json:"to"`
}

// PackageEntry is one effect of one method: the type that declares it, its name, the effect (database-read,
// deferred-query, executes-query, cast-result-type, returns-empty-collection) and when it runs (call, iteration,
// first-read).
type PackageEntry struct {
	Type      string `json:"type"`
	Name      string `json:"name"`
	Effect    string `json:"effect"`
	Execution string `json:"execution"`
}

// The ecosystems a package comes from, each read from its own lock file or manifest.
const (
	Composer = "composer"
	PyPI     = "pypi"
	NuGet    = "nuget"
	// Standard is a language's own library, which every project holds.
	Standard = "standard"
)

var packageFacts struct {
	sync.Mutex
	held []PackageFacts
}

// RegisterPackageFacts adds a library's facts to those every codebase reads.
func RegisterPackageFacts(facts PackageFacts) {
	packageFacts.Lock()
	defer packageFacts.Unlock()
	packageFacts.held = append(slices.Clone(packageFacts.held), facts)
}

func registeredPackageFacts() []PackageFacts {
	packageFacts.Lock()
	defer packageFacts.Unlock()

	return packageFacts.held
}

// HasEffect is whether a call or construction has the effect named by the library its resolved target belongs to.
// It is unknown where the compiler did not resolve the target, or where no lock file says which version of the
// library the project holds; a method that only shares a library method's name has no effect.
func (m Match) HasEffect(effect string) Truth {
	if m.node == nil || m.file == nil {
		return Unknown
	}
	target := m.node.Target
	if target == nil {
		return m.writtenCallEffect(effect)
	}
	answer := False
	for _, facts := range registeredPackageFacts() {
		if !slices.ContainsFunc(facts.Entries, func(entry PackageEntry) bool {
			return entry.Effect == effect && entry.Name == target.Name && (m.declaredBy(target.Type, entry.Type) || m.declaredBy(m.receiverType(), entry.Type))
		}) {
			continue
		}
		if held := m.file.codebase.holds(facts); held == True {
			return True
		} else {
			answer = answer.Or(held)
		}
	}

	return answer
}

// holds is whether the project holds the library in a version its facts apply to: always for the language's own
// standard library, unknown where no lock file or manifest says.
func (c *Codebase) holds(facts PackageFacts) Truth {
	if facts.Ecosystem == Standard {
		return True
	}
	version, read := c.PackageVersion(facts.Ecosystem, facts.Package)
	switch {
	case !read:
		return Unknown
	case version != "" && InVersionRange(version, facts.Versions):
		return True
	}

	return False
}

// declaredBy says whether a target's declaring type is the type an entry names, or one of its subtypes.
// Both are compared as bare symbols, so C#'s `global::System.Exception` is the entry's `System.Exception`.
func (m Match) declaredBy(declaring, named string) bool {
	if BareSymbol(declaring) == BareSymbol(named) {
		return true
	}
	types, typed := m.Types()

	return typed && slices.ContainsFunc(Supertypes(types, declaring), func(supertype string) bool { return BareSymbol(supertype) == BareSymbol(named) })
}

// PackageVersion is the version of a package the project's lock file or manifest holds: empty when the project does
// not hold the package, and not read when no lock file or manifest of that ecosystem is found above a root judged.
func (c *Codebase) PackageVersion(ecosystem, name string) (string, bool) {
	versions := Analysis(c, "package-versions", readPackageVersions)
	held, read := versions[ecosystem]
	if !read {
		return "", false
	}

	return held[strings.ToLower(name)], true
}

func readPackageVersions(c *Codebase) map[string]map[string]string {
	versions := map[string]map[string]string{}
	visited := map[string]bool{}
	for _, stream := range c.streams {
		for _, root := range stream.Header.Roots {
			for _, folder := range foldersUpFrom(root) {
				if visited[folder] {
					break
				}
				visited[folder] = true
				readManifests(folder, versions)
			}
		}
	}

	return versions
}

var packageFiles = []string{"composer.lock", "vendor/composer/installed.json", "requirements.txt", "poetry.lock", "uv.lock", "*.csproj",
	"Directory.Packages.props", "package-lock.json", "pnpm-lock.yaml", "yarn.lock", "go.sum"}

// InstalledPackages names the packages the projects above the roots hold, by the contents of every lock file and
// installed list found there, so what a bridge resolved through them is asked again when one changes.
func InstalledPackages(roots []string) string {
	digest := sha256.New()
	visited := map[string]bool{}
	for _, root := range roots {
		for _, folder := range foldersUpFrom(root) {
			if visited[folder] {
				break
			}
			visited[folder] = true
			for _, pattern := range packageFiles {
				found, _ := filepath.Glob(filepath.Join(folder, pattern))
				for _, file := range found {
					if text, err := os.ReadFile(file); err == nil {
						fmt.Fprintf(digest, "%s\x00%x\x00", file, sha256.Sum256(text))
					}
				}
			}
		}
	}

	return hex.EncodeToString(digest.Sum(nil))[:16]
}

func foldersUpFrom(folder string) []string {
	parent := filepath.Dir(folder)
	if parent == folder {
		return []string{folder}
	}

	return append([]string{folder}, foldersUpFrom(parent)...)
}

func readManifests(folder string, versions map[string]map[string]string) {
	add := func(ecosystem, name, version string) {
		if versions[ecosystem] == nil {
			versions[ecosystem] = map[string]string{}
		}
		if name = strings.ToLower(name); versions[ecosystem][name] == "" {
			versions[ecosystem][name] = strings.TrimPrefix(version, "v")
		}
	}
	if held, read := composerPackages(folder); read && versions[Composer] == nil {
		versions[Composer] = map[string]string{}
		if floor := phpFloor(folder); floor != "" {
			add(Composer, "php", floor)
		}
		for _, each := range held {
			add(Composer, each.Name, each.Version)
			for _, stands := range slices.Concat(slices.Collect(maps.Keys(each.Replace)), slices.Collect(maps.Keys(each.Provide))) {
				add(Composer, stands, each.Version)
			}
		}
	}
	for _, requirements := range []string{"requirements.txt", "poetry.lock", "uv.lock"} {
		if text, err := os.ReadFile(filepath.Join(folder, requirements)); err == nil {
			if versions[PyPI] == nil {
				versions[PyPI] = map[string]string{}
			}
			for name, version := range pythonPins(string(text)) {
				add(PyPI, name, version)
			}
		}
	}
	projects, _ := filepath.Glob(filepath.Join(folder, "*.csproj"))
	for _, project := range projects {
		text, err := os.ReadFile(project)
		if err != nil {
			continue
		}
		var read struct {
			Groups []struct {
				References []struct {
					Include string `xml:"Include,attr"`
					Version string `xml:"Version,attr"`
				} `xml:"PackageReference"`
			} `xml:"ItemGroup"`
		}
		if xml.Unmarshal(text, &read) == nil {
			if versions[NuGet] == nil {
				versions[NuGet] = map[string]string{}
			}
			for _, group := range read.Groups {
				for _, reference := range group.References {
					add(NuGet, reference.Include, reference.Version)
				}
			}
		}
	}
}

var pythonPin = regexp.MustCompile(`(?m)^\s*([A-Za-z0-9_.\-]+)\s*==\s*([0-9][^\s;#]*)`)
var poetryPin = regexp.MustCompile(`(?m)^name\s*=\s*"([^"]+)"\s*\nversion\s*=\s*"([^"]+)"`)

// pythonPins is each package a requirements file pins with ==, or a poetry or uv lock lists, with its version.
func pythonPins(text string) map[string]string {
	pins := map[string]string{}
	for _, pin := range slices.Concat(pythonPin.FindAllStringSubmatch(text, -1), poetryPin.FindAllStringSubmatch(text, -1)) {
		pins[strings.ReplaceAll(pin[1], "_", "-")] = pin[2]
	}

	return pins
}

// InVersionRange says whether a version meets every constraint of a range written as constraints apart by spaces,
// each >=, >, <=, <, = or a bare version: ">=6 <10".
func InVersionRange(version, constraints string) bool {
	for _, constraint := range strings.Fields(constraints) {
		operator := constraint[:len(constraint)-len(strings.TrimLeft(constraint, "<>="))]
		compared := compareVersions(version, strings.TrimPrefix(strings.TrimLeft(constraint, "<>="), "v"))
		holds := map[string]bool{">=": compared >= 0, ">": compared > 0, "<=": compared <= 0, "<": compared < 0, "=": compared == 0, "": compared == 0}[operator]
		if !holds {
			return false
		}
	}

	return true
}

func compareVersions(a, b string) int {
	first, second := strings.Split(a, "."), strings.Split(b, ".")
	for at := range max(len(first), len(second)) {
		left, right := versionPart(first, at), versionPart(second, at)
		if left != right {
			if left < right {
				return -1
			}

			return 1
		}
	}

	return 0
}

func versionPart(parts []string, at int) int {
	if at >= len(parts) {
		return 0
	}
	digits := strings.TrimRightFunc(parts[at], func(r rune) bool { return r < '0' || r > '9' })
	number, _ := strconv.Atoi(digits)

	return number
}

// PackageExemption is what a library excuses under one tag: whole classes and their subtypes, classes exactly (not
// their subtypes), methods of a class, methods of any name, and attributes, each named by its resolved symbol.
type PackageExemption struct {
	Tag        string              `json:"tag"`
	Why        string              `json:"why"`
	Classes    []string            `json:"classes,omitempty"`
	Exactly    []string            `json:"exactly,omitempty"`
	On         map[string][]string `json:"on,omitempty"`
	Methods    []string            `json:"methods,omitempty"`
	Attributes []string            `json:"attributes,omitempty"`
}

// PackageIdentity names a type of a library a rule reads by name, such as php-types' option.
type PackageIdentity struct {
	Name string `json:"name"`
	Type string `json:"type"`
}

// ExemptedBy is whether a library the project holds excuses the node under the tag: a declaration of an excused class
// or one of its subtypes, a method an excused class declares, a call bound to one, or an attribute it names. It reads
// only resolved symbols, so a project class that merely shares a name gets no exemption; it is unknown where the
// symbol or the library's version is.
func (m Match) ExemptedBy(tag string) Truth {
	if m.node == nil || m.file == nil {
		return Unknown
	}
	class, method, attribute := m.exemptionSubject()
	answer := False
	for _, facts := range registeredPackageFacts() {
		for _, exemption := range facts.Exemptions {
			if exemption.Tag != tag || !m.excuses(exemption, class, method, attribute) {
				continue
			}
			if held := m.file.codebase.holds(facts); held == True {
				return True
			} else {
				answer = answer.Or(held)
			}
		}
	}

	return answer
}

// exemptionSubject is what an exemption is asked about: a type declaration's own symbol; a function's enclosing type
// and its name; a call's target type and name; an attribute's resolved name.
func (m Match) exemptionSubject() (class, method, attribute string) {
	node := m.node
	switch {
	case node.Kind == "type":
		return node.Symbol, "", ""
	case node.Kind == "function":
		for owner := m.Parent(); owner.Exists(); owner = owner.Parent() {
			if owner.node.Kind == "type" {
				return owner.node.Symbol, node.Name, ""
			}
		}

		return "", node.Name, ""
	case node.Kind == "construction" && node.Resolved != nil && node.Resolved.Name != "":
		return node.Resolved.Name, "", ""
	case node.Target != nil:
		return node.Target.Type, node.Target.Name, ""
	case node.Kind == "call" && node.Refers == "":
		return "", m.writtenCallee(), ""
	case node.Kind == "field":
		for owner := m.Parent(); owner.Exists(); owner = owner.Parent() {
			if owner.node.Kind == "type" {
				return owner.node.Symbol, m.fieldName(), ""
			}
		}
	case node.Kind == "annotation":
		return "", "", node.Refers
	case node.Refers != "":
		return node.Refers, "", ""
	}

	return "", "", ""
}

func (m Match) excuses(exemption PackageExemption, class, method, attribute string) bool {
	if attribute != "" && slices.ContainsFunc(exemption.Attributes, func(named string) bool { return m.declaredBy(attribute, named) }) {
		return true
	}
	if class != "" && slices.ContainsFunc(exemption.Classes, func(named string) bool { return m.declaredBy(class, named) }) {
		return true
	}
	if class != "" && slices.Contains(exemption.Exactly, class) {
		return true
	}
	if method == "" {
		return false
	}
	if slices.Contains(exemption.Methods, method) {
		return true
	}
	for base, methods := range exemption.On {
		if class != "" && slices.Contains(methods, method) && m.declaredBy(class, base) {
			return true
		}
	}

	return false
}

// PackageIdentitiesNamed is every type the facts name an identity by: one for a library's own type, one per language
// for an identity the standard library of each spells its own way, such as the root of every failure.
func PackageIdentitiesNamed(name string) []string {
	var types []string
	for _, facts := range registeredPackageFacts() {
		for _, identity := range facts.Identities {
			if identity.Name == name {
				types = append(types, identity.Type)
			}
		}
	}

	return types
}

// PackageIdentityNamed is the type a library names an identity, when a library the facts hold names it.
func PackageIdentityNamed(name string) (string, bool) {
	for _, facts := range registeredPackageFacts() {
		for _, identity := range facts.Identities {
			if identity.Name == name {
				return identity.Type, true
			}
		}
	}

	return "", false
}

// ExemptionTag is one exemption a library registers: the tag a rule names, why it excuses, and the library.
type ExemptionTag struct {
	Package string
	Tag     string
	Why     string
}

// ExemptionTags are the exemptions every registered library states, by tag.
func ExemptionTags() []ExemptionTag {
	var tags []ExemptionTag
	for _, facts := range registeredPackageFacts() {
		for _, exemption := range facts.Exemptions {
			tags = append(tags, ExemptionTag{facts.Package, exemption.Tag, exemption.Why})
		}
	}
	slices.SortFunc(tags, func(a, b ExemptionTag) int { return strings.Compare(a.Tag+a.Package, b.Tag+b.Package) })

	return tags
}

type composerPackage struct {
	Name    string            `json:"name"`
	Version string            `json:"version"`
	Replace map[string]string `json:"replace"`
	Provide map[string]string `json:"provide"`
}

// composerPackages are the packages a folder's composer.lock and vendor/composer/installed.json name, the second
// written either as composer 2's {"packages": [...]} or composer 1's bare list.
func composerPackages(folder string) ([]composerPackage, bool) {
	var held []composerPackage
	read := false
	if text, err := os.ReadFile(filepath.Join(folder, "composer.lock")); err == nil {
		var lock struct {
			Packages    []composerPackage `json:"packages"`
			PackagesDev []composerPackage `json:"packages-dev"`
		}
		if json.Unmarshal(text, &lock) == nil {
			held, read = slices.Concat(lock.Packages, lock.PackagesDev), true
		}
	}
	if text, err := os.ReadFile(filepath.Join(folder, "vendor", "composer", "installed.json")); err == nil {
		var installed struct {
			Packages []composerPackage `json:"packages"`
		}
		var listed []composerPackage
		if json.Unmarshal(text, &installed) == nil {
			held, read = append(held, installed.Packages...), true
		} else if json.Unmarshal(text, &listed) == nil {
			held, read = append(held, listed...), true
		}
	}

	return held, read
}

func phpFloor(folder string) string {
	text, err := os.ReadFile(filepath.Join(folder, "composer.json"))
	if err != nil {
		return ""
	}
	var manifest struct {
		Require map[string]string `json:"require"`
	}
	if json.Unmarshal(text, &manifest) != nil {
		return ""
	}
	lowest := ""
	for _, written := range phpVersions.FindAllString(manifest.Require["php"], -1) {
		if lowest == "" || VersionBelow(written, lowest) {
			lowest = written
		}
	}

	return lowest
}

var phpVersions = regexp.MustCompile(`\d+(?:\.\d+)*`)

// VersionBelow says whether one dotted version is lower than another, part by part, a missing part read as 0.
func VersionBelow(version, other string) bool {
	ours, theirs := strings.Split(version, "."), strings.Split(other, ".")
	for at := range max(len(ours), len(theirs)) {
		left, right := 0, 0
		if at < len(ours) {
			left, _ = strconv.Atoi(ours[at])
		}
		if at < len(theirs) {
			right, _ = strconv.Atoi(theirs[at])
		}
		if left != right {
			return left < right
		}
	}

	return false
}

// PackagesIn is every package a folder's own manifests say the project holds, by ecosystem, each with its version
// where the manifest states one.
func PackagesIn(folder string) map[string]map[string]string {
	versions := map[string]map[string]string{}
	readManifests(folder, versions)

	return versions
}

func (m Match) receiverType() string {
	callees, _ := m.node.Role("callee")
	for _, callee := range callees {
		if subjects, _ := callee.Role("subject"); len(subjects) == 1 && subjects[0].Resolved != nil {
			return subjects[0].Resolved.Name
		}
	}

	return ""
}

func (m Match) writtenCallee() string {
	callees, _ := m.node.Role("callee")
	for _, callee := range callees {
		if callee.Kind == "reference" {
			return callee.Name
		}
		if members, _ := callee.Role("member"); len(members) == 1 {
			return members[0].Name
		}
	}

	return ""
}

func (m Match) fieldName() string {
	if m.node.Name != "" {
		return m.node.Name
	}
	declarators, _ := m.node.Role("declarators")
	for _, declarator := range declarators {
		if bindings, _ := declarator.Role("binding"); len(bindings) == 1 {
			return bindings[0].Name
		}
	}

	return ""
}

func (m Match) NamesAnAssociation() bool {
	for owner := m.Parent(); owner.Exists(); owner = owner.Parent() {
		switch owner.node.Kind {
		case "call", "annotation", "field":
			return owner.ExemptedBy("association") == True
		case "function", "type", "module", "file":
			return false
		}
	}

	return false
}

func (m Match) writtenCallEffect(effect string) Truth {
	receiver, method := m.writtenReceiver(), m.writtenCallee()
	if receiver == "" || method == "" {
		if m.file.Knows("target") {
			return False
		}

		return Unknown
	}
	answer := False
	for _, facts := range registeredPackageFacts() {
		answering := []string{}
		for _, forward := range facts.Forwards {
			if m.declaredBy(receiver, forward.Type) {
				answering = append(answering, forward.To)
			}
		}
		if !slices.ContainsFunc(facts.Entries, func(entry PackageEntry) bool {
			return entry.Effect == effect && entry.Name == method && (m.declaredBy(receiver, entry.Type) || slices.Contains(answering, entry.Type))
		}) {
			continue
		}
		if held := m.file.codebase.holds(facts); held == True {
			return True
		} else {
			answer = answer.Or(held)
		}
	}

	return answer
}

func (m Match) writtenReceiver() string {
	callees, _ := m.node.Role("callee")
	if len(callees) != 1 || callees[0].Kind != "access" {
		return ""
	}
	subjects, _ := callees[0].Role("subject")
	if len(subjects) != 1 {
		return ""
	}
	subject := subjects[0]
	if callees[0].Form == "static" {
		return subject.Refers
	}
	held := Match{node: subject, file: m.file}
	if typed := held.Typed(); typed != nil && typed.Kind == "named" {
		return typed.Name
	}
	if subject.Kind == "call" && subject.Target == nil {
		return held.forwardedResult()
	}

	return ""
}

func (m Match) forwardedResult() string {
	receiver := m.writtenReceiver()
	if receiver == "" {
		return ""
	}
	for _, facts := range registeredPackageFacts() {
		for _, forward := range facts.Forwards {
			if m.declaredBy(receiver, forward.Type) || m.declaredBy(receiver, forward.To) {
				return forward.To
			}
		}
	}

	return ""
}
