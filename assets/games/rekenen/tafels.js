/**
 * Tafels (multiplication tables) game logic.
 *
 * A round practises one table (2–10): ten sums "multiplier × table" for multipliers 1–10,
 * in random order. A task is solved once the pupil enters the right answer.
 *
 * Pure ES module: no DOM access, no bare (importmap) imports, randomness injected.
 * The DOM wiring lives in assets/controllers/tafels_controller.js.
 */

import { shuffle } from '../shared/random.js';

export const MIN_TABLE = 2;
export const MAX_TABLE = 10;
export const ANSWER_MAX_LENGTH = 3;

/**
 * @typedef {object} Task
 * @property {number} id
 * @property {number} multiplier
 * @property {number} table
 * @property {number} answer
 * @property {boolean} solved
 */

/**
 * @typedef {object} Round
 * @property {number} table
 * @property {Task[]} tasks
 */

/**
 * Creates a new round for one table, with the ten sums in random order.
 *
 * @param {number} table 2–10
 * @param {() => number} [random] source of randomness in [0, 1)
 * @returns {Round}
 */
export function createRound(table, random = Math.random) {
    if (!Number.isInteger(table) || table < MIN_TABLE || table > MAX_TABLE) {
        throw new RangeError(`Table must be an integer from ${MIN_TABLE} to ${MAX_TABLE}`);
    }

    const tasks = Array.from({ length: 10 }, (_, index) => {
        const multiplier = index + 1;

        return { id: multiplier, multiplier, table, answer: multiplier * table, solved: false };
    });

    return { table, tasks: shuffle(tasks, random) };
}

/**
 * Whether `input` is exactly the right answer. Only plain digits are accepted.
 *
 * @param {Task} task
 * @param {string} input
 * @returns {boolean}
 */
export function checkAnswer(task, input) {
    return /^\d+$/.test(input) && Number(input) === task.answer;
}

/**
 * Returns a new round with the given task marked as solved.
 *
 * @param {Round} round
 * @param {number} taskId
 * @returns {Round}
 */
export function solveTask(round, taskId) {
    return {
        ...round,
        tasks: round.tasks.map((task) => (task.id === taskId ? { ...task, solved: true } : task)),
    };
}

/**
 * @param {Round} round
 * @returns {Task[]}
 */
export function remainingTasks(round) {
    return round.tasks.filter((task) => !task.solved);
}

/**
 * @param {Round} round
 * @returns {boolean}
 */
export function isRoundComplete(round) {
    return remainingTasks(round).length === 0;
}

/**
 * @param {Round} round
 * @param {number} taskId
 * @returns {Task | undefined}
 */
export function findTask(round, taskId) {
    return round.tasks.find((task) => task.id === taskId);
}

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
 * @param {string} value
 * @returns {string}
 */
export function removeLastDigit(value) {
    return value.slice(0, -1);
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
