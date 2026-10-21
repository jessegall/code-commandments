namespace Shop.Boards;

// Translations of one string, asked for by language. The first method answers false when the language is null,
// which its own type says cannot happen: either the type is wrong and callers do pass null, or nobody needed
// the check and every reader has to work that out. Below it the repair, and the two look-alikes that are not
// the sin: a guard that asserts the contract and dies, and one on a parameter typed as it really is.
public sealed class LanguageTexts(IReadOnlyDictionary<string, string> texts)
{
    public bool HasTranslation(Language language)
    {
        // @sin ImpossibleGuard
        if (language == null)
        {
            return false;
        }

        return texts.ContainsKey(language.Code);
    }

    // @fixed ImpossibleGuard
    public bool HasTextIn(Language language) => texts.ContainsKey(language.Code);

    // @righteous ImpossibleGuard
    public string TextIn(Language language)
    {
        if (language == null)
        {
            throw new ArgumentNullException(nameof(language));
        }

        return texts[language.Code];
    }

    // @righteous ImpossibleGuard
    public string TextOrPrimary(Language? language, string primary)
    {
        if (language == null)
        {
            return primary;
        }

        return texts.TryGetValue(language.Code, out var text) ? text : primary;
    }
}

public sealed class Language(string code)
{
    public string Code { get; } = code;
}
