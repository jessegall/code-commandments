# Translations of one string, asked for by language. The first method answers False when the language is None,
# which its own annotation says cannot happen: either the annotation is wrong and callers do pass None, or
# nobody needed the check and every reader has to work that out. Below it, the two look-alikes that are not the
# sin: a guard that asserts the contract and dies, and one on a parameter annotated as it really is.


class Language:
    def __init__(self, code: str) -> None:
        self.code = code


class LanguageTexts:
    def __init__(self, texts: dict[str, str]) -> None:
        self.texts = texts

    def has_translation(self, language: Language) -> bool:
        # @sin ImpossibleGuard
        if language is None:
            return False

        return language.code in self.texts

    # @fixed ImpossibleGuard
    def has_text_in(self, language: Language) -> bool:
        return language.code in self.texts

    # @righteous ImpossibleGuard
    def text_in(self, language: Language) -> str:
        if language is None:
            raise ValueError("a language is wanted")

        return self.texts[language.code]

    # @righteous ImpossibleGuard
    def text_or_primary(self, language: Language | None, primary: str) -> str:
        if language is None:
            return primary

        return self.texts.get(language.code, primary)
