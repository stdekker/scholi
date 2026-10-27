/**
 * Tafels (multiplication tables) game logic.
 *
 * A round practises one table (2–10): ten sums "multiplier × table" for multipliers 1–10,
 * in random order. "Alle tafels" (table ALL_TABLES) instead picks ALL_TABLES_COUNT different
 * sums from all tables. A task is solved once the pupil enters the right answer; after
 * MAX_ATTEMPTS wrong answers it is frozen instead. The round is complete when every task is
 * solved or frozen, whether or not the time has run out.
 *
 * Timer: the round's clock starts after a READY_COUNTDOWN_SECONDS countdown. The pupil passes
 * when all sums are solved (none frozen) within TIME_LIMIT_MS; after that they may still finish.
 *
 * Pure ES module: no DOM access, no bare (importmap) imports, randomness injected.
 * The DOM wiring lives in assets/controllers/tafels_controller.js.
 */

import { shuffle } from '../shared/random.js';

export const MIN_TABLE = 2;
export const MAX_TABLE = 10;
/** Table number for "alle tafels" (mirrors App\Game\Tafels::ALL). */
export const ALL_TABLES = 0;
/** Wrong answers after which a bubble freezes. */
export const MAX_ATTEMPTS = 3;
/** Bubbles in an "alle tafels" round (the Dutch texts say "twintig"). */
export const ALL_TABLES_COUNT = 20;
export const ANSWER_MAX_LENGTH = 3;
/** Default only: the page passes the server's limit (App\Game\Tafels::TIME_LIMIT_MS) to createRound. */
export const TIME_LIMIT_MS = 60_000;
export const READY_COUNTDOWN_SECONDS = 3;

/**
 * @typedef {object} Task
 * @property {number} id
 * @property {number} multiplier
 * @property {number} table
 * @property {number} answer
 * @property {boolean} solved
 * @property {number} wrongAttempts
 * @property {boolean} frozen true after MAX_ATTEMPTS wrong answers
 */

/**
 * @typedef {object} Round
 * @property {number} table 2–10, or ALL_TABLES
 * @property {Task[]} tasks
 * @property {number} timeLimitMs
 * @property {number | null} startedAt timestamp (ms) the clock started, null before that
 * @property {number | null} finishedAt timestamp (ms) the last task was solved
 */

/**
 * Creates a new round: the ten sums of one table in random order, or for ALL_TABLES
 * ALL_TABLES_COUNT different sums picked at random from all tables.
 *
 * @param {number} table 2–10, or ALL_TABLES
 * @param {() => number} [random] source of randomness in [0, 1)
 * @param {number} [timeLimitMs]
 * @returns {Round}
 */
export function createRound(table, random = Math.random, timeLimitMs = TIME_LIMIT_MS) {
    const isAll = table === ALL_TABLES;
    if (!isAll && (!Number.isInteger(table) || table < MIN_TABLE || table > MAX_TABLE)) {
        throw new RangeError(
            `Table must be ${ALL_TABLES} or an integer from ${MIN_TABLE} to ${MAX_TABLE}`,
        );
    }

    const tables = isAll ? range(MIN_TABLE, MAX_TABLE) : [table];
    const sums = tables.flatMap((t) =>
        range(1, 10).map((multiplier) => ({ multiplier, table: t })),
    );
    const picked = shuffle(sums, random).slice(0, isAll ? ALL_TABLES_COUNT : sums.length);

    return {
        table,
        tasks: picked.map(({ multiplier, table: t }, index) => ({
            id: index + 1,
            multiplier,
            table: t,
            answer: multiplier * t,
            solved: false,
            wrongAttempts: 0,
            frozen: false,
        })),
        timeLimitMs,
        startedAt: null,
        finishedAt: null,
    };
}

/**
 * @param {number} from
 * @param {number} to inclusive
 * @returns {number[]}
 */
function range(from, to) {
    return Array.from({ length: to - from + 1 }, (_, index) => from + index);
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
    return updateTask(round, taskId, now, (task) => ({ ...task, solved: true }));
}

/**
 * Registers a wrong answer; the MAX_ATTEMPTS-th wrong answer freezes the task. Freezing the
 * last open task completes the round (records `finishedAt`).
 *
 * @param {Round} round
 * @param {number} taskId
 * @param {number} now current timestamp in ms
 * @returns {Round}
 */
export function registerWrongAnswer(round, taskId, now) {
    return updateTask(round, taskId, now, (task) => {
        const wrongAttempts = task.wrongAttempts + 1;

        return { ...task, wrongAttempts, frozen: wrongAttempts >= MAX_ATTEMPTS };
    });
}

/**
 * @param {Round} round
 * @param {number} taskId
 * @param {number} now
 * @param {(task: Task) => Task} change
 * @returns {Round}
 */
function updateTask(round, taskId, now, change) {
    const tasks = round.tasks.map((task) =>
        task.id === taskId && !task.solved && !task.frozen ? change(task) : task,
    );
    const complete = tasks.every((task) => task.solved || task.frozen);

    return { ...round, tasks, finishedAt: complete ? (round.finishedAt ?? now) : null };
}

/**
 * @param {Round} round
 * @returns {Task[]}
 */
export function frozenTasks(round) {
    return round.tasks.filter((task) => task.frozen);
}

/**
 * @param {Round} round
 * @returns {Task[]}
 */
export function remainingTasks(round) {
    return round.tasks.filter((task) => !task.solved && !task.frozen);
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
        return round.timeLimitMs;
    }

    const elapsed = (round.finishedAt ?? now) - round.startedAt;

    return Math.max(0, round.timeLimitMs - elapsed);
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
        frozenTasks(round).length === 0 &&
        round.finishedAt - round.startedAt <= round.timeLimitMs
    );
}

/**
 * How long the pupil took for the whole round, or null while it is not finished.
 *
 * @param {Round} round
 * @returns {number | null}
 */
export function roundDuration(round) {
    if (round.startedAt === null || round.finishedAt === null) {
        return null;
    }

    return round.finishedAt - round.startedAt;
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
