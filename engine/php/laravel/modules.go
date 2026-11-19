package laravel

import (
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// Modules is every module of a codebase that registers itself: a module holding exactly one service provider of its own.
type Modules struct {
	registered map[string]bool
}

var modules = engine.Memoised(readModules)

func ModulesOf(codebase *engine.Codebase) *Modules {
	return modules.Of(codebase)
}

func (m *Modules) Registers(module string) bool {
	return m.registered[module]
}

func readModules(codebase *engine.Codebase) *Modules {
	program := php.ProgramOf(codebase)
	providers := map[string]int{}
	for name := range program.Declarations() {
		if program.Extends(name, ServiceProvider) {
			providers[php.ModuleOf(name)]++
		}
	}
	read := &Modules{registered: map[string]bool{}}
	for module, count := range providers {
		read.registered[module] = count == 1
	}

	return read
}

func (n Node) JoinsTwoRegisteredModules() bool {
	modules := ModulesOf(n.Codebase())
	used := php.Written(n.Node().Declared).Class()

	return modules.Registers(php.ModuleOf(php.EnclosingClassName(n.Match))) && modules.Registers(php.ModuleOf(used))
}
