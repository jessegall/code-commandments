type LabelDetails = { code: string; urgent: boolean; paid: boolean }

// @fixed ConvertedArgument
function labelFor(details: LabelDetails): string {
    return details.code.trim()
}

// @righteous ConvertedArgument
export function orderLabel(details: LabelDetails): string {
    return labelFor(details)
}

// @fixed DerivedArgument
function formatDetails(details: LabelDetails): string {
    return details.code + (details.urgent ? ' express' : '')
}

// @righteous DerivedArgument
export function arbitraryCaption(text: string, details: LabelDetails): string {
    return text + formatDetails(details)
}

// @fixed ComputedBooleanArgument
function canDispatch(details: LabelDetails): boolean {
    return details.paid && details.urgent
}

// @righteous ComputedBooleanArgument
export function dispatchIfPaid(details: LabelDetails): boolean {
    return canDispatch(details)
}

// @righteous FlagArgument
export function allowDispatch(approved: boolean, count: number): boolean {
    return approved && count > 0
}

// @fixed FilledParameter
function occupyLabel(details: LabelDetails): LabelDetails {
    return { ...details, paid: true }
}

// @righteous FilledParameter
export function printPaidLabel(details: LabelDetails): string {
    return labelFor(occupyLabel(details))
}

// @fixed ParamResolvedFromParam
function readCode(details: LabelDetails): string {
    return details.code
}

// @righteous ParamResolvedFromParam
export function selectCode(index: Record<string, LabelDetails>, key: string): string {
    return readCode(index[key]) + Object.keys(index).length
}
