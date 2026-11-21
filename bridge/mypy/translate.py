"""A Python module translated into the version 5 generic tree (contract/CONTRACT.md): one neutral kind per operation,
its parts under named roles, its facts, and byte spans into the file. Compiler facts (refers, a call of a class as a
construction, resolved types) come from mypy and are left out when mypy did not check the module."""

from __future__ import annotations

import ast
import bisect
import io
import re
import tokenize
from dataclasses import dataclass, field

from session import Named, ResolvedType

COMPARISONS = {
    ast.Eq: "equal", ast.NotEq: "not-equal", ast.Lt: "less", ast.LtE: "less-or-equal", ast.Gt: "greater",
    ast.GtE: "greater-or-equal", ast.Is: "identical", ast.IsNot: "not-identical", ast.In: "in", ast.NotIn: "not-in",
}
BINARIES = {
    ast.Add: "add", ast.Sub: "subtract", ast.Mult: "multiply", ast.Div: "divide", ast.FloorDiv: "divide",
    ast.Mod: "modulo", ast.Pow: "power", ast.LShift: "shift-left", ast.RShift: "shift-right", ast.BitOr: "bit-or",
    ast.BitAnd: "bit-and", ast.BitXor: "bit-xor", ast.MatMult: "multiply",
}
UNARIES = {ast.Not: "not", ast.USub: "negate", ast.UAdd: "plus", ast.Invert: "bit-not"}
COMPREHENSIONS = {ast.ListComp: "list", ast.SetComp: "set", ast.GeneratorExp: "generator", ast.DictComp: "map"}
FUNCTIONS = (ast.FunctionDef, ast.AsyncFunctionDef)
SCOPES = (*FUNCTIONS, ast.Lambda, ast.ClassDef)


@dataclass
class Node:
    """One node of the tree before it is numbered: kind, byte span, facts, owned parts and links to other nodes."""

    kind: str
    start: int
    end: int
    facts: dict[str, object] = field(default_factory=dict)
    parts: dict[str, Node | list[Node]] = field(default_factory=dict)
    links: dict[str, Node | list[Node]] = field(default_factory=dict)
    id: int = -1

    def children(self) -> list[Node]:
        held = [child for value in self.parts.values() for child in (value if isinstance(value, list) else [value])]
        return sorted(held, key=lambda child: (child.start, -child.end))


