/**
 * Answer input shared by the bubble games: digits typed on the keypad or keyboard, and letters
 * typed on the keyboard (dictee).
 *
 * Pure ES module: no DOM access and no bare (importmap) imports.
 */

/** Longest answer in any game: 12 × 10 = 120, 100 for erbij en eraf. */
export const ANSWER_MAX_LENGTH = 3;

/**
 * Appends a digit typed on the keypad or keyboard. Non-digits and input beyond
 * `maxLength` are ignored.
 *
 * @param {string} value
 * @param {string} digit
 * @param {number} [maxLength]
 * @returns {string}
 */
export function appendDigit(value, digit, maxLength = ANSWER_MAX_LENGTH) {
    if (!/^\d$/.test(digit) || value.length >= maxLength) {
        return value;
    }

    return value + digit;
}

/**
 * Appends a letter typed on the keyboard, in lowercase. Anything else (digits, spaces, keys like
 * "Enter") and input beyond `maxLength` are ignored.
 *
 * @param {string} value
 * @param {string} letter
 * @param {number} maxLength
 * @returns {string}
 */
export function appendLetter(value, letter, maxLength) {
    if (!/^\p{L}$/u.test(letter) || [...value].length >= maxLength) {
        return value;
    }

    return value + letter.toLowerCase();
}

/**
 * @param {string} value
 * @returns {string}
 */
export function removeLastDigit(value) {
    return value.slice(0, -1);
}
