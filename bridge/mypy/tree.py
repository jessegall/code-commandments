"""A Python project as one generic tree stream (contract/CONTRACT.md), typed by mypy.

`python tree.py <path>...` writes the stream for every module under the paths. `python tree.py --serve` answers one
JSON request per stdin line ({"paths", "write", "python"}, as bridge.py takes it) with a full stream, header to
trailer, holding the checked project in memory between requests. A module outside `write` informs the types and is
written with `context: true`. Types come from bridge.py's mypy session, joined to nodes by exact span.
"""

from __future__ import annotations

import ast
import io
import json
import os
import sys
import tokenize
from typing import Iterator

from mypy.version import __version__ as MYPY_VERSION

from bridge import Session, spans, states

OPERATORS = {
    ast.Add: "+", ast.Sub: "-", ast.Mult: "*", ast.Div: "/", ast.FloorDiv: "//", ast.Mod: "%", ast.Pow: "**",
    ast.MatMult: "@", ast.LShift: "<<", ast.RShift: ">>", ast.BitOr: "|", ast.BitAnd: "&", ast.BitXor: "^",
    ast.And: "and", ast.Or: "or", ast.Not: "not", ast.Invert: "~", ast.UAdd: "+", ast.USub: "-",
    ast.Eq: "==", ast.NotEq: "!=", ast.Lt: "<", ast.LtE: "<=", ast.Gt: ">", ast.GtE: ">=",
    ast.Is: "is", ast.IsNot: "is not", ast.In: "in", ast.NotIn: "not in",
}
SKIPPED = (ast.expr_context, ast.operator, ast.unaryop, ast.cmpop, ast.boolop)
DEFINITIONS = (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)


