"""A Python project as one generic tree stream (contract/CONTRACT.md), typed by mypy.

`python tree.py <path>...` writes the stream for every module under the paths. `python tree.py --serve` answers one
JSON request per stdin line ({"paths", "write", "python"}) with a full stream, header to
trailer, holding the checked project in memory between requests. A module outside `write` informs the types and is
written with `context: true`. Types come from session.py's mypy session, joined to nodes by exact span.
"""

from __future__ import annotations

import ast
import io
import json
import os
import sys
import tokenize
from dataclasses import dataclass
from typing import Iterator

from mypy.version import __version__ as MYPY_VERSION

from session import ResolvedType, Session, TypedModule, spans, states

OPERATORS = {
    ast.Add: "+", ast.Sub: "-", ast.Mult: "*", ast.Div: "/", ast.FloorDiv: "//", ast.Mod: "%", ast.Pow: "**",
    ast.MatMult: "@", ast.LShift: "<<", ast.RShift: ">>", ast.BitOr: "|", ast.BitAnd: "&", ast.BitXor: "^",
    ast.And: "and", ast.Or: "or", ast.Not: "not", ast.Invert: "~", ast.UAdd: "+", ast.USub: "-",
    ast.Eq: "==", ast.NotEq: "!=", ast.Lt: "<", ast.LtE: "<=", ast.Gt: ">", ast.GtE: ">=",
    ast.Is: "is", ast.IsNot: "is not", ast.In: "in", ast.NotIn: "not in",
}
SKIPPED = (ast.expr_context, ast.operator, ast.unaryop, ast.cmpop, ast.boolop)
DEFINITIONS = (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)

ABSENT = object()
"""A value the contract leaves out: distinct from `None`, which a `null` literal writes."""


def written(pairs: tuple[tuple[str, object], ...]) -> dict[str, object]:
    """The pairs present, in order, as the contract's object."""
    return {key: value for key, value in pairs if value is not ABSENT}


@dataclass(frozen=True)
class Span:
    """Where a node or a comment sits: `[start, end)` in bytes, and the line it starts on."""

    start: int
    end: int
    line: int

    def to_json(self) -> list[int]:
        return [self.start, self.end, self.line]


@dataclass(frozen=True)
class Literal:
    """What a literal is, and its value when the contract writes one — `None` for a `null`."""

    kind: str
    value: object = ABSENT


@dataclass(frozen=True)
class WrittenType:
    """A type as an annotation writes it: a name, `None`, a union, a subscripted name, or something opaque."""

    text: str
    kind: str
    name: str | None = None
    members: tuple[WrittenType, ...] | None = None
    args: tuple[WrittenType, ...] | None = None
    nullable: bool = False

    def to_json(self) -> dict[str, object]:
        """The type as the contract writes it: a union's nullability, read off its members, after the rest."""
        own = self.nullable and self.members is None
        return written((
            ("text", self.text),
            ("kind", self.kind),
            ("name", ABSENT if self.name is None else self.name),
            ("members", ABSENT if self.members is None else [member.to_json() for member in self.members]),
            ("args", ABSENT if self.args is None else [arg.to_json() for arg in self.args]),
            ("nullable", True if own else ABSENT),
            ("origin", "written"),
            ("nullable", True if self.nullable and not own else ABSENT),
        ))


@dataclass(frozen=True)
class Facts:
    """What the contract says of a node beyond its shape: its name, literal, operator, flags, types, symbol and
    the Python it keeps in `extras`."""

    name: str | None = None
    literal: Literal | None = None
    operator: str | None = None
    flags: tuple[str, ...] = ()
    declared: WrittenType | None = None
    returns: WrittenType | None = None
    symbol: str | None = None
    resolved: ResolvedType | None = None
    extras: dict[str, object] | None = None

    def pairs(self) -> tuple[tuple[str, object], ...]:
        return (
            ("name", ABSENT if self.name is None else self.name),
            ("literal", ABSENT if self.literal is None else self.literal.kind),
            ("value", ABSENT if self.literal is None else self.literal.value),
            ("operator", ABSENT if self.operator is None else self.operator),
            ("flags", list(self.flags) if self.flags else ABSENT),
            ("declared", ABSENT if self.declared is None else self.declared.to_json()),
            ("returns", ABSENT if self.returns is None else self.returns.to_json()),
            ("symbol", ABSENT if self.symbol is None else self.symbol),
            ("resolved", ABSENT if self.resolved is None else self.resolved.to_json()),
            ("extras", ABSENT if self.extras is None else {"python": self.extras}),
        )


