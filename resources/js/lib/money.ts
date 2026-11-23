/**
 * Money arrives from the server as exact decimal strings with 9 decimal places ("0.086362500").
 * It is only ever formatted here, never calculated. Intl formats a decimal string exactly
 * (no float on the way), and the "< $0.01" check uses BigInt nanodollars.
 */

export type MoneyDecimals = 2 | 4;

type Amount = string | null | undefined;

const NANODOLLAR_DIGITS = 9;

const formatters = new Map<MoneyDecimals, Intl.NumberFormat>();

function formatter(decimals: MoneyDecimals): Intl.NumberFormat {
    let numberFormat = formatters.get(decimals);

    if (!numberFormat) {
        numberFormat = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
        formatters.set(decimals, numberFormat);
    }

    return numberFormat;
}

function toNanodollars(amount: string): bigint {
    const match = /^(-?)(\d+)(?:\.(\d{1,9}))?$/.exec(amount.trim());

    if (!match) {
        throw new Error(`Not an exact dollar amount: [${amount}].`);
    }

    const nanodollars = BigInt(
        match[2] + (match[3] ?? '').padEnd(NANODOLLAR_DIGITS, '0'),
    );

    return match[1] === '-' ? -nanodollars : nanodollars;
}

/**
 * "$3.92", "$1,234.50", "< $0.01" for a non-zero amount below the smallest shown unit.
 * Use 4 decimals where sub-cent amounts matter (the admin report).
 */
export function formatUsd(amount: Amount, decimals: MoneyDecimals = 2): string {
    if (amount === null || amount === undefined) {
        return '—';
    }

    const nanodollars = toNanodollars(amount);
    const smallestUnit = 10n ** BigInt(NANODOLLAR_DIGITS - decimals);
    const magnitude = nanodollars < 0n ? -nanodollars : nanodollars;

    if (magnitude !== 0n && magnitude < smallestUnit) {
        const unit = formatter(decimals).format(
            `0.${'0'.repeat(decimals - 1)}1` as Intl.StringNumericLiteral,
        );

        return nanodollars < 0n ? `> -${unit}` : `< ${unit}`;
    }

    return formatter(decimals).format(amount as Intl.StringNumericLiteral);
}

/**
 * "$0.06 – $0.09". When both ends show the same cents, 4 decimals keep the range visible.
 */
export function formatUsdRange(min: string, max: string): string {
    let decimals: MoneyDecimals = 2;

    if (min !== max && formatUsd(min, 2) === formatUsd(max, 2)) {
        decimals = 4;
    }

    return `${formatUsd(min, decimals)} – ${formatUsd(max, decimals)}`;
}

/**
 * The exact stored value, to the nanodollar, for hover titles: "$0.086362500".
 */
export function exactUsd(amount: Amount): string | undefined {
    if (amount === null || amount === undefined) {
        return undefined;
    }

    const negative = amount.trim().startsWith('-');
    const [whole, fraction = ''] = amount.trim().replace('-', '').split('.');
    const grouped = BigInt(whole).toLocaleString('en-US');

    return `${negative ? '-' : ''}$${grouped}.${fraction.padEnd(NANODOLLAR_DIGITS, '0')}`;
}
