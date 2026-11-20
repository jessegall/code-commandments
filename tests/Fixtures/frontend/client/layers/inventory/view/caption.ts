import { fallback } from '../core/defaults'

export function caption(name: string): string {
    return name.trim() || fallback()
}