@dataclass(frozen=True)
class Node:
    """One node of the contract's tree, with its subtree."""

    id: int
    kind: str
    role: str
    span: Span
    answers: tuple[str, ...] = ()
    field: str | None = None
    facts: Facts = Facts()
    children: tuple[Node, ...] = ()

    def to_json(self) -> dict[str, object]:
        return written((
            ("id", self.id),
            ("kind", self.kind),
            ("role", self.role),
            ("is", list(self.answers) if self.answers else ABSENT),
            ("span", self.span.to_json()),
            ("field", ABSENT if self.field is None else self.field),
            *self.facts.pairs(),
            ("children", [child.to_json() for child in self.children] if self.children else ABSENT),
        ))


@dataclass(frozen=True)
class Attachment:
    """Where a comment belongs: the node it is attached to, when one owns it, and whether it trails code."""

    owner: int | None
    trailing: bool = False


@dataclass(frozen=True)
class Comment:
    """One comment of a module as the contract writes it."""

    id: int
    kind: str
    text: str
    span: Span
    attachment: Attachment
    code: bool = False

    def to_json(self) -> dict[str, object]:
        return written((
            ("id", self.id),
            ("kind", self.kind),
            ("text", self.text),
            ("span", self.span.to_json()),
            ("attached", ABSENT if self.attachment.owner is None else self.attachment.owner),
            ("trailing", True if self.attachment.trailing else ABSENT),
            ("extras", {"python": {"code": True}} if self.code else ABSENT),
        ))


@dataclass(frozen=True)
class FileLine:
    """One module as the stream's file line writes it."""

    path: str
    errors: int
    context: bool
    module: str
    resolved: bool
    root: Node
    comments: tuple[Comment, ...]

    def to_json(self) -> dict[str, object]:
        return written((
            ("path", self.path),
            ("language", "python"),
            ("errors", self.errors),
            ("context", True if self.context else ABSENT),
            ("module", self.module),
            ("resolver", {"tool": "mypy", "ran": self.resolved}),
            ("root", self.root.to_json()),
            ("comments", [comment.to_json() for comment in self.comments]),
        ))