class TreeWriter:
    def __init__(self, source: bytes, module: str, types: dict[tuple[int, int], dict]) -> None:
        self.source = source
        self.module = module
        self.types = types
        self.joined: set[tuple[int, int]] = set()
        self.starts = [0]
        for index, byte in enumerate(source):
            if byte == 0x0A:
                self.starts.append(index + 1)
        self.spans: list[tuple[int, int, int]] = []
        self.docstrings: list[tuple[ast.Constant, int]] = []
        self.defaults: dict[int, ast.expr] = {}
        self.spreads: set[int] = set()
        self.next = 0

    def offset(self, line: int, column: int) -> int:
        return self.starts[line - 1] + column

    def line_of(self, offset: int) -> int:
        return self.source.count(b"\n", 0, offset) + 1

    def span_of(self, node: ast.AST, children: list[dict]) -> tuple[int, int]:
        """The node's byte span: its own position, from its first decorator, and reaching its last child, as a
        parameter reaches the default the contract nests under it."""
        if isinstance(node, ast.Module):
            return 0, len(self.source)
        if hasattr(node, "lineno") and node.end_lineno is not None:
            start = self.offset(node.lineno, node.col_offset)
            end = self.offset(node.end_lineno, node.end_col_offset)
            decorators = [child for child in children if child["field"] == "decorator_list"]
            if decorators:
                start = self.source.rindex(b"@", 0, decorators[0]["span"][0])
            return start, max([end, *(child["span"][1] for child in children)])
        if children:
            return children[0]["span"][0], children[-1]["span"][1]
        return -1, -1

    def node(self, node: ast.AST, field: str | None, scope: list[str], floor: int = 0) -> dict:
        """The node and its subtree. $floor is where a node without a position of its own sits: the end of its
        previous sibling, or its parent's start."""
        identity = self.next
        self.next += 1
        out: dict = {"id": identity, "kind": type(node).__name__, "role": self.role(node, field)}
        placeholder = len(self.spans)
        self.spans.append((0, 0, identity))
        inner = scope + [node.name] if isinstance(node, DEFINITIONS) else scope
        children = []
        after = self.offset(node.lineno, node.col_offset) if hasattr(node, "lineno") else floor
        for name, child in self.ordered(node):
            children.append(self.node(child, name, inner, after))
            after = children[-1]["span"][1]
        start, end = self.span_of(node, children)
        if start < 0:
            start = end = floor
        self.spans[placeholder] = (start, end, identity)
        answers = self.neutral(node)
        if answers:
            out["is"] = answers
        out["span"] = [start, end, self.line_of(start)]
        if field is not None:
            out["field"] = field
        out.update(self.facts(node, field, scope, (start, end)))
        if children:
            out["children"] = children
        docstring = ast.get_docstring(node, clean=False) if isinstance(node, (*DEFINITIONS, ast.Module)) else None
        if docstring is not None:
            self.docstrings.append((node.body[0].value, identity))
        return out

    def ordered(self, node: ast.AST) -> list[tuple[str, ast.AST]]:
        """The node's children with their fields, in source order: `ast` keeps its own field order, which puts an
        `IfExp`'s test before its body and a `Dict`'s keys before its values. A child with no position anywhere
        in it keeps its place after the child before it."""
        children = [(name, child) for name, value in self.fields(node)
                    for child in (value if isinstance(value, list) else [value])
                    if isinstance(child, ast.AST) and not isinstance(child, SKIPPED)]
        keys, last = [], (-1, -1)
        for _, child in children:
            last = self.position(child) or last
            keys.append(last)
        return [pair for _, _, pair in sorted(zip(keys, range(len(children)), children))]

    def fields(self, node: ast.AST) -> list[tuple[str, object]]:
        """The node's `ast` fields, as the contract nests them: a parameter's default is its own `default` child, not
        an entry in a list beside the parameters, and a `**` entry of a dict is flagged where `ast` leaves its key
        `None`."""
        if isinstance(node, ast.arguments):
            positional = [*node.posonlyargs, *node.args]
            paired = zip(positional[len(positional) - len(node.defaults):], node.defaults)
            for parameter, default in [*paired, *zip(node.kwonlyargs, node.kw_defaults)]:
                if default is not None:
                    self.defaults[id(parameter)] = default
            return [(name, value) for name, value in ast.iter_fields(node) if name not in ("defaults", "kw_defaults")]
        if isinstance(node, ast.Dict):
            self.spreads.update(id(value) for key, value in zip(node.keys, node.values) if key is None)
        fields = list(ast.iter_fields(node))
        if isinstance(node, ast.arg) and id(node) in self.defaults:
            fields.append(("default", self.defaults[id(node)]))
        return fields

    def position(self, node: ast.AST) -> tuple[int, int] | None:
        """Where the node's source starts: its own position, else the earliest one inside it."""
        if hasattr(node, "lineno"):
            return node.lineno, node.col_offset
        found = [(inner.lineno, inner.col_offset) for inner in ast.walk(node) if hasattr(inner, "lineno")]
        return min(found) if found else None

    def role(self, node: ast.AST, field: str | None) -> str:
        if isinstance(node, DEFINITIONS):
            return "member"
        if field in ("annotation", "returns"):
            return "type"
        if isinstance(node, ast.stmt):
            return "statement"
        if isinstance(node, ast.expr):
            return "expression"
        if isinstance(node, ast.pattern):
            return "pattern"
        return "other"

    def neutral(self, node: ast.AST) -> list[str]:
        answers = {
            "function": isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef, ast.Lambda)),
            "type-declaration": isinstance(node, ast.ClassDef),
            "parameter": isinstance(node, ast.arg),
            "branch": isinstance(node, (ast.If, ast.Match, ast.IfExp)),
            "loop": isinstance(node, (ast.For, ast.AsyncFor, ast.While)),
            "return": isinstance(node, ast.Return),
            "throw": isinstance(node, ast.Raise),
            "bail-out": isinstance(node, (ast.Return, ast.Raise, ast.Break, ast.Continue)),
            "expression-statement": isinstance(node, ast.Expr),
            "call": isinstance(node, ast.Call),
            "member-access": isinstance(node, ast.Attribute),
            "self-reference": isinstance(node, ast.Name) and node.id in ("self", "cls"),
            "identifier": isinstance(node, ast.Name) and node.id not in ("self", "cls"),
            "assignment": isinstance(node, (ast.Assign, ast.AugAssign, ast.AnnAssign, ast.NamedExpr)),
            "comparison": isinstance(node, ast.Compare),
            "literal": isinstance(node, (ast.Constant, ast.JoinedStr)),
            "import": isinstance(node, (ast.Import, ast.ImportFrom)),
            "catch": isinstance(node, ast.ExceptHandler),
        }
        return [name for name, yes in answers.items() if yes]

    def facts(self, node: ast.AST, field: str | None, scope: list[str], span: tuple[int, int]) -> dict:
        facts: dict = {}
        name = self.name_of(node)
        if name is not None:
            facts["name"] = name
        facts.update(self.literal(node))
        operator = self.operator(node)
        if operator is not None:
            facts["operator"] = operator
        flags = self.flags(node, field, span[0])
        if flags:
            facts["flags"] = flags
        if isinstance(node, ast.arg) and node.annotation is not None:
            facts["declared"] = self.type(node.annotation)
        if isinstance(node, ast.AnnAssign):
            facts["declared"] = self.type(node.annotation)
        if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)) and node.returns is not None:
            facts["returns"] = self.type(node.returns)
        if isinstance(node, DEFINITIONS):
            facts["symbol"] = ".".join([self.module, *scope, node.name])
        if isinstance(node, ast.expr) and span in self.types:
            facts["resolved"] = self.resolved(self.types[span])
            self.joined.add(span)
        if isinstance(node, ast.Compare) and len(node.ops) > 1:
            facts["extras"] = {"python": {"operators": [OPERATORS[type(op)] for op in node.ops]}}
        if isinstance(node, ast.ImportFrom) and node.level:
            facts["extras"] = {"python": {"level": node.level}}
        if isinstance(node, ast.alias) and node.asname is not None:
            facts["extras"] = {"python": {"as": node.asname}}
        if isinstance(node, (ast.Global, ast.Nonlocal)):
            facts["extras"] = {"python": {"names": list(node.names)}}
        return facts

    def name_of(self, node: ast.AST) -> str | None:
        if isinstance(node, ast.Name):
            return node.id
        if isinstance(node, (*DEFINITIONS, ast.alias)):
            return node.name
        if isinstance(node, ast.arg):
            return node.arg
        if isinstance(node, ast.Attribute):
            return node.attr
        if isinstance(node, ast.keyword) and node.arg is not None:
            return node.arg
        if isinstance(node, ast.ImportFrom):
            return node.module
        if isinstance(node, (ast.ExceptHandler, ast.MatchAs, ast.MatchStar)):
            return node.name
        if isinstance(node, ast.MatchMapping):
            return node.rest
        return None

    def literal(self, node: ast.AST) -> dict:
        if isinstance(node, ast.JoinedStr):
            return {"literal": "interpolated"}
        if not isinstance(node, ast.Constant):
            return {}
        value = node.value
        if isinstance(value, bool):
            return {"literal": "bool", "value": value}
        if value is None:
            return {"literal": "null", "value": None}
        if value is Ellipsis:
            return {"literal": "ellipsis"}
        if isinstance(value, bytes):
            return {"literal": "bytes"}
        if isinstance(value, int):
            return {"literal": "int", "value": str(value)}
        if isinstance(value, float):
            return {"literal": "float", "value": repr(value)}
        return {"literal": "string", "value": value}

    def operator(self, node: ast.AST) -> str | None:
        if isinstance(node, (ast.BinOp, ast.AugAssign, ast.UnaryOp, ast.BoolOp)):
            return OPERATORS[type(node.op)]
        if isinstance(node, ast.Compare) and len(node.ops) == 1:
            return OPERATORS[type(node.ops[0])]
        if isinstance(node, ast.Assign):
            return "="
        if isinstance(node, ast.FormattedValue) and node.conversion != -1:
            return "!" + chr(node.conversion)
        return None

    def flags(self, node: ast.AST, field: str | None, start: int) -> list[str]:
        flags = []
        if isinstance(node, ast.arg) and field in ("vararg", "kwarg", "kwonlyargs"):
            flags.append({"vararg": "variadic", "kwarg": "keywords", "kwonlyargs": "keyword-only"}[field])
        if isinstance(node, ast.If) and field == "orelse" and self.source.startswith(b"elif", start):
            flags.append("elif")
        if isinstance(node, (ast.AsyncFunctionDef, ast.AsyncFor, ast.AsyncWith)):
            flags.append("async")
        if isinstance(node, ast.comprehension) and node.is_async:
            flags.append("async")
        if isinstance(node, ast.Starred) or id(node) in self.spreads:
            flags.append("spread")
        if isinstance(node, ast.keyword):
            flags.append("named" if node.arg is not None else "spread")
        if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)) and any(
            isinstance(inner, (ast.Yield, ast.YieldFrom)) for inner in ast.walk(node)
        ):
            flags.append("generator")
        if isinstance(node, ast.TryStar):
            flags.append("group")
        return flags

    def type(self, annotation: ast.expr) -> dict:
        text = ast.get_source_segment(self.source.decode(), annotation) or ast.unparse(annotation)
        if isinstance(annotation, ast.Constant) and annotation.value is None:
            return {"text": text, "kind": "keyword", "name": "None", "nullable": True, "origin": "written"}
        if isinstance(annotation, ast.Constant) and isinstance(annotation.value, str):
            return {"text": text, "kind": "named", "name": annotation.value, "origin": "written"}
        if isinstance(annotation, ast.BinOp) and isinstance(annotation.op, ast.BitOr):
            members = self.union_members(annotation)
            out = {"text": text, "kind": "union", "members": members, "origin": "written"}
            if any(member.get("name") == "None" for member in members):
                out["nullable"] = True
            return out
        if isinstance(annotation, ast.Subscript):
            base = self.type(annotation.value)
            items = annotation.slice.elts if isinstance(annotation.slice, ast.Tuple) else [annotation.slice]
            return {"text": text, "kind": "named", "name": base["name"], "args": [self.type(item) for item in items], "origin": "written"}
        if isinstance(annotation, (ast.Name, ast.Attribute)):
            return {"text": text, "kind": "named", "name": ast.unparse(annotation), "origin": "written"}
        return {"text": text, "kind": "opaque", "origin": "written"}

    def union_members(self, annotation: ast.expr) -> list[dict]:
        if isinstance(annotation, ast.BinOp) and isinstance(annotation.op, ast.BitOr):
            return self.union_members(annotation.left) + self.union_members(annotation.right)
        return [self.type(annotation)]

    def resolved(self, fact: dict) -> dict:
        out: dict = {"text": fact["type"], "kind": "named" if "class" in fact else "opaque"}
        if "class" in fact:
            out["name"] = fact["class"]
        if fact.get("nullable"):
            out["nullable"] = True
        if "constructs" in fact:
            out["constructs"] = fact["constructs"]
        out["origin"] = "compiler"
        return out

    def comments(self) -> list[dict]:
        found: list[tuple[int, int, str]] = []
        text = self.source.decode()
        lines = text.splitlines(keepends=True)
        for token in tokenize.generate_tokens(io.StringIO(text).readline):
            if token.type != tokenize.COMMENT:
                continue
            line, column = token.start
            start = self.offset(line, len(lines[line - 1][:column].encode()))
            found.append((start, start + len(token.string.encode()), "line"))
        for literal, owner in self.docstrings:
            start = self.offset(literal.lineno, literal.col_offset)
            found.append((start, self.offset(literal.end_lineno, literal.end_col_offset), f"doc:{owner}"))
        self.comment_ends = {start: end for start, end, _ in found}
        comments = []
        for start, end, kind in sorted(found):
            comment = {"id": len(comments), "kind": "doc" if kind.startswith("doc") else "line",
                       "text": self.source[start:end].decode(), "span": [start, end, self.line_of(start)]}
            comment.update({"attached": int(kind[4:])} if kind.startswith("doc") else self.attachment(start, end))
            if not kind.startswith("doc") and is_code(comment["text"][1:]):
                comment["extras"] = {"python": {"code": True}}
            comments.append(comment)
        return comments

    def attachment(self, start: int, end: int) -> dict:
        line_start = self.source.rfind(b"\n", 0, start) + 1
        if self.source[line_start:start].strip():
            owner = self.outermost(lambda s, e: line_start < e <= start)
            return {"trailing": True} if owner is None else {"attached": owner, "trailing": True}
        after = end
        while True:
            while after < len(self.source) and self.source[after:after + 1] in (b" ", b"\t", b"\r", b"\n"):
                after += 1
            if after not in self.comment_ends:
                break
            after = self.comment_ends[after]
        owner = self.outermost(lambda s, e: s == after)
        return {} if owner is None else {"attached": owner}

    def outermost(self, matches) -> int | None:
        for start, end, identity in self.spans:
            if matches(start, end):
                return identity
        return None



