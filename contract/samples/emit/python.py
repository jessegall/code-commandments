"""Writes one Python file as a generic tree stream (contract/CONTRACT.md), the way a Python bridge will.

Usage: python python.py <file> <path-in-stream> <module> <package-in-stream>
Types come from bridge/mypy/bridge.py, run with the same interpreter, joined to nodes by exact span.
"""

from __future__ import annotations

import ast
import io
import json
import os
import subprocess
import sys
import tokenize

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
        self.next = 0

    def offset(self, line: int, column: int) -> int:
        return self.starts[line - 1] + column

    def line_of(self, offset: int) -> int:
        return self.source.count(b"\n", 0, offset) + 1

    def span_of(self, node: ast.AST, children: list[dict]) -> tuple[int, int]:
        if isinstance(node, ast.Module):
            return 0, len(self.source)
        if hasattr(node, "lineno") and node.end_lineno is not None:
            start = self.offset(node.lineno, node.col_offset)
            end = self.offset(node.end_lineno, node.end_col_offset)
            decorators = [child for child in children if child["field"] == "decorator_list"]
            if decorators:
                start = self.source.rindex(b"@", 0, decorators[0]["span"][0])
            return start, end
        if children:
            return children[0]["span"][0], children[-1]["span"][1]
        return -1, -1

    def node(self, node: ast.AST, field: str | None, scope: list[str]) -> dict:
        identity = self.next
        self.next += 1
        out: dict = {"id": identity, "kind": type(node).__name__, "role": self.role(node, field)}
        placeholder = len(self.spans)
        self.spans.append((0, 0, identity))
        inner = scope + [node.name] if isinstance(node, DEFINITIONS) else scope
        children = []
        for name, value in ast.iter_fields(node):
            for child in value if isinstance(value, list) else [value]:
                if isinstance(child, ast.AST) and not isinstance(child, SKIPPED):
                    children.append(self.node(child, name, inner))
        start, end = self.span_of(node, children)
        if start < 0:
            start = end = self.empty_at(node, children)
        self.spans[placeholder] = (start, end, identity)
        answers = self.neutral(node)
        if answers:
            out["is"] = answers
        out["span"] = [start, end, self.line_of(start)]
        if field is not None:
            out["field"] = field
        out.update(self.facts(node, scope, (start, end)))
        if children:
            out["children"] = children
        docstring = ast.get_docstring(node, clean=False) if isinstance(node, (*DEFINITIONS, ast.Module)) else None
        if docstring is not None:
            self.docstrings.append((node.body[0].value, identity))
        return out

    def empty_at(self, node: ast.AST, children: list[dict]) -> int:
        return self.spans[-2][1] if len(self.spans) > 1 else 0

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

    def facts(self, node: ast.AST, scope: list[str], span: tuple[int, int]) -> dict:
        facts: dict = {}
        name = self.name_of(node)
        if name is not None:
            facts["name"] = name
        facts.update(self.literal(node))
        operator = self.operator(node)
        if operator is not None:
            facts["operator"] = operator
        flags = self.flags(node)
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
        return None

    def flags(self, node: ast.AST) -> list[str]:
        flags = []
        if isinstance(node, (ast.AsyncFunctionDef, ast.AsyncFor, ast.AsyncWith)):
            flags.append("async")
        if isinstance(node, ast.comprehension) and node.is_async:
            flags.append("async")
        if isinstance(node, ast.Starred):
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


def mypy_types(path: str) -> tuple[dict[tuple[int, int], dict], dict]:
    bridge = os.path.join(os.path.dirname(__file__), "../../../bridge/mypy/bridge.py")
    output = subprocess.run([sys.executable, bridge, path], capture_output=True, text=True, check=True).stdout
    types: dict[tuple[int, int], dict] = {}
    resolution: dict = {}
    for line in output.splitlines():
        record = json.loads(line)
        if os.path.realpath(record.get("path", "")) == os.path.realpath(path):
            types = {(entry["start"], entry["end"]): entry for entry in record["types"]}
        resolution = record.get("resolution", resolution)
    return types, resolution


def main(argv: list[str]) -> int:
    source_path, shown, module, package = argv
    source = open(source_path, "rb").read()
    types, resolution = mypy_types(source_path)
    writer = TreeWriter(source, module, types)
    root = writer.node(ast.parse(source), None, [])
    comments = writer.comments()
    unjoined = sum(1 for span in types if span not in writer.joined)

    def emit(record: dict) -> None:
        print(json.dumps(record, ensure_ascii=False, separators=(",", ":")))

    emit({"header": {"contract": "tree", "version": 1, "language": "python",
                     "bridge": {"name": "contract/samples/emit/python.py", "version": "1"}, "roots": [shown]}})
    emit({"file": {"path": shown, "language": "python", "errors": 0, "module": module,
                   "resolver": {"tool": "mypy", "ran": True}, "root": root, "comments": comments}})
    emit({"program": {"packages": [package]}})
    emit({"trailer": {"files": 1, "resolution": {"expressions": resolution.get("expressions", 0),
                                                 "typed": resolution.get("typed", 0), "unjoined": unjoined}}})
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