class Translator:
    """Translates one parsed module. `resolve` names what a dotted name refers to (None when mypy did not run), and
    `types` is mypy's type of each expression by its byte span."""

    def __init__(self, source: bytes, module: str, resolve, types: dict[tuple[int, int], ResolvedType]) -> None:
        self.source = source
        self.module = module
        self.resolve = resolve
        self.types = types
        self.joined: set[tuple[int, int]] = set()
        self.table: list[dict[str, object]] = []
        self.starts = [0, *(at + 1 for at, byte in enumerate(source) if byte == 0x0A)]
        self.modules: set[str] = set()
        self.docstrings: list[tuple[ast.Constant, Node]] = []

    # Positions ----------------------------------------------------------------------------------------------------

    def at(self, line: int, column: int) -> int:
        return self.starts[line - 1] + column

    def span(self, node: ast.AST) -> tuple[int, int]:
        return self.at(node.lineno, node.col_offset), self.at(node.end_lineno, node.end_col_offset)

    def text(self, start: int, end: int) -> str:
        return self.source[start:end].decode(errors="replace")

    def line(self, offset: int) -> int:
        return bisect.bisect_right(self.starts, offset)

    def named_at(self, name: str, low: int, high: int) -> tuple[int, int]:
        """Where `name` is written as a whole word between `low` and `high`."""
        found = re.search(rb"(?<![\w.])" + re.escape(name.encode()) + rb"(?!\w)", self.source[low:high])
        start = low + found.start() if found else low
        return start, start + len(name.encode())

    def new(self, kind: str, start: int, end: int, **facts: object) -> Node:
        return Node(kind, start, end, {key: value for key, value in facts.items() if value is not None and value is not False})

    def of(self, kind: str, node: ast.AST, **facts: object) -> Node:
        start, end = self.span(node)
        return self.new(kind, start, end, **facts)

    # The module ---------------------------------------------------------------------------------------------------

    def file(self, tree: ast.Module) -> Node:
        whole = len(self.source)
        module = self.new("module", 0, whole, name=self.module or None)
        imports, declarations, statements = [], [], []
        for statement in tree.body:
            if isinstance(statement, (ast.Import, ast.ImportFrom)):
                imports.extend(self.imports(statement))
            elif isinstance(statement, (*FUNCTIONS, ast.ClassDef)):
                declarations.append(self.statement(statement, [], None))
            else:
                statements.append(self.statement(statement, [], None))
        module.parts = {role: held for role, held in (("imports", imports), ("declarations", declarations), ("statements", statements)) if held}
        self.document(tree, module)
        root = self.new("file", 0, whole)
        root.parts = {"modules": [module]}
        return root

    def document(self, owner: ast.AST, node: Node) -> None:
        body = getattr(owner, "body", None)
        if body and isinstance(body[0], ast.Expr) and isinstance(body[0].value, ast.Constant) and isinstance(body[0].value.value, str):
            self.docstrings.append((body[0].value, node))

    # Statements ---------------------------------------------------------------------------------------------------

    def block(self, statements: list[ast.stmt], scope: list[str], owner: str | None) -> Node:
        held = [self.statement(statement, scope, owner) for statement in statements]
        node = self.new("block", held[0].start, held[-1].end)
        node.parts = {"statements": held}
        return node

    def statement(self, statement: ast.stmt, scope: list[str], owner: str | None) -> Node:
        handler = getattr(self, "stmt_" + type(statement).__name__, None)
        if handler is not None:
            return handler(statement, scope, owner)
        return self.unsupported(statement)

    def unsupported(self, written: ast.AST) -> Node:
        """A construct version 5 has no kind for: its sub-expressions are still read, as its parts."""
        node = self.of("unsupported", written, construct=type(written).__name__.lower())
        node.parts = {"parts": [self.expression(child) for child in ast.iter_child_nodes(written) if isinstance(child, ast.expr)]}
        return node

    def stmt_Expr(self, statement: ast.Expr, scope, owner) -> Node:
        node = self.of("expression-statement", statement)
        node.parts = {"expression": self.expression(statement.value)}
        return node

    def stmt_Pass(self, statement, scope, owner) -> Node:
        return self.of("no-op", statement)

    def stmt_Break(self, statement, scope, owner) -> Node:
        return self.of("break", statement)

    def stmt_Continue(self, statement, scope, owner) -> Node:
        return self.of("continue", statement)

    def stmt_Return(self, statement: ast.Return, scope, owner) -> Node:
        node = self.of("return", statement)
        node.parts = {"values": [self.expression(statement.value)] if statement.value is not None else []}
        return node

    def stmt_Raise(self, statement: ast.Raise, scope, owner) -> Node:
        node = self.of("throw", statement)
        if statement.exc is not None:
            node.parts["value"] = self.expression(statement.exc)
        if statement.cause is not None:
            node.parts["cause"] = self.expression(statement.cause)
        return node

    def stmt_Assign(self, statement: ast.Assign, scope, owner) -> Node:
        if owner == "class":
            return self.field(statement, statement.targets, None, statement.value)
        return self.assigned(statement, self.new("assignment", *self.span(statement), mode="assign"),
                             [self.expression(target) for target in statement.targets], [self.expression(statement.value)])

    def stmt_AnnAssign(self, statement: ast.AnnAssign, scope, owner) -> Node:
        if owner == "class":
            return self.field(statement, [statement.target], statement.annotation, statement.value)
        values = [self.expression(statement.value)] if statement.value is not None else []
        return self.assigned(statement, self.new("assignment", *self.span(statement), mode="assign"), [self.expression(statement.target)], values)

    def stmt_AugAssign(self, statement: ast.AugAssign, scope, owner) -> Node:
        assignment = self.new("assignment", *self.span(statement), mode="compound", operator=BINARIES[type(statement.op)])
        return self.assigned(statement, assignment, [self.expression(statement.target)], [self.expression(statement.value)])

    def assigned(self, statement: ast.stmt, assignment: Node, targets: list[Node], values: list[Node]) -> Node:
        assignment.parts = {"targets": targets, "values": values}
        node = self.of("expression-statement", statement)
        node.parts = {"expression": assignment}
        return node

    def field(self, statement: ast.stmt, targets: list[ast.expr], annotation: ast.expr | None, value: ast.expr | None) -> Node:
        node = self.of("field", statement)
        declarators = []
        for target in targets:
            start, end = self.span(target)
            declarator = self.new("declarator", start, self.span(value)[1] if value is not None else end)
            declarator.parts = {"binding": self.written_binding(target)}
            if value is not None:
                declarator.parts["initializer"] = self.expression(value)
            declarators.append(declarator)
        node.parts = {"declarators": declarators}
        if annotation is not None:
            node.parts["declaredType"] = self.type(annotation)
        return node

    def stmt_If(self, statement: ast.If, scope, owner) -> Node:
        node = self.of("if", statement)
        node.parts = {"condition": self.expression(statement.test), "then": self.block(statement.body, scope, owner)}
        if statement.orelse:
            first = statement.orelse[0]
            elif_ = len(statement.orelse) == 1 and isinstance(first, ast.If) and self.source.startswith(b"elif", self.span(first)[0])
            node.parts["else"] = self.statement(first, scope, owner) if elif_ else self.block(statement.orelse, scope, owner)
        return node

    def stmt_For(self, statement: ast.For, scope, owner, asynchronous: bool = False) -> Node:
        node = self.of("loop", statement, form="for-each", **{"async": asynchronous})
        node.parts = {"bindings": self.bindings(statement.target), "iterable": self.expression(statement.iter),
                      "body": self.block(statement.body, scope, owner)}
        if statement.orelse:
            node.parts["normalCompletion"] = self.block(statement.orelse, scope, owner)
        return node

    def stmt_AsyncFor(self, statement, scope, owner) -> Node:
        return self.stmt_For(statement, scope, owner, asynchronous=True)

    def stmt_While(self, statement: ast.While, scope, owner) -> Node:
        node = self.of("loop", statement, form="while")
        node.parts = {"condition": self.expression(statement.test), "body": self.block(statement.body, scope, owner)}
        if statement.orelse:
            node.parts["normalCompletion"] = self.block(statement.orelse, scope, owner)
        return node

    def stmt_With(self, statement: ast.With, scope, owner, asynchronous: bool = False) -> Node:
        node = self.of("resource-scope", statement, form="with", **{"async": asynchronous})
        resources = []
        for item in statement.items:
            acquired = self.expression(item.context_expr)
            if item.optional_vars is None:
                resources.append(acquired)
                continue
            declarator = self.new("declarator", acquired.start, self.span(item.optional_vars)[1])
            declarator.parts = {"binding": self.written_binding(item.optional_vars), "initializer": acquired}
            resources.append(declarator)
        node.parts = {"resources": resources, "body": self.block(statement.body, scope, owner)}
        return node

    def stmt_AsyncWith(self, statement, scope, owner) -> Node:
        return self.stmt_With(statement, scope, owner, asynchronous=True)

    def stmt_Try(self, statement: ast.Try, scope, owner, group: bool = False) -> Node:
        node = self.of("try", statement)
        node.parts = {"body": self.block(statement.body, scope, owner),
                      "handlers": [self.handler(handler, scope, owner, group) for handler in statement.handlers]}
        if statement.orelse:
            node.parts["normalCompletion"] = self.block(statement.orelse, scope, owner)
        if statement.finalbody:
            node.parts["finally"] = self.block(statement.finalbody, scope, owner)
        return node

    def stmt_TryStar(self, statement, scope, owner) -> Node:
        return self.stmt_Try(statement, scope, owner, group=True)

    def handler(self, handler: ast.ExceptHandler, scope, owner, group: bool) -> Node:
        node = self.of("handler", handler, catchForm="typed" if handler.type is not None else "catch-all", group=group)
        if handler.type is not None:
            caught = handler.type.elts if isinstance(handler.type, ast.Tuple) else [handler.type]
            node.parts["caughtTypes"] = [self.type(each) for each in caught]
        if handler.name is not None:
            after = self.span(handler.type)[1] if handler.type is not None else node.start
            start, end = self.named_at(handler.name, after, self.span(handler.body[0])[0])
            node.parts["binding"] = self.new("binding", start, end, name=handler.name)
        node.parts["body"] = self.block(handler.body, scope, owner)
        return node

    def stmt_Global(self, statement: ast.Global, scope, owner, form: str = "global") -> Node:
        node = self.of("scope-declaration", statement, form=form)
        start, end = self.span(statement)
        cursor, bindings = start + len(form), []
        for name in statement.names:
            low, high = self.named_at(name, cursor, end)
            bindings.append(self.new("binding", low, high, name=name))
            cursor = high
        node.parts = {"bindings": bindings}
        return node

    def stmt_Nonlocal(self, statement, scope, owner) -> Node:
        return self.stmt_Global(statement, scope, owner, form="nonlocal")

    def stmt_Import(self, statement, scope, owner) -> Node:
        found = self.imports(statement)
        if len(found) == 1:
            return found[0]
        node = self.of("block", statement)
        node.parts = {"statements": found}
        return node

    stmt_ImportFrom = stmt_Import

    def stmt_FunctionDef(self, statement, scope, owner) -> Node:
        return self.function(statement, scope, owner)

    stmt_AsyncFunctionDef = stmt_FunctionDef

    def stmt_ClassDef(self, statement: ast.ClassDef, scope, owner) -> Node:
        start = self.decorated(statement)
        node = self.new("type", start, self.span(statement)[1], name=statement.name,
                        symbol=".".join([self.module, *scope, statement.name]), form="class")
        inner = [*scope, statement.name]
        node.parts = {}
        if statement.bases:
            node.parts["bases"] = [self.type(base) for base in statement.bases]
        if statement.decorator_list:
            node.parts["attributes"] = [self.annotation(each) for each in statement.decorator_list]
        node.parts["writtenMembers"] = [self.statement(each, inner, "class") for each in statement.body]
        self.document(statement, node)
        return node

    def stmt_Match(self, statement: ast.Match, scope, owner) -> Node:
        node = self.of("dispatch", statement, form="match")
        arms = []
        for case in statement.cases:
            pattern = self.pattern(case.pattern)
            start = self.source.rindex(b"case", 0, pattern.start)
            body = self.block(case.body, scope, owner)
            arm = self.new("arm", start, body.end, default=self.is_wildcard(case.pattern) and case.guard is None)
            arm.parts = {"patterns": [] if arm.facts.get("default") else [pattern], "body": body}
            if case.guard is not None:
                arm.parts["guard"] = self.expression(case.guard)
            arms.append(arm)
        node.parts = {"subject": self.expression(statement.subject), "arms": arms}
        return node

    def is_wildcard(self, pattern: ast.pattern) -> bool:
        return isinstance(pattern, ast.MatchAs) and pattern.pattern is None and pattern.name is None

    # Declarations -------------------------------------------------------------------------------------------------

    def decorated(self, statement: ast.stmt) -> int:
        decorators = getattr(statement, "decorator_list", [])
        if decorators:
            return self.source.rindex(b"@", 0, self.span(decorators[0])[0])
        return self.span(statement)[0]

    def function(self, statement: ast.FunctionDef, scope: list[str], owner: str | None) -> Node:
        if owner == "class":
            form = "constructor" if statement.name == "__init__" else "method"
        else:
            form = "local" if owner == "function" else "function"
        node = self.new("function", self.decorated(statement), self.span(statement)[1], name=statement.name,
                        symbol=".".join([self.module, *scope, statement.name]), form=form,
                        generator=self.yields(statement), **{"async": isinstance(statement, ast.AsyncFunctionDef)})
        node.parts = {}
        if statement.decorator_list:
            node.parts["attributes"] = [self.annotation(each) for each in statement.decorator_list]
        parameters = self.parameters(statement.args)
        if parameters:
            node.parts["parameters"] = parameters
        if statement.returns is not None:
            node.parts["returnType"] = self.type(statement.returns)
        node.parts["body"] = self.block(statement.body, [*scope, statement.name], "function")
        self.document(statement, node)
        return node

    def yields(self, function: ast.AST) -> bool:
        """Whether the function's own body yields, not counting a function nested in it."""
        pending = list(ast.iter_child_nodes(function))
        while pending:
            node = pending.pop()
            if isinstance(node, (ast.Yield, ast.YieldFrom)):
                return True
            if not isinstance(node, SCOPES):
                pending.extend(ast.iter_child_nodes(node))
        return False

    def written_binding(self, target: ast.expr) -> Node:
        """A binding of a target written as an expression, named as it is written: `self.total`, `(a, b)`."""
        return self.new("binding", *self.span(target), name=ast.unparse(target))

    def parameters(self, arguments: ast.arguments) -> list[Node]:
        positional = [*arguments.posonlyargs, *arguments.args]
        defaults = dict(zip(map(id, positional[len(positional) - len(arguments.defaults):]), arguments.defaults))
        defaults.update({id(each): default for each, default in zip(arguments.kwonlyargs, arguments.kw_defaults) if default is not None})
        written = [(each, None, "positional") for each in arguments.posonlyargs]
        written += [(each, None, None) for each in arguments.args]
        if arguments.vararg is not None:
            written.append((arguments.vararg, "rest", None))
        written += [(each, None, "keyword") for each in arguments.kwonlyargs]
        if arguments.kwarg is not None:
            written.append((arguments.kwarg, "keywords", None))
        return sorted((self.parameter(each, defaults.get(id(each)), collects, position) for each, collects, position in written),
                      key=lambda node: node.start)

    def parameter(self, argument: ast.arg, default: ast.expr | None, collects: str | None, position: str | None) -> Node:
        start, name_end = self.at(argument.lineno, argument.col_offset), self.at(argument.lineno, argument.col_offset) + len(argument.arg.encode())
        binding = self.new("binding", start, name_end, name=argument.arg)
        # `*args` and `**kwargs` start at their stars, which the parser leaves out of the name's position.
        start -= {"rest": 1, "keywords": 2}.get(collects or "", 0)
        node = self.new("parameter", start, self.span(argument)[1], evaluated="definition" if default is not None else None,
                        collects=collects, position=position)
        node.parts = {"binding": binding}
        if argument.annotation is not None:
            node.parts["declaredType"] = self.type(argument.annotation)
        if default is not None:
            node.parts["default"] = self.expression(default)
            node.end = max(node.end, node.parts["default"].end)
        return node

    def annotation(self, decorator: ast.expr) -> Node:
        callee = decorator.func if isinstance(decorator, ast.Call) else decorator
        node = self.of("annotation", decorator, name=ast.unparse(callee), refers=self.refers(callee))
        node.parts = {"callee": self.expression(callee)}
        if isinstance(decorator, ast.Call):
            node.parts["arguments"] = self.arguments(decorator)
        return node

    def imports(self, statement: ast.Import | ast.ImportFrom) -> list[Node]:
        if isinstance(statement, ast.Import):
            found = []
            for alias in statement.names:
                start, end = self.span(statement) if len(statement.names) == 1 else self.span(alias)
                node = self.new("import", start, end, source=alias.name, form="module", refers=self.known(alias.name))
                if alias.asname is not None:
                    low, high = self.named_at(alias.asname, self.span(alias)[0] + len(alias.name), self.span(alias)[1])
                    node.parts = {"alias": self.new("binding", low, high, name=alias.asname)}
                self.modules.add(alias.asname or alias.name.split(".")[0])
                found.append(node)
            return found
        source = ("." * statement.level) + (statement.module or "")
        absolute = self.absolute(statement)
        wildcard = any(alias.name == "*" for alias in statement.names)
        node = self.of("import", statement, source=source, form="wildcard" if wildcard else "names",
                       relativeLevel=statement.level or None, refers=self.known(absolute))
        bindings = []
        for alias in statement.names:
            if alias.name == "*":
                continue
            binding = self.of("import-binding", alias, name=alias.name, refers=self.known(f"{absolute}.{alias.name}") if absolute else None)
            if alias.asname is not None:
                low, high = self.named_at(alias.asname, binding.start + len(alias.name), binding.end)
                binding.parts = {"alias": self.new("binding", low, high, name=alias.asname)}
            bindings.append(binding)
        if bindings:
            node.parts = {"bindings": bindings}
        return [node]

    def absolute(self, statement: ast.ImportFrom) -> str:
        if not statement.level:
            return statement.module or ""
        parts = self.module.split(".")[:-statement.level] if self.module else []
        if statement.module:
            parts.append(statement.module)
        return ".".join(parts)

    def known(self, name: str) -> str | None:
        """The module or name an import refers to, a compiler fact: stated only for a module mypy checked."""
        return name if name and self.checked else None

    @property
    def checked(self) -> bool:
        """Whether mypy checked the module, so its names resolve: the builtins always do then."""
        return self.resolve("int") is not None

    # Types as written ---------------------------------------------------------------------------------------------

    def type(self, annotation: ast.expr) -> Node:
        if isinstance(annotation, ast.Constant) and annotation.value is None:
            return self.of("type-expression", annotation, form="keyword", name="None")
        if is_union(annotation):
            node = self.of("type-expression", annotation, form="union")
            node.parts = {"members": [self.type(member) for member in self.union(annotation)]}
            return node
        if isinstance(annotation, ast.Subscript):
            node = self.named_type(annotation, self.refers(annotation.value))
            items = annotation.slice.elts if isinstance(annotation.slice, ast.Tuple) else [annotation.slice]
            node.parts = {"typeArguments": [self.type(item) for item in items]}
            return node
        if isinstance(annotation, (ast.Name, ast.Attribute)):
            return self.named_type(annotation, self.refers(annotation))
        if isinstance(annotation, ast.Constant) and isinstance(annotation.value, str):
            return self.named_type(annotation, None)
        return self.of("type-expression", annotation, form="opaque")

    def named_type(self, annotation: ast.expr, refers: str | None) -> Node:
        return self.of("type-expression", annotation, form="named", refers=refers)

    def union(self, annotation: ast.expr) -> list[ast.expr]:
        if is_union(annotation):
            return self.union(annotation.left) + self.union(annotation.right)
        return [annotation]

    def refers(self, expression: ast.expr) -> str | None:
        named = self.named(expression)
        return named.fullname if named is not None else None

    def named(self, expression: ast.expr) -> Named | None:
        if isinstance(expression, (ast.Name, ast.Attribute)):
            return self.resolve(ast.unparse(expression))
        return None

    # Expressions --------------------------------------------------------------------------------------------------

    def expression(self, expression: ast.expr) -> Node:
        handler = getattr(self, "expr_" + type(expression).__name__, None)
        if handler is None:
            node = self.unsupported(expression)
        else:
            node = handler(expression)
        span = self.span(expression)
        if span in self.types and node.kind != "unsupported":
            self.joined.add(span)
            typed = self.types[span].to_json()
            if typed not in self.table:
                self.table.append(typed)
            node.facts["resolvedType"] = self.table.index(typed)
        return node

    def expr_Name(self, expression: ast.Name) -> Node:
        return self.of("reference", expression, name=expression.id, form="self" if expression.id in ("self", "cls") else "name")

    def expr_Constant(self, expression: ast.Constant) -> Node:
        value = expression.value
        if value is None:
            return self.of("literal", expression, literalKind="null")
        if value is Ellipsis:
            return self.of("literal", expression, literalKind="ellipsis")
        if isinstance(value, bool):
            node = self.of("literal", expression, literalKind="boolean")
            node.facts["value"] = value
            return node
        if isinstance(value, int):
            return self.of("literal", expression, literalKind="integer", value=str(value))
        if isinstance(value, (float, complex)):
            return self.of("literal", expression, literalKind="float", value=repr(value))
        if isinstance(value, bytes):
            return self.of("literal", expression, literalKind="bytes")
        node = self.of("literal", expression, literalKind="string")
        node.facts["value"] = value
        return node

    def expr_JoinedStr(self, expression: ast.JoinedStr) -> Node:
        node = self.of("interpolation", expression)
        parts = []
        for part in expression.values:
            if isinstance(part, ast.Constant):
                text = self.of("text", part)
                text.facts["value"] = part.value
                parts.append(text)
            elif isinstance(part, ast.FormattedValue):
                inner = self.expression(part.value)
                if part.conversion != -1:
                    inner.facts["conversion"] = "!" + chr(part.conversion)
                parts.append(inner)
        node.parts = {"parts": parts}
        return node

    def expr_Attribute(self, expression: ast.Attribute) -> Node:
        static = isinstance(expression.value, ast.Name) and expression.value.id in self.modules
        node = self.of("access", expression, form="static" if static else "instance")
        end = node.end
        member = self.new("reference", end - len(expression.attr.encode()), end, name=expression.attr, form="name")
        node.parts = {"subject": self.expression(expression.value), "member": member}
        return node

    def expr_Subscript(self, expression: ast.Subscript) -> Node:
        node = self.of("index", expression)
        node.parts = {"subject": self.expression(expression.value), "key": self.expression(expression.slice)}
        return node

    def expr_Call(self, expression: ast.Call) -> Node:
        named = self.named(expression.func)
        construction = named is not None and named.is_class
        node = self.of("construction" if construction else "call", expression)
        node.parts = {"callee": self.expression(expression.func), "arguments": self.arguments(expression)}
        return node

    def arguments(self, call: ast.Call) -> list[Node]:
        found = []
        for argument in call.args:
            if isinstance(argument, ast.Starred):
                node = self.of("argument", argument, spread="positional")
                node.parts = {"value": self.expression(argument.value)}
            else:
                value = self.expression(argument)
                node = self.new("argument", value.start, value.end)
                node.parts = {"value": value}
            found.append(node)
        for keyword in call.keywords:
            if keyword.arg is None:
                node = self.of("argument", keyword, spread="keyed")
            else:
                node = self.of("argument", keyword, label=keyword.arg)
            node.parts = {"value": self.expression(keyword.value)}
            found.append(node)
        return sorted(found, key=lambda node: node.start)

    def expr_Compare(self, expression: ast.Compare) -> Node:
        operators = [COMPARISONS[type(op)] for op in expression.ops]
        node = self.of("comparison", expression, operators=operators, operator=operators[0] if len(operators) == 1 else None)
        operands = [self.expression(expression.left), *(self.expression(each) for each in expression.comparators)]
        node.parts = {"operands": operands}
        node.links = {"left": operands[0], "right": operands[-1]}
        return node

    def expr_BoolOp(self, expression: ast.BoolOp) -> Node:
        operator = "and" if isinstance(expression.op, ast.And) else "or"
        left = self.expression(expression.values[0])
        for value in expression.values[1:]:
            right = self.expression(value)
            joined = self.new("logical", left.start, right.end, operator=operator)
            joined.parts = {"left": left, "right": right}
            left = joined
        return left

    def expr_BinOp(self, expression: ast.BinOp) -> Node:
        node = self.of("binary", expression, operator=BINARIES[type(expression.op)])
        node.parts = {"left": self.expression(expression.left), "right": self.expression(expression.right)}
        return node

    def expr_UnaryOp(self, expression: ast.UnaryOp) -> Node:
        node = self.of("unary", expression, operator=UNARIES[type(expression.op)])
        node.parts = {"operand": self.expression(expression.operand)}
        return node

    def expr_IfExp(self, expression: ast.IfExp) -> Node:
        node = self.of("conditional", expression)
        node.parts = {"condition": self.expression(expression.test), "consequent": self.expression(expression.body),
                      "alternative": self.expression(expression.orelse)}
        return node

    def expr_Lambda(self, expression: ast.Lambda) -> Node:
        node = self.of("function", expression, form="lambda")
        parameters = self.parameters(expression.args)
        node.parts = {"parameters": parameters} if parameters else {}
        node.parts["body"] = self.expression(expression.body)
        return node

    def expr_Await(self, expression: ast.Await) -> Node:
        node = self.of("await", expression)
        node.parts = {"operand": self.expression(expression.value)}
        return node

    def expr_Yield(self, expression: ast.Yield, delegates: bool = False) -> Node:
        node = self.of("yield", expression, delegates=delegates)
        if expression.value is not None:
            node.parts = {"value": self.expression(expression.value)}
        return node

    def expr_YieldFrom(self, expression: ast.YieldFrom) -> Node:
        return self.expr_Yield(expression, delegates=True)

    def expr_NamedExpr(self, expression: ast.NamedExpr) -> Node:
        node = self.of("assignment", expression, mode="assign")
        node.parts = {"targets": [self.expression(expression.target)], "values": [self.expression(expression.value)]}
        return node

    def expr_Dict(self, expression: ast.Dict) -> Node:
        node = self.of("collection", expression, form="map")
        entries = []
        for key, value in zip(expression.keys, expression.values):
            held = self.expression(value)
            if key is None:
                entry = self.new("entry", self.source.rindex(b"**", 0, held.start), held.end, expansion="keyed")
                entry.parts = {"value": held}
            else:
                written = self.expression(key)
                entry = self.new("entry", written.start, held.end)
                entry.parts = {"key": written, "value": held}
            entries.append(entry)
        node.parts = {"entries": entries}
        return node

    def sequence(self, expression: ast.List | ast.Tuple | ast.Set, form: str) -> Node:
        node = self.of("collection", expression, form=form)
        entries = []
        for element in expression.elts:
            if isinstance(element, ast.Starred):
                entry = self.of("entry", element, expansion="positional")
                entry.parts = {"value": self.expression(element.value)}
            else:
                held = self.expression(element)
                entry = self.new("entry", held.start, held.end)
                entry.parts = {"value": held}
            entries.append(entry)
        node.parts = {"entries": entries}
        return node

    def expr_List(self, expression) -> Node:
        return self.sequence(expression, "list")

    def expr_Tuple(self, expression) -> Node:
        return self.sequence(expression, "tuple")

    def expr_Set(self, expression) -> Node:
        return self.sequence(expression, "set")

    def comprehension(self, expression: ast.AST) -> Node:
        node = self.of("comprehension", expression, form=COMPREHENSIONS[type(expression)])
        if isinstance(expression, ast.DictComp):
            node.parts = {"key": self.expression(expression.key), "value": self.expression(expression.value)}
        else:
            node.parts = {"element": self.expression(expression.elt)}
        clauses = []
        for generator in expression.generators:
            iterable = self.expression(generator.iter)
            start = self.source.rindex(b"for", 0, self.span(generator.target)[0])
            if generator.is_async:
                start = self.source.rindex(b"async", 0, start)
            clause = self.new("comprehension-clause", start, iterable.end, form="for", **{"async": bool(generator.is_async)})
            clause.parts = {"bindings": self.bindings(generator.target), "iterable": iterable}
            clauses.append(clause)
            for condition in generator.ifs:
                tested = self.expression(condition)
                guard = self.new("comprehension-clause", self.source.rindex(b"if", 0, tested.start), tested.end, form="if")
                guard.parts = {"condition": tested}
                clauses.append(guard)
        node.parts["clauses"] = clauses
        return node

    expr_ListComp = expr_SetComp = expr_GeneratorExp = expr_DictComp = comprehension

    def expr_Slice(self, expression: ast.Slice) -> Node:
        node = self.of("unsupported", expression, construct="slice")
        node.parts = {"parts": [self.expression(each) for each in (expression.lower, expression.upper, expression.step) if each is not None]}
        return node

    def bindings(self, target: ast.expr) -> list[Node]:
        if isinstance(target, ast.Name):
            return [self.of("binding", target, name=target.id)]
        if isinstance(target, (ast.Tuple, ast.List)):
            return [binding for element in target.elts for binding in self.bindings(element)]
        if isinstance(target, ast.Starred):
            return self.bindings(target.value)
        return [self.of("binding", target, name=ast.unparse(target))]

    # Patterns -----------------------------------------------------------------------------------------------------

    def pattern(self, pattern: ast.pattern) -> Node:
        if isinstance(pattern, ast.MatchValue):
            node = self.of("pattern", pattern, form="value")
            node.parts = {"value": self.expression(pattern.value)}
        elif isinstance(pattern, ast.MatchSingleton):
            node = self.of("pattern", pattern, form="value")
            node.parts = {"value": self.expr_Constant(ast.Constant(pattern.value, lineno=pattern.lineno, col_offset=pattern.col_offset,
                                                                   end_lineno=pattern.end_lineno, end_col_offset=pattern.end_col_offset))}
        elif isinstance(pattern, ast.MatchAs):
            node = self.of("pattern", pattern, form="wildcard" if pattern.name is None and pattern.pattern is None else "binding")
            if pattern.pattern is not None:
                node.parts["subpatterns"] = [self.pattern(pattern.pattern)]
            if pattern.name is not None:
                node.parts["bindings"] = [self.new("binding", node.end - len(pattern.name.encode()), node.end, name=pattern.name)]
        elif isinstance(pattern, ast.MatchStar):
            node = self.of("pattern", pattern, form="rest")
            if pattern.name is not None:
                node.parts = {"bindings": [self.new("binding", node.end - len(pattern.name.encode()), node.end, name=pattern.name)]}
        elif isinstance(pattern, ast.MatchOr):
            node = self.of("pattern", pattern, form="or")
            node.parts = {"subpatterns": [self.pattern(each) for each in pattern.patterns]}
        elif isinstance(pattern, ast.MatchSequence):
            node = self.of("pattern", pattern, form="sequence")
            node.parts = {"subpatterns": [self.pattern(each) for each in pattern.patterns]}
        elif isinstance(pattern, ast.MatchMapping):
            node = self.of("pattern", pattern, form="mapping")
            node.parts = {"subpatterns": [self.pattern(each) for each in pattern.patterns]}
            if pattern.rest is not None:
                low, high = self.named_at(pattern.rest, node.start, node.end)
                node.parts["bindings"] = [self.new("binding", low, high, name=pattern.rest)]
        else:
            node = self.of("pattern", pattern, form="record")
            node.parts = {"constrainedType": self.type(pattern.cls),
                          "subpatterns": [self.pattern(each) for each in [*pattern.patterns, *pattern.kwd_patterns]]}
        node.parts = {role: held for role, held in node.parts.items() if held != []}
        return node

    # Comments -----------------------------------------------------------------------------------------------------

    def comments(self, root: Node, nodes: list[Node]) -> list[dict[str, object]]:
        """Every comment of the module: its line comments, attached to the statement they lead or trail, and its
        docstrings, attached to what they document."""
        found: list[tuple[int, int, str, Node | None, bool]] = []
        text = self.source.decode(errors="replace")
        lines = text.splitlines(keepends=True)
        for token in tokenize.generate_tokens(io.StringIO(text).readline):
            if token.type != tokenize.COMMENT:
                continue
            line, column = token.start
            start = self.at(line, len(lines[line - 1][:column].encode()))
            end = start + len(token.string.encode())
            owner, trailing = self.owner_of(start, end, nodes)
            found.append((start, end, "line", owner, trailing))
        for literal, owner in self.docstrings:
            start, end = self.span(literal)
            found.append((start, end, "doc", owner, False))
        comments = []
        for index, (start, end, form, owner, trailing) in enumerate(sorted(found, key=lambda each: each[0])):
            comment = {"id": index, "form": form, "text": self.text(start, end), "span": [start, end, self.line(start)]}
            if owner is not None:
                comment["attached"] = owner.id
            if trailing:
                comment["trailing"] = True
            if form == "line":
                comment["fragment"] = fragment(self.text(start, end)[1:])
            comments.append(comment)
        return comments

    def owner_of(self, start: int, end: int, nodes: list[Node]) -> tuple[Node | None, bool]:
        line_start = self.source.rfind(b"\n", 0, start) + 1
        if self.source[line_start:start].strip():
            owners = [node for node in nodes if line_start <= node.start < start and node.kind not in ("file", "module", "block")]
            return (owners[0] if owners else None), True
        after, blank = end, False
        while after < len(self.source):
            if self.source[after:after + 1] == b"\n" and self.source[after + 1:].lstrip(b" \t\r").startswith(b"\n"):
                blank = True
            if self.source[after:after + 1] in (b" ", b"\t", b"\r", b"\n"):
                after += 1
            elif self.source[after:after + 1] == b"#":
                after = self.source.find(b"\n", after)
                after = len(self.source) if after < 0 else after
            else:
                break
        if blank:
            return None, False
        owners = [node for node in nodes if node.start == after and node.kind not in ("file", "module", "block")]
        return (owners[0] if owners else None), False


