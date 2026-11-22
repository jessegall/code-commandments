package engine

import (
	"encoding/json"
	"encoding/xml"
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
		if m.file.Knows("target") {
			return False
		}

		return Unknown
	}
	answer := False
	for _, facts := range registeredPackageFacts() {
		if !slices.ContainsFunc(facts.Entries, func(entry PackageEntry) bool {
			return entry.Effect == effect && entry.Name == target.Name && m.declaredBy(target.Type, entry.Type)
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
func (m Match) declaredBy(declaring, named string) bool {
	if declaring == named {
		return true
	}
	types, typed := m.Types()

	return typed && IsA(types, declaring, named)
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
	if text, err := os.ReadFile(filepath.Join(folder, "composer.lock")); err == nil {
		var lock struct {
			Packages    []struct{ Name, Version string } `json:"packages"`
			PackagesDev []struct{ Name, Version string } `json:"packages-dev"`
		}
		if json.Unmarshal(text, &lock) == nil {
			versions[Composer] = map[string]string{}
			for _, each := range slices.Concat(lock.Packages, lock.PackagesDev) {
				add(Composer, each.Name, each.Version)
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
	case node.Kind == "attribute":
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
