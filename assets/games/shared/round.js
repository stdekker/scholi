/**
 * A round of the bubble games: a list of sums ("tasks") to answer against the clock.
 *
 * A task is solved once the pupil enters the right answer; after MAX_ATTEMPTS wrong answers it
 * is frozen instead. The round is complete when every task is solved or frozen, whether or not
 * the time has run out.
 *
 * Timer: the round's clock starts after a READY_COUNTDOWN_SECONDS countdown. The pupil passes
 * when all sums are solved (none frozen) within the time limit; after that they may still finish.
 *
 * Pure ES module: no DOM access, no bare (importmap) imports, time injected.
 * The DOM wiring lives in assets/controllers/bubble_game.js; each game only supplies the sums.
 */

/** Wrong answers after which a bubble freezes. */
export const MAX_ATTEMPTS = 3;
export const READY_COUNTDOWN_SECONDS = 3;

/** @typedef {'plus' | 'min' | 'keer'} Operator */

/**
 * @typedef {object} Sum
 * @property {number} left
 * @property {Operator} operator
 * @property {number} right
 */

/**
 * @typedef {object} Task
 * @property {number} id
 * @property {number} left
 * @property {Operator} operator
 * @property {number} right
 * @property {number} answer
 * @property {boolean} solved
 * @property {number} wrongAttempts
 * @property {boolean} frozen true after MAX_ATTEMPTS wrong answers
 */

/**
 * @typedef {object} Round
 * @property {Task[]} tasks
 * @property {number} timeLimitMs
 * @property {number | null} startedAt timestamp (ms) the clock started, null before that
 * @property {number | null} finishedAt timestamp (ms) the last task was solved or frozen
 */

/**
 * The result of a sum.
 *
 * @param {Sum} sum
 * @returns {number}
 */
export function answerOf({ left, operator, right }) {
    switch (operator) {
        case 'plus':
            return left + right;
        case 'min':
            return left - right;
        case 'keer':
            return left * right;
        default:
            throw new RangeError(`Unknown operator: ${operator}`);
    }
}

/**
 * Creates a new round with one task per sum, in the given order.
 *
 * @param {Sum[]} sums at least one
 * @param {number} timeLimitMs
 * @returns {Round}
 */
export function createRound(sums, timeLimitMs) {
    if (sums.length === 0) {
        throw new RangeError('A round needs at least one sum');
    }

    return {
        tasks: sums.map(({ left, operator, right }, index) => ({
            id: index + 1,
            left,
            operator,
            right,
            answer: answerOf({ left, operator, right }),
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
 * Practice time of a finished round: the whole seconds from the clock's start to the end of the
 * round, also after the time limit and also when bubbles froze. 0 while the round is not finished.
 *
 * @param {Round} round
 * @returns {number}
 */
export function practiceSeconds(round) {
    const duration = roundDuration(round);

    return duration === null ? 0 : Math.floor(duration / 1000);
}