class TreeWriter:
    def __init__(self, source: bytes, module: str, types: dict[tuple[int, int], ResolvedType]) -> None:
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

    def span_of(self, node: ast.AST, children: list[Node]) -> tuple[int, int]:
        """The node's byte span: its own position, from its first decorator, and reaching its last child, as a
        parameter reaches the default the contract nests under it."""
        if isinstance(node, ast.Module):
            return 0, len(self.source)
        if hasattr(node, "lineno") and node.end_lineno is not None:
            start = self.offset(node.lineno, node.col_offset)
            end = self.offset(node.end_lineno, node.end_col_offset)
            decorators = [child for child in children if child.field == "decorator_list"]
            if decorators:
                start = self.source.rindex(b"@", 0, decorators[0].span.start)
            return start, max([end, *(child.span.end for child in children)])
        if children:
            return children[0].span.start, children[-1].span.end
        return -1, -1

    def node(self, node: ast.AST, field: str | None, scope: list[str], floor: int = 0) -> Node:
        """The node and its subtree. $floor is where a node without a position of its own sits: the end of its
        previous sibling, or its parent's start."""
        identity = self.next
        self.next += 1
        role = self.role(node, field)
        placeholder = len(self.spans)
        self.spans.append((0, 0, identity))
        inner = scope + [node.name] if isinstance(node, DEFINITIONS) else scope
        children: list[Node] = []
        after = self.offset(node.lineno, node.col_offset) if hasattr(node, "lineno") else floor
        for name, child in self.ordered(node):
            children.append(self.node(child, name, inner, after))
            after = children[-1].span.end
        start, end = self.span_of(node, children)
        if start < 0:
            start = end = floor
        self.spans[placeholder] = (start, end, identity)
        answers = tuple(self.neutral(node))
        span = Span(start, end, self.line_of(start))
        facts = self.facts(node, field, scope, (start, end))
        docstring = ast.get_docstring(node, clean=False) if isinstance(node, (*DEFINITIONS, ast.Module)) else None
        if docstring is not None:
            self.docstrings.append((node.body[0].value, identity))
        return Node(identity, type(node).__name__, role, span, answers, field, facts, tuple(children))

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

    def facts(self, node: ast.AST, field: str | None, scope: list[str], span: tuple[int, int]) -> Facts:
        declared = None
        if isinstance(node, ast.arg) and node.annotation is not None:
            declared = self.type(node.annotation)
        if isinstance(node, ast.AnnAssign):
            declared = self.type(node.annotation)
        returns = self.type(node.returns) if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)) and node.returns is not None else None
        resolved = None
        if isinstance(node, ast.expr) and span in self.types:
            resolved = self.types[span]
            self.joined.add(span)
        return Facts(
            name=self.name_of(node),
            literal=self.literal(node),
            operator=self.operator(node),
            flags=tuple(self.flags(node, field, span[0])),
            declared=declared,
            returns=returns,
            symbol=".".join([self.module, *scope, node.name]) if isinstance(node, DEFINITIONS) else None,
            resolved=resolved,
            extras=self.extras(node),
        )

    def extras(self, node: ast.AST) -> dict[str, object] | None:
        """What the contract keeps of Python alone about the node, under `extras.python`."""
        if isinstance(node, ast.Compare) and len(node.ops) > 1:
            return {"operators": [OPERATORS[type(op)] for op in node.ops]}
        if isinstance(node, ast.ImportFrom) and node.level:
            return {"level": node.level}
        if isinstance(node, ast.alias) and node.asname is not None:
            return {"as": node.asname}
        if isinstance(node, (ast.Global, ast.Nonlocal)):
            return {"names": list(node.names)}
        return None

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

    def literal(self, node: ast.AST) -> Literal | None:
        if isinstance(node, ast.JoinedStr):
            return Literal("interpolated")
        if not isinstance(node, ast.Constant):
            return None
        value = node.value
        if isinstance(value, bool):
            return Literal("bool", value)
        if value is None:
            return Literal("null", None)
        if value is Ellipsis:
            return Literal("ellipsis")
        if isinstance(value, bytes):
            return Literal("bytes")
        if isinstance(value, int):
            return Literal("int", str(value))
        if isinstance(value, float):
            return Literal("float", repr(value))
        return Literal("string", value)

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

    def type(self, annotation: ast.expr) -> WrittenType:
        text = ast.get_source_segment(self.source.decode(), annotation) or ast.unparse(annotation)
        if isinstance(annotation, ast.Constant) and annotation.value is None:
            return WrittenType(text, "keyword", "None", nullable=True)
        if isinstance(annotation, ast.Constant) and isinstance(annotation.value, str):
            return WrittenType(text, "named", annotation.value)
        if is_union(annotation):
            members = tuple(self.union_members(annotation))
            return WrittenType(text, "union", members=members, nullable=any(member.name == "None" for member in members))
        if isinstance(annotation, ast.Subscript):
            base = self.type(annotation.value)
            items = annotation.slice.elts if isinstance(annotation.slice, ast.Tuple) else [annotation.slice]
            return WrittenType(text, "named", base.name, args=tuple(self.type(item) for item in items))
        if isinstance(annotation, (ast.Name, ast.Attribute)):
            return WrittenType(text, "named", ast.unparse(annotation))
        return WrittenType(text, "opaque")

    def union_members(self, annotation: ast.expr) -> list[WrittenType]:
        if is_union(annotation):
            return self.union_members(annotation.left) + self.union_members(annotation.right)
        return [self.type(annotation)]

    def comments(self) -> list[Comment]:
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
        comments: list[Comment] = []
        for start, end, kind in sorted(found):
            text = self.source[start:end].decode()
            span = Span(start, end, self.line_of(start))
            if kind.startswith("doc"):
                comments.append(Comment(len(comments), "doc", text, span, Attachment(int(kind[4:]))))
            else:
                comments.append(Comment(len(comments), "line", text, span, self.attachment(start, end), is_code(text[1:])))
        return comments

    def attachment(self, start: int, end: int) -> Attachment:
        line_start = self.source.rfind(b"\n", 0, start) + 1
        if self.source[line_start:start].strip():
            owner = self.outermost(lambda s, e: line_start < e <= start)
            return Attachment(owner, trailing=True)
        after = end
        while True:
            while after < len(self.source) and self.source[after:after + 1] in (b" ", b"\t", b"\r", b"\n"):
                after += 1
            if after not in self.comment_ends:
                break
            after = self.comment_ends[after]
        owner = self.outermost(lambda s, e: s == after)
        return Attachment(owner)

    def outermost(self, matches) -> int | None:
        for start, end, identity in self.spans:
            if matches(start, end):
                return identity
        return None



