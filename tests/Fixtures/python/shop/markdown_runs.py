"""An end-of-day markdown run over the bakery shelf."""


class Markdown:
    def applies_to(self, sku: str) -> bool:
        return sku.startswith("BAKE")


class MarkdownRun:
    def __init__(self) -> None:
        self.markdown: Markdown | None = None

    def run(self, markdown: Markdown, skus: list[str]) -> list[str]:
        self.markdown = markdown
        return [sku for sku in skus if self.reduced(sku)]

    def reduced(self, sku: str) -> bool:
        # @sin MaskedInvariant
        return self.markdown.applies_to(sku) if self.markdown else False


class HonestMarkdownRun:
    # @fixed MaskedInvariant
    def run(self, markdown: Markdown, skus: list[str]) -> list[str]:
        return [sku for sku in skus if markdown.applies_to(sku)]
