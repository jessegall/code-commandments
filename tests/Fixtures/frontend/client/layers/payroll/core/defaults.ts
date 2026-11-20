// @sin NamespaceCycle
// @sin NamespaceDependency
import { caption as printable } from '../view/caption'

const placeholder = new Map<number, string>([[1, 'First cycle'], [2, 'End of cycle']])

export function fallback(): string {
    return String(placeholder.size) + ' pay cycles configured'
}

export function title(name: string): string {
    const period = placeholder.get(Number(name))
    if (period !== undefined) return printable(period)
    return name.toLowerCase()
}
