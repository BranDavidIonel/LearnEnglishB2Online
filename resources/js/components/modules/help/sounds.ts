// The pronunciation guide of the help page: showing where a sign sits in a transcription.
import { esc } from '@/utils/text';

/**
 * A transcription as safe HTML, with every place of the sign in bold: markSound('/kæt/', 'æ').
 * A row that explains several signs ("p t k") or a way of writing ("/ /", "…r") has nothing
 * to point at, so its examples stay plain.
 */
export function markSound(ipa: string, symbol: string): string {
    if (symbol === '' || /[\s…]/.test(symbol) || !ipa.includes(symbol)) {
        return esc(ipa);
    }

    return ipa
        .split(symbol)
        .map((part) => esc(part))
        .join(`<b>${esc(symbol)}</b>`);
}
