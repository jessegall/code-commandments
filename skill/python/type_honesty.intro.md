An `X | None` that is never actually `None` is a lie the whole codebase pays for. Every reader has
to prove the value is there again — `if self.batch is None`, `self.batch.permits(sku) if self.batch else
False` — and one of those fallbacks answers a question for a state that cannot happen. Make the annotation
say what the design guarantees.