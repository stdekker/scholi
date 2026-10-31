/**
 * Tafels (multiplication tables) game logic: which sums a round asks.
 *
 * A round practises one table (1–12): ten sums "multiplier × table" for multipliers 1–10,
 * in random order (Dutch notation: 2 × 6 belongs to de tafel van 6). "Alle tafels" mixes
 * several tables and instead picks ALL_TABLES_COUNT different sums from all their sums.
 * Playing the round (answers, freezing, clock) is shared: see ../shared/round.js.
 *
 * Pure ES module: no DOM access, no bare (importmap) imports, randomness injected.
 * The DOM wiring lives in assets/controllers/tafels_controller.js.
 */

import { shuffle } from '../shared/random.js';

export const MIN_TABLE = 1;
export const MAX_TABLE = 12;
/** Bubbles in a round of several tables (the Dutch texts say "twintig"). */
export const ALL_TABLES_COUNT = 20;

/**
 * The sums of a round: the ten sums of one table in random order, or for several tables
 * ALL_TABLES_COUNT different sums picked at random from all their sums.
 *
 * @param {number[]} tables at least one, each an integer from MIN_TABLE to MAX_TABLE, no duplicates
 * @param {() => number} [random] source of randomness in [0, 1)
 * @returns {import('../shared/round.js').Sum[]}
 */
export function tafelsSums(tables, random = Math.random) {
    const isTable = (table) => Number.isInteger(table) && table >= MIN_TABLE && table <= MAX_TABLE;
    if (tables.length === 0 || new Set(tables).size !== tables.length || !tables.every(isTable)) {
        throw new RangeError(
            `Tables must be different integers from ${MIN_TABLE} to ${MAX_TABLE}, at least one`,
        );
    }

    const sums = tables.flatMap((table) =>
        range(1, 10).map((multiplier) => ({ left: multiplier, operator: 'keer', right: table })),
    );

    return shuffle(sums, random).slice(0, tables.length > 1 ? ALL_TABLES_COUNT : sums.length);
}

/**
 * @param {number} from
 * @param {number} to inclusive
 * @returns {number[]}
 */
function range(from, to) {
    return Array.from({ length: to - from + 1 }, (_, index) => from + index);
}
