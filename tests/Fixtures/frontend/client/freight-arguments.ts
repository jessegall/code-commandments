export type FreightArgument = { label: string; packed: boolean; insured: boolean }

export function freightLabel(value: string): string {
    return String(value)
}

export function freightValue(value: FreightArgument): string {
    return value.label
}

export function freightLabels(first: FreightArgument, second: FreightArgument): string[] {
    // @sin ConvertedArgument
    const one = freightLabel(freightValue(first))
    // @sin ConvertedArgument
    const two = freightLabel(freightValue(second))
    return [one, two]
}

function freightDetails(value: FreightArgument, reading: string): string {
    return [value.packed, reading].join(':')
}

export function freightReport(first: FreightArgument, second: FreightArgument): string[] {
    // @sin DerivedArgument
    const one = freightDetails(first, first.label)
    // @sin DerivedArgument
    const two = freightDetails(second, second.label)
    return [one, two]
}

function freightReady(first: boolean, second: boolean): boolean {
    return first && second
}

export function freightAvailability(first: FreightArgument, second: FreightArgument): boolean[] {
    // @sin ComputedBooleanArgument
    const one = freightReady(first.packed, first.insured)
    // @sin ComputedBooleanArgument
    const two = freightReady(second.packed, second.insured)
    return [one, two]
}

// @sin FlagArgument
export function freightStatus(urgent: boolean, publish: (state: string) => void): void {
    if (urgent) {
        publish('packed')
    } else {
        publish('insured')
    }
}

// @fixed FlagArgument
export function freightStatusOf(value: FreightArgument): string {
    return value.packed ? 'packed' : 'insured'
}

// @sin FilledParameter
// @sin FeatureEnvy
function freightFill(value: FreightArgument): void {
    value.packed = true
}

export function freightRead(value: FreightArgument): boolean {
    freightFill(value)
    return value.packed
}

// @sin ParamResolvedFromParam
export function freightFind(choices: Readonly<Record<string, FreightArgument>>, key: string): string {
    return choices[key].label
}

export function freightDisplay(
    value: FreightArgument,
    // @sin UnusedParameter
    format: string,
): string {
    return value.label
}

// @righteous UnusedParameter
export function freightFormat(value: FreightArgument, format: string): string {
    return format + value.label
}