def is_union(annotation: ast.expr) -> bool:
    """Whether an annotation is written `A | B`."""
    return isinstance(annotation, ast.BinOp) and isinstance(annotation.op, ast.BitOr)


def fragment(words: str) -> dict[str, object]:
    """What Python's parser makes of a comment's words read as code; the shared rule decides whether it is code."""
    text = words.strip()
    try:
        body = ast.parse(text).body if text else []
    except (SyntaxError, ValueError):
        return {"context": "statement", "diagnostics": 1, "statements": 0}
    read: dict[str, object] = {"context": "statement", "diagnostics": 0, "statements": len(body)}
    if len(body) == 1 and isinstance(body[0], ast.Expr):
        # The lone expression's kind is what the translation makes of it, as for any expression of the file.
        read["expressionKind"] = Translator(text.encode(), "", lambda name: None, {}).expression(body[0].value).kind
    return read


def numbered(root: Node) -> list[Node]:
    """Numbers every node in pre-order, its children in source order, and lists them by number."""
    order: list[Node] = []
    pending = [root]
    while pending:
        node = pending.pop()
        node.id = len(order)
        order.append(node)
        pending.extend(reversed(node.children()))
    return order


def wire(node: Node, starts: list[int]) -> dict[str, object]:
    """The node as the stream writes it, its line read off the file's line starts."""
    out: dict[str, object] = {"id": node.id, "kind": node.kind, "span": [node.start, node.end, bisect.bisect_right(starts, node.start)]}
    out.update(node.facts)
    if node.parts:
        out["parts"] = {role: [wire(child, starts) for child in held] if isinstance(held, list) else wire(held, starts)
                        for role, held in node.parts.items()}
    if node.links:
        out["links"] = {name: [each.id for each in target] if isinstance(target, list) else target.id for name, target in node.links.items()}
    return out
