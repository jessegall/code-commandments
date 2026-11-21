// Package laravel holds what a scan reads from a Laravel project beyond its own sources: the routes an installed
// package registers.
package laravel

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
)

const (
	fortifyPackage       = "laravel/fortify"
	fortifyProvider      = `Laravel\Fortify\FortifyServiceProvider`
	fortifyRoutesFile    = "vendor/" + fortifyPackage + "/routes/routes.php"
	fortifyConfigFile    = "config/fortify.php"
	fortifyPackageConfig = "vendor/" + fortifyPackage + "/" + fortifyConfigFile
)

type composerDiscovery struct {
	Extra struct {
		Laravel struct {
			Providers    []string `json:"providers"`
			DontDiscover []string `json:"dont-discover"`
		} `json:"laravel"`
	} `json:"extra"`
}

// RouteContextFiles are the route files of the packages a Laravel project discovers, read beside its own sources as
// context: Fortify's routes when the project installs it and does not opt out of its discovery.
func RouteContextFiles(root string) []string {
	if root == "" {
		return nil
	}
	project, ok := readComposerDiscovery(filepath.Join(root, "composer.json"))
	if !ok || slices.Contains(project.Extra.Laravel.DontDiscover, "*") || slices.Contains(project.Extra.Laravel.DontDiscover, fortifyPackage) {
		return nil
	}
	installed, ok := readComposerDiscovery(filepath.Join(root, "vendor", fortifyPackage, "composer.json"))
	if !ok || !slices.Contains(installed.Extra.Laravel.Providers, fortifyProvider) {
		return nil
	}

	return []string{filepath.Join(root, fortifyRoutesFile), filepath.Join(root, fortifyPackageConfig)}
}

func readComposerDiscovery(path string) (composerDiscovery, bool) {
	var manifest composerDiscovery
	content, err := os.ReadFile(path)
	if err != nil {
		return manifest, false
	}
	err = json.Unmarshal(content, &manifest)

	return manifest, err == nil
}
