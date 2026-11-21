package csharp_test

import (
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

var fixture struct {
	once     sync.Once
	root     string
	codebase *engine.Codebase
	err      error
}

type goldenType struct {
	Name     string   `json:"name"`
	Nullable bool     `json:"nullable"`
	Value    bool     `json:"value"`
	Inner    []string `json:"inner"`
}

type goldenTarget struct {
	Type       string   `json:"type"`
	Name       string   `json:"name"`
	Parameters []string `json:"parameters"`
}

type goldenFlow struct {
	State         map[string]string   `json:"state"`
	ReadsByMember map[string][]string `json:"readsByMember"`
	Nullable      []string            `json:"nullable"`
	Assigned      map[string][]string `json:"assigned"`
	Reads         map[string]int      `json:"reads"`
	Coupled       bool                `json:"coupled"`
}

type goldenMethod struct {
	Callers   []string `json:"callers"`
	HandedOut bool     `json:"handedOut"`
	Envied    string   `json:"envied"`
	Unpacks   bool     `json:"unpacks"`
}

type goldenDeclaration struct {
	Declared bool     `json:"declared"`
	Record   bool     `json:"record"`
	Enum     []string `json:"enum"`
	Cased    bool     `json:"cased"`
}