def is_union(annotation: ast.expr) -> bool:
    """Whether the annotation is written `A | B`."""
    return isinstance(annotation, ast.BinOp) and isinstance(annotation.op, ast.BitOr)


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


def unparsed(source: bytes) -> Node:
    """The root of a file Python's parser refused: an empty module over the whole file."""
    return Node(0, "Module", "other", Span(0, len(source), 1))


def stream(session: Session, paths: list[str], write: list[str], python: str | None) -> Iterator[dict]:
    """The contract's lines for the modules under $paths: the header, one file line each, the program, the trailer."""
    yield {"header": {"contract": "tree", "version": 2, "language": "python",
                      "bridge": {"name": "mypy-bridge", "version": MYPY_VERSION}, "roots": [os.path.realpath(p) for p in paths]}}
    project = session.checked(paths, python)
    found, types, graph = project.sources, project.types, project.graph
    judged = {os.path.realpath(p) for p in write}
    named = {os.path.realpath(s.path): s.module for s in found if s.path}
    checked = states(graph, set(named))
    seen = resolved = unjoined = files = 0
    folders: list[str] = []
    for path in python_files(paths):
        text = open(path, "rb").read()
        state = checked.get(path)
        typed = spans(path, state, types) if state is not None else TypedModule(0, {})
        seen, resolved = seen + typed.expressions, resolved + len(typed.by_span)
        module = named.get(path) or module_name(path)
        writer = TreeWriter(text, module, typed.by_span)
        try:
            root, errors = writer.node(ast.parse(text), None, []), 0
        except SyntaxError:
            root, errors = unparsed(text), 1
        comments = tuple(writer.comments()) if not errors else ()
        ran = state is not None and state.tree is not None
        yield {"file": FileLine(path, errors, bool(write) and path not in judged, module, ran, root, comments)}
        files += 1
        unjoined += sum(1 for span in typed.by_span if span not in writer.joined)
        folders += [folder for folder in packages(path) if folder not in folders]
    yield {"program": {"packages": sorted(folders)}}
    yield {"trailer": {"files": files, "resolution": {"expressions": seen, "typed": resolved, "unjoined": unjoined}}}


def emit(lines: Iterator[dict]) -> None:
    for line in lines:
        sys.stdout.write(json.dumps(line, ensure_ascii=False, separators=(",", ":"), default=to_json) + "\n")
    sys.stdout.flush()


def to_json(record: FileLine) -> dict[str, object]:
    """A line's record as the contract writes it."""
    return record.to_json()


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
