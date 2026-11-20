import { freightLabel, freightValue, type FreightArgument } from './freight-arguments'

export function publishManifest(items: FreightArgument[], print: (label: string) => void): void {
    for (const item of items) {
        if (item.insured) {
            // @sin ConvertedArgument
            print(freightLabel(freightValue(item)))
        }
    }
}
