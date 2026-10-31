/**
 * Erbij en eraf (plussommen en minsommen) game logic: which sums a round asks.
 *
 * Six levels, roughly groep 3 to 5 (mirrors App\Game\ErbijErafLevel). Per level the pupil picks
 * erbij (+), eraf (−) or door elkaar (5 + 5). A round has ROUND_SIZE different sums, picked at
 * random from every sum the level allows. Terms are never 0 and answers never negative.
 * Playing the round (answers, freezing, clock) is shared: see ../shared/round.js.
 *
 * Pure ES module: no DOM access, no bare (importmap) imports, randomness injected.
 * The DOM wiring lives in assets/controllers/erbij_eraf_controller.js.
 */

import { shuffle } from '../shared/random.js';

export const LEVELS = Object.freeze({
    TOT_10: 1,
    TOT_20: 2,
    TOT_20_BRUG: 3,
    TIENTALLEN: 4,
    TOT_100: 5,
    TOT_100_BRUG: 6,
});
/** Mirrors App\Game\ErbijErafOperation. */
export const OPERATIONS = Object.freeze(['erbij', 'eraf', 'door-elkaar']);
export const ROUND_SIZE = 10;
const LARGEST_TERM = 100;

const units = (n) => n % 10;
const isTens = (n) => n % 10 === 0;

/**
 * Which sums "left + right" (plus) and "left − right" (min) belong to a level. Terms are 1–100;
 * for min, right ≤ left is checked before these rules.
 *
 * @type {Record<number, {plus: (a: number, b: number) => boolean, min: (a: number, b: number) => boolean}>}
 */
const RULES = {
    // 3 + 4, 9 − 5
    [LEVELS.TOT_10]: {
        plus: (a, b) => a + b <= 10,
        min: (a) => a <= 10,
    },
    // 12 + 5, 17 − 4: the units stay below ten.
    [LEVELS.TOT_20]: {
        plus: (a, b) => a >= 11 && a <= 19 && b <= 9 && units(a) + b <= 9,
        min: (a, b) => a >= 11 && a <= 19 && b <= units(a),
    },
    // 8 + 5, 13 − 6: over (or back across) the ten.
    [LEVELS.TOT_20_BRUG]: {
        plus: (a, b) => a >= 2 && a <= 9 && b >= 2 && b <= 9 && a + b > 10,
        min: (a, b) => a >= 11 && a <= 18 && b >= 2 && b <= 9 && b > units(a),
    },
    // 30 + 40, 90 − 50
    [LEVELS.TIENTALLEN]: {
        plus: (a, b) => isTens(a) && isTens(b) && a + b <= 100,
        min: (a, b) => isTens(a) && isTens(b) && b < a,
    },
    // 34 + 25, 68 − 25: the units stay below ten, above twenty, not only tens.
    [LEVELS.TOT_100]: {
        plus: (a, b) =>
            a >= 11 && a + b > 20 && a + b <= 99 && units(a) + units(b) <= 9 && !isTens(b),
        min: (a, b) => a >= 21 && a <= 99 && b < a && units(b) <= units(a) && !isTens(b),
    },
    // 38 + 7, 52 − 18: the units cross the ten.
    [LEVELS.TOT_100_BRUG]: {
        plus: (a, b) => a >= 11 && a + b > 20 && a + b <= 100 && units(a) + units(b) >= 10,
        min: (a, b) => a >= 21 && b < a && units(b) > units(a),
    },
};

/** @type {Map<string, import('../shared/round.js').Sum[]>} */
const cache = new Map();

/**
 * Every sum a level allows for one operator, in a fixed order.
 *
 * @param {number} level one of LEVELS
 * @param {'plus' | 'min'} operator
 * @returns {import('../shared/round.js').Sum[]}
 */
export function allSums(level, operator) {
    const rule = RULES[level]?.[operator];
    if (!rule) {
        throw new RangeError(`Unknown level or operator: ${level} ${operator}`);
    }

    const key = `${level}:${operator}`;
    if (!cache.has(key)) {
        const sums = [];
        for (let left = 1; left <= LARGEST_TERM; left++) {
            for (let right = 1; right <= LARGEST_TERM; right++) {
                if ((operator === 'plus' || right <= left) && rule(left, right)) {
                    sums.push({ left, operator, right });
                }
            }
        }
        cache.set(key, sums);
    }

    return cache.get(key);
}

/**
 * The sums of a round: ROUND_SIZE different sums in random order; "door elkaar" has half plus
 * and half min.
 *
 * @param {number} level one of LEVELS
 * @param {string} operation one of OPERATIONS
 * @param {() => number} [random] source of randomness in [0, 1)
 * @returns {import('../shared/round.js').Sum[]}
 */
export function pickSums(level, operation, random = Math.random) {
    const pick = (operator, count) => shuffle(allSums(level, operator), random).slice(0, count);

    switch (operation) {
        case 'erbij':
            return pick('plus', ROUND_SIZE);
        case 'eraf':
            return pick('min', ROUND_SIZE);
        case 'door-elkaar':
            return shuffle(
                [...pick('plus', ROUND_SIZE / 2), ...pick('min', ROUND_SIZE / 2)],
                random,
            );
        default:
            throw new RangeError(`Unknown operation: ${operation}`);
    }
}
