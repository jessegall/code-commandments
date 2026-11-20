abstract class WrapSize {
    abstract readonly width: number
}

class SampleWrap extends WrapSize { readonly width = 10 }
class MiniWrap extends WrapSize { readonly width = 15 }
class StandardWrap extends WrapSize { readonly width = 30 }
class WideWrap extends WrapSize { readonly width = 50 }
class DisplayWrap extends WrapSize { readonly width = 100 }

export function publishWrapChoices(publish: (choices: (typeof WrapSize)[]) => void): void {
    // @sin ListMirrorsFolder
    publish([SampleWrap, MiniWrap, StandardWrap, WideWrap, DisplayWrap])
}

// @righteous ListMirrorsFolder
export function availableAtKiosk(): (typeof WrapSize)[] {
    return [MiniWrap, StandardWrap]
}

class WrapFinish {
    static readonly Matte = 'matte'
}

function chooseFinish(word: string, send: (word: string) => void): void {
    send(word)
}

// @fixed UnnamedVocabularyLiteral
export function sendByName(send: (word: string) => void): void {
    chooseFinish(WrapFinish.Matte, send)
}

export function sendByLiteral(send: (word: string) => void): void {
    // @sin UnnamedVocabularyLiteral
    chooseFinish('matte', send)
}

// @righteous UnnamedVocabularyLiteral
export function sendOther(send: (word: string) => void): void {
    chooseFinish('custom', send)
}