def is_code(words: str) -> bool:
    """Whether a comment's words read as one Python statement from end to end, `total += rate` or `return
    order.total`, rather than prose: words strung together parse as several statements or none, and a lone name
    is a label, not code."""
    text = words.strip()
    try:
        body = ast.parse(text).body if text else []
    except (SyntaxError, ValueError):
        return False
    if len(body) != 1 or body[0].end_lineno != text.count("\n") + 1 or body[0].end_col_offset != len(text.split("\n")[-1].encode()):
        return False
    return not (isinstance(body[0], ast.Expr) and isinstance(body[0].value, ast.Name))


SKIPPED_FOLDERS = {"vendor", "node_modules", "site-packages", "__pycache__"}


def python_files(paths: list[str]) -> list[str]:
    """Every `.py` file under the paths, resolved, in walk order: a folder that is a link, hidden, a dependency
    store, bytecode, an `.egg-info` or a virtual environment is not walked. A module a package shadows is still
    one, so it is written even where mypy leaves it out."""
    found: list[str] = []
    for root in paths:
        if os.path.isfile(root):
            found.append(os.path.realpath(root))
            continue
        for folder, subfolders, names in os.walk(root):
            subfolders[:] = sorted(sub for sub in subfolders if walks(os.path.join(folder, sub)))
            found += [os.path.realpath(os.path.join(folder, name)) for name in sorted(names) if name.endswith(".py")]
    return list(dict.fromkeys(found))


