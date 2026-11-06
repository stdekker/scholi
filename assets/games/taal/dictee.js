/**
 * Rules of dictee: the game speaks a word and the pupil writes it. The level decides how much of
 * the word they see while they write it ("mode"):
 *
 * - full: every letter (copying it),
 * - partial: some letters, the others are blank,
 * - blanks: only a blank per letter, so they know how long the word is,
 * - hidden: nothing at all.
 *
 * Pure ES module: no DOM access, no bare (importmap) imports, randomness injected.
 * The DOM wiring lives in assets/controllers/dictee_controller.js, on top of the shared bubble game.
 */

import { shuffle } from '../shared/random.js';

/** Words in a round. */
export const ROUND_SIZE = 10;

export const MODES = Object.freeze({
    FULL: 'full',
    PARTIAL: 'partial',
    BLANKS: 'blanks',
    HIDDEN: 'hidden',
});

/**
 * What the pupil sees of a word: one entry per letter, the letter itself or null when it is a
 * blank. Empty when nothing is shown (mode hidden).
 *
 * @typedef {(string | null)[]} Hint
 */

/**
 * A word of a round, as the shared round (games/shared/round.js) takes it.
 *
 * @typedef {object} WordItem
 * @property {string} answer the word, in lowercase
 * @property {Hint} hint
 */

/**
 * Lowercases and trims what the pupil typed, so "Kat " is the word "kat".
 *
 * @param {string} input
 * @returns {string}
 */
export function normalizeWord(input) {
    return input.trim().normalize('NFC').toLowerCase();
}

/**
 * Whether `input` is exactly the word (capitals and surrounding spaces don't matter).
 *
 * @param {{answer: number | string}} task
 * @param {string} input
 * @returns {boolean}
 */
export function checkWord(task, input) {
    return normalizeWord(input) === task.answer;
}

/**
 * What to show of `word` in the given mode. In mode partial about half of the letters is blank:
 * at least one, and always at least one letter stays visible.
 *
 * @param {string} word
 * @param {string} mode one of MODES
 * @param {() => number} [random] source of randomness in [0, 1)
 * @returns {Hint}
 */
export function hintFor(word, mode, random = Math.random) {
    const letters = [...word];

    switch (mode) {
        case MODES.FULL:
            return letters;
        case MODES.PARTIAL:
            return partialHint(letters, random);
        case MODES.BLANKS:
            return letters.map(() => null);
        case MODES.HIDDEN:
            return [];
        default:
            throw new RangeError(`Unknown mode: ${mode}`);
    }
}

/**
 * @param {string[]} letters
 * @param {() => number} random
 * @returns {Hint}
 */
function partialHint(letters, random) {
    const blankCount = Math.min(letters.length - 1, Math.max(1, Math.ceil(letters.length / 2)));
    const blanks = new Set(shuffle([...letters.keys()], random).slice(0, blankCount));

    return letters.map((letter, index) => (blanks.has(index) ? null : letter));
}

/**
 * Writes a hint for a screen reader, e.g. "k, leeg, t". Empty when nothing is shown.
 *
 * @param {Hint} hint
 * @param {string} blankLabel what is said for a blank, e.g. "leeg"
 * @returns {string}
 */
export function describeHint(hint, blankLabel) {
    return hint.map((letter) => letter ?? blankLabel).join(', ');
}

/**
 * Picks the words of a round, each of them once and in random order. A list shorter than
 * `count` gives all its words.
 *
 * @param {readonly string[]} words
 * @param {() => number} [random] source of randomness in [0, 1)
 * @param {number} [count]
 * @returns {string[]}
 */
export function pickWords(words, random = Math.random, count = ROUND_SIZE) {
    return shuffle(words, random).slice(0, count);
}

/**
 * The words of a new round with what is shown of each of them.
 *
 * @param {readonly string[]} words every word the game knows
 * @param {string} mode one of MODES
 * @param {() => number} [random] source of randomness in [0, 1)
 * @param {number} [count]
 * @returns {WordItem[]}
 */
export function createWordItems(words, mode, random = Math.random, count = ROUND_SIZE) {
    return pickWords(words, random, count).map((word) => ({
        answer: word,
        hint: hintFor(word, mode, random),
    }));
}
