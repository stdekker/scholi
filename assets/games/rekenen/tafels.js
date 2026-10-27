/**
 * Tafels (multiplication tables) game logic.
 *
 * A round practises one table (2–10): ten sums "multiplier × table" for multipliers 1–10,
 * in random order. A task is solved once the pupil enters the right answer.
 *
 * Timer: the round's clock starts after a READY_COUNTDOWN_SECONDS countdown. The pupil passes
 * when all sums are solved within TIME_LIMIT_MS; after that they may still finish the round.
 *
 * Pure ES module: no DOM access, no bare (importmap) imports, randomness injected.
 * The DOM wiring lives in assets/controllers/tafels_controller.js.
 */

import { shuffle } from '../shared/random.js';

export const MIN_TABLE = 2;
export const MAX_TABLE = 10;
export const ANSWER_MAX_LENGTH = 3;
export const TIME_LIMIT_MS = 60_000;
export const READY_COUNTDOWN_SECONDS = 3;

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
 * @property {number | null} startedAt timestamp (ms) the clock started, null before that
 * @property {number | null} finishedAt timestamp (ms) the last task was solved
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

    return { table, tasks: shuffle(tasks, random), startedAt: null, finishedAt: null };
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
 * Returns a new round with the given task marked as solved. Solving the last task
 * records `finishedAt`.
 *
 * @param {Round} round
 * @param {number} taskId
 * @param {number} now current timestamp in ms
 * @returns {Round}
 */
export function solveTask(round, taskId, now) {
    const tasks = round.tasks.map((task) =>
        task.id === taskId ? { ...task, solved: true } : task,
    );
    const complete = tasks.every((task) => task.solved);

    return { ...round, tasks, finishedAt: complete ? (round.finishedAt ?? now) : null };
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
 * Starts the round's clock; does nothing if it is already running.
 *
 * @param {Round} round
 * @param {number} now current timestamp in ms
 * @returns {Round}
 */
export function startTimer(round, now) {
    return round.startedAt === null ? { ...round, startedAt: now } : round;
}

/**
 * Milliseconds left on the clock (never negative). Stops counting once the round is finished.
 *
 * @param {Round} round
 * @param {number} now current timestamp in ms
 * @returns {number}
 */
export function timeLeft(round, now) {
    if (round.startedAt === null) {
        return TIME_LIMIT_MS;
    }

    const elapsed = (round.finishedAt ?? now) - round.startedAt;

    return Math.max(0, TIME_LIMIT_MS - elapsed);
}

/**
 * @param {Round} round
 * @param {number} now current timestamp in ms
 * @returns {boolean}
 */
export function isTimeUp(round, now) {
    return round.startedAt !== null && timeLeft(round, now) === 0;
}

/**
 * Whether every sum was solved within the time limit.
 *
 * @param {Round} round
 * @returns {boolean}
 */
export function isPassed(round) {
    return (
        round.startedAt !== null &&
        round.finishedAt !== null &&
        round.finishedAt - round.startedAt <= TIME_LIMIT_MS
    );
}

/**
 * Formats milliseconds as m:ss, rounding up so the clock shows 0:00 only when time is up.
 *
 * @param {number} ms
 * @returns {string}
 */
export function formatTime(ms) {
    const seconds = Math.ceil(Math.max(0, ms) / 1000);

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
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
