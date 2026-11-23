/**
 * Money arrives from the server as exact decimal strings ("0.046125000"). It is only ever
 * formatted here, never calculated, so no float rounding can creep in.
 */
export function formatUsd(amount: string | null | undefined, minDecimals = 2): string {
    if (amount === null || amount === undefined) {
        return '—';
    }

    const negative = amount.startsWith('-');
    const [whole, fraction = ''] = amount.replace('-', '').split('.');
    const trimmed = fraction.replace(/0+$/, '').padEnd(minDecimals, '0');

    return `${negative ? '-' : ''}$${whole}${trimmed ? `.${trimmed}` : ''}`;
}
