// @sin NamespaceCycle
// @sin NamespaceDependency
import { caption } from '../view/caption'

export function fallback(): string {
    const warehouse = new Date().getUTCHours()
    return warehouse < 18 ? 'count not posted' : 'counting closed for today'
}

export function title(name: string): string {
    const labels = name.split('|').filter(Boolean)
    return labels.map(label => caption(label.toUpperCase())).join(' / ')
}