def walks(folder: str) -> bool:
    name = os.path.basename(folder)
    return not (os.path.islink(folder) or name.startswith(".") or name in SKIPPED_FOLDERS or name.endswith(".egg-info")
                or os.path.isfile(os.path.join(folder, "pyvenv.cfg")))


def module_name(path: str) -> str:
    """The dotted name Python imports the file by: its path from the top of its outermost package."""
    parts = [] if os.path.basename(path) == "__init__.py" else [os.path.basename(path)[:-3]]
    folder = os.path.dirname(path)
    while os.path.isfile(os.path.join(folder, "__init__.py")):
        parts.insert(0, os.path.basename(folder))
        folder = os.path.dirname(folder)
    return ".".join(parts)


def packages(path: str) -> list[str]:
    """Every folder holding an __init__.py, from the file's own up."""
    found = []
    folder = os.path.dirname(path)
    while os.path.isfile(os.path.join(folder, "__init__.py")):
        found.append(folder)
        folder = os.path.dirname(folder)
    return found


def unparsed(source: bytes) -> dict:
    """The root of a file Python's parser refused: an empty module over the whole file."""
    return {"id": 0, "kind": "Module", "role": "other", "span": [0, len(source), 1]}


def stream(session: Session, paths: list[str], write: list[str], python: str | None) -> Iterator[dict]:
    """The contract's lines for the modules under $paths: the header, one file line each, the program, the trailer."""
    yield {"header": {"contract": "tree", "version": 2, "language": "python",
                      "bridge": {"name": "mypy-bridge", "version": MYPY_VERSION}, "roots": [os.path.realpath(p) for p in paths]}}
    found, types, graph = session.checked(paths, python)
    judged = {os.path.realpath(p) for p in write}
    named = {os.path.realpath(s.path): s.module for s in found if s.path}
    checked = states(graph, set(named))
    seen = resolved = unjoined = files = 0
    folders: list[str] = []
    for path in python_files(paths):
        text = open(path, "rb").read()
        state = checked.get(path)
        count, typed = spans(path, state, types) if state is not None else (0, {})
        seen, resolved = seen + count, resolved + len(typed)
        module = named.get(path) or module_name(path)
        writer = TreeWriter(text, module, typed)
        line: dict = {"path": path, "language": "python", "errors": 0}
        try:
            root = writer.node(ast.parse(text), None, [])
        except SyntaxError:
            line["errors"], root = 1, unparsed(text)
        if write and path not in judged:
            line["context"] = True
        line.update({"module": module, "resolver": {"tool": "mypy", "ran": state is not None and state.tree is not None},
                     "root": root, "comments": writer.comments() if not line["errors"] else []})
        yield {"file": line}
        files += 1
        unjoined += sum(1 for span in typed if span not in writer.joined)
        folders += [folder for folder in packages(path) if folder not in folders]
    yield {"program": {"packages": sorted(folders)}}
    yield {"trailer": {"files": files, "resolution": {"expressions": seen, "typed": resolved, "unjoined": unjoined}}}


def emit(lines: Iterator[dict]) -> None:
    for line in lines:
        sys.stdout.write(json.dumps(line, ensure_ascii=False, separators=(",", ":")) + "\n")
    sys.stdout.flush()


def main(argv: list[str]) -> int:
    session = Session()
    if "--serve" in argv:
        for request in sys.stdin:
            asked = json.loads(request)
            emit(stream(session, asked.get("paths", []), asked.get("write", []), asked.get("python")))
        return 0
    emit(stream(session, [arg for arg in argv if not arg.startswith("--")], [], None))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
