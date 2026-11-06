package python

import (
	"path/filepath"
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

// packageGraph is each import between two packages of the program: where it is written, its package's folder, and the folder of the package it reaches.
type packageGraph struct {
	once   sync.Once
	arrows engine.DependencyArrows
}

func (p *Program) PackageArrows() engine.DependencyArrows {
	p.packages.once.Do(func() {
		packages := map[string]bool{}
		for _, module := range p.modules {
			if filepath.Base(module.File.Path) == "__init__.py" {
				packages[filepath.Dir(module.File.Path)] = true
			}
		}
		for _, module := range p.modules {
			from := filepath.Dir(module.File.Path)
			if !packages[from] || module.File.Match(0).IsTest() {
				continue
			}
			for _, imported := range module.Imports() {
				if to := filepath.Dir(imported.Module.File.Path); to != from && packages[to] {
					p.packages.arrows = append(p.packages.arrows, engine.DependencyArrow{At: imported.Statement.Match, From: from, To: to})
				}
			}
		}
	})

	return p.packages.arrows
}

func (p *Program) WouldCloseACycle(referrer, target *Module) bool {
	from, to := filepath.Dir(referrer.File.Path), filepath.Dir(target.File.Path)

	return from != to && p.PackageArrows().Has(to, from)
}
