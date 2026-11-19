package php

import (
	"path"
	"path/filepath"
	"strconv"
	"strings"
	"unicode"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) SpelledFolder() (engine.SpelledFolder, bool) {
	switch {
	case n.Kind() == "Expr_BinaryOp_Concat" && n.Parent().Kind() != "Expr_BinaryOp_Concat":
		return n.concatenatedFolder()
	case n.Kind() == "Scalar_String" && n.isPathFunctionArgument():
		text, ok := literalText(n.Match)
		if !ok || strings.HasPrefix(text, "/") {
			return engine.SpelledFolder{}, false
		}

		return foldersBelow(n.pathFunctionRoot(), text)
	}

	return engine.SpelledFolder{}, false
}

func (n Node) isPathFunctionArgument() bool {
	return n.Parent().Kind() == "Arg" && n.Parent().Parent().Kind() == "Expr_FuncCall"
}

func (n Node) pathFunctionRoot() string {
	return n.Parent().Parent().Child("name").Name() + "()"
}

func (n Node) concatenatedFolder() (engine.SpelledFolder, bool) {
	parts := concatenatedParts(n.Match)
	root, rooted := pathRoot(parts[0])
	if !rooted && n.isPathFunctionArgument() {
		root, rooted, parts = n.pathFunctionRoot(), true, append([]engine.Match{{}}, parts...)
	}
	if !rooted {
		return engine.SpelledFolder{}, false
	}
	var written strings.Builder
	for _, part := range parts[1:] {
		text, literal := literalText(part)
		switch {
		case literal:
			written.WriteString(text)
		case part.Kind() == "Expr_ConstFetch" && part.Child("name").Name() == "DIRECTORY_SEPARATOR":
			written.WriteString("/")
		default:
			return foldersBelow(root, written.String())
		}
	}

	return foldersBelow(root, written.String())
}

func pathRoot(part engine.Match) (string, bool) {
	if directory, ok := resolvedDirectory(part); ok {
		return directory, true
	}
	if _, literal := literalText(part); literal {
		return "", false
	}

	return part.Written(), true
}

func resolvedDirectory(part engine.Match) (string, bool) {
	switch {
	case part.Kind() == "Scalar_MagicConst_Dir":
		return filepath.ToSlash(filepath.Dir(part.File())), true
	case Node{Match: part}.CallsFunction("dirname"):
		inner, ok := resolvedDirectory(Node{Match: part}.Argument(0).Match)
		levels := 1
		if level, given := literalText(Node{Match: part}.Argument(1).Match); given {
			levels, _ = strconv.Atoi(level)
		}
		for ; ok && levels > 0; levels-- {
			inner = path.Dir(inner)
		}

		return inner, ok
	}

	return "", false
}

func foldersBelow(root, written string) (engine.SpelledFolder, bool) {
	if !readsAsPath(written) {
		return engine.SpelledFolder{}, false
	}
	segments := engine.PathSegments(written)
	for len(segments) > 0 && segments[0] == ".." {
		root, segments = path.Dir(root), segments[1:]
	}
	if !strings.HasSuffix(written, "/") {
		segments = segments[:max(len(segments)-1, 0)]
	}
	folder := engine.CleanPath(segments)
	if len(folder) == 0 {
		return engine.SpelledFolder{}, false
	}

	return engine.SpelledFolder{Root: root, Folder: folder}, true
}

func concatenatedParts(node engine.Match) []engine.Match {
	if node.Kind() != "Expr_BinaryOp_Concat" {
		return []engine.Match{node}
	}

	return append(concatenatedParts(node.Child("left")), concatenatedParts(node.Child("right"))...)
}

func readsAsPath(text string) bool {
	if strings.Contains(text, "://") || strings.IndexFunc(text, unicode.IsSpace) >= 0 || strings.ContainsAny(text, "?#=%@:<>|*\"'") {
		return false
	}

	return strings.Contains(strings.Trim(text, "/"), "/") || strings.HasPrefix(text, "/") && len(text) > 1
}
