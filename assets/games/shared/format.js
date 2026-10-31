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
 * Writes a duration in words, in whole seconds rounded down, e.g. "41 seconden" or
 * "1 minuut en 5 seconden".
 *
 * @param {number} ms
 * @param {{secondsOne: string, secondsMany: string, minutesOne: string, minutesMany: string, both: string}} texts
 *   translated texts: "1 seconde", "%count% seconden", "1 minuut", "%count% minuten",
 *   "%minutes% en %seconds%"
 * @returns {string}
 */
export function formatDuration(ms, texts) {
    const total = Math.floor(Math.max(0, ms) / 1000);
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    const secondsText = format(seconds === 1 ? texts.secondsOne : texts.secondsMany, {
        count: seconds,
    });
    if (minutes === 0) {
        return secondsText;
    }

    const minutesText = format(minutes === 1 ? texts.minutesOne : texts.minutesMany, {
        count: minutes,
    });

    return seconds === 0
        ? minutesText
        : format(texts.both, { minutes: minutesText, seconds: secondsText });
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
