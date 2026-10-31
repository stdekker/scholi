/**
 * Text helpers shared by all games.
 *
 * Pure ES module: no DOM access and no bare (importmap) imports.
 */

/** How each operator is written in a sum (− is U+2212, the minus sign). */
export const OPERATOR_SYMBOLS = Object.freeze({ plus: '+', min: '−', keer: '×' });

/**
 * Writes a sum without its answer, e.g. "7 + 5" or "3 × 6".
 *
 * @param {import('./round.js').Sum} sum
 * @returns {string}
 */
export function formatSum({ left, operator, right }) {
    return `${left} ${OPERATOR_SYMBOLS[operator]} ${right}`;
}

/**
 * Formats milliseconds as m:ss, rounding up so the clock shows 0:00 only when time is up.
 * Mirrors App\Game\RoundTime::format().
 *
 * @param {number} ms
 * @returns {string}
 */
export function formatTime(ms) {
    const seconds = Math.ceil(Math.max(0, ms) / 1000);

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

/**
 * Fills `%name%` placeholders in a (translated) message.
 *
 * @param {string} template e.g. "Nog %count% bubbels"
 * @param {Record<string, string | number>} params e.g. { count: 7 }
 * @returns {string}
 */
export function format(template, params) {
    return template.replace(/%(\w+)%/g, (match, name) =>
        Object.hasOwn(params, name) ? String(params[name]) : match,
    );
}
