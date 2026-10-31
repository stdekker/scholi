import { describe, expect, it } from 'vitest';
import {
    answerOf,
    checkAnswer,
    createRound,
    findTask,
    frozenTasks,
    isPassed,
    isRoundComplete,
    isTimeUp,
    MAX_ATTEMPTS,
    registerWrongAnswer,
    remainingTasks,
    roundDuration,
    solveTask,
    startTimer,
    timeLeft,
} from '../../../../assets/games/shared/round.js';

/** A round of `count` sums "i × 4", one minute unless given. */
const roundOf = (count, timeLimitMs = 60_000) =>
    createRound(
        Array.from({ length: count }, (_, i) => ({ left: i + 1, operator: 'keer', right: 4 })),
        timeLimitMs,
    );

describe('createRound', () => {
    it('makes one task per sum, in order, with its answer', () => {
        const round = createRound(
            [
                { left: 7, operator: 'plus', right: 5 },
                { left: 13, operator: 'min', right: 6 },
                { left: 3, operator: 'keer', right: 6 },
            ],
            60_000,
        );

        expect(round.tasks).toEqual([
            {
                id: 1,
                left: 7,
                operator: 'plus',
                right: 5,
                answer: 12,
                solved: false,
                wrongAttempts: 0,
                frozen: false,
            },
            {
                id: 2,
                left: 13,
                operator: 'min',
                right: 6,
                answer: 7,
                solved: false,
                wrongAttempts: 0,
                frozen: false,
            },
            {
                id: 3,
                left: 3,
                operator: 'keer',
                right: 6,
                answer: 18,
                solved: false,
                wrongAttempts: 0,
                frozen: false,
            },
        ]);
        expect(round).toMatchObject({ timeLimitMs: 60_000, startedAt: null, finishedAt: null });
    });

    it('needs at least one sum', () => {
        expect(() => createRound([], 60_000)).toThrow(RangeError);
    });

    it('rejects an unknown operator', () => {
        expect(() => answerOf({ left: 1, operator: 'delen', right: 1 })).toThrow(RangeError);
    });
});

describe('checkAnswer', () => {
    const task = { id: 2, left: 2, operator: 'keer', right: 6, answer: 12, solved: false };

    it('accepts the right answer', () => {
        expect(checkAnswer(task, '12')).toBe(true);
        expect(checkAnswer(task, '012')).toBe(true);
    });

    it.each(['', '13', '1a', '-12', ' 12', '12.0'])('rejects %j', (input) => {
        expect(checkAnswer(task, input)).toBe(false);
    });
});

describe('round progress', () => {
    it('tracks solved tasks without modifying the original round', () => {
        const round = roundOf(10);
        const solved = solveTask(round, 4, 1000);

        expect(findTask(solved, 4)?.solved).toBe(true);
        expect(findTask(round, 4)?.solved).toBe(false);
        expect(remainingTasks(solved)).toHaveLength(9);
        expect(isRoundComplete(solved)).toBe(false);
    });

    it('is complete when every task is solved', () => {
        let round = roundOf(10);
        for (const task of round.tasks) {
            round = solveTask(round, task.id, 1000);
        }

        expect(remainingTasks(round)).toHaveLength(0);
        expect(isRoundComplete(round)).toBe(true);
    });
});

describe('timer', () => {
    const solveAll = (round, now) =>
        round.tasks.reduce((current, task) => solveTask(current, task.id, now), round);

    it('has the full time before it starts', () => {
        const round = roundOf(10);

        expect(round.startedAt).toBeNull();
        expect(timeLeft(round, 999_999)).toBe(60_000);
        expect(isTimeUp(round, 999_999)).toBe(false);
    });

    it('counts down from the first start only', () => {
        const round = startTimer(startTimer(roundOf(10), 1_000), 5_000);

        expect(round.startedAt).toBe(1_000);
        expect(timeLeft(round, 21_000)).toBe(40_000);
        expect(isTimeUp(round, 60_999)).toBe(false);
        expect(isTimeUp(round, 61_000)).toBe(true);
        expect(timeLeft(round, 90_000)).toBe(0);
    });

    it('passes when everything is solved within the minute', () => {
        const round = solveAll(startTimer(roundOf(10), 1_000), 61_000);

        expect(round.finishedAt).toBe(61_000);
        expect(isPassed(round)).toBe(true);
        expect(timeLeft(round, 120_000)).toBe(0);
    });

    it('does not pass when finished after the minute, but the round is complete', () => {
        const round = solveAll(startTimer(roundOf(10), 1_000), 61_001);

        expect(isRoundComplete(round)).toBe(true);
        expect(isPassed(round)).toBe(false);
    });

    it('stops the clock when the round is finished', () => {
        const round = solveAll(startTimer(roundOf(10), 0), 45_000);

        expect(timeLeft(round, 200_000)).toBe(15_000);
    });

    it('only records finishedAt for the last task', () => {
        const round = solveTask(startTimer(roundOf(10), 0), 1, 5_000);

        expect(round.finishedAt).toBeNull();
        expect(isPassed(round)).toBe(false);
    });

    it('uses a custom time limit', () => {
        const round = startTimer(roundOf(10, 30_000), 0);

        expect(round.timeLimitMs).toBe(30_000);
        expect(timeLeft(round, 10_000)).toBe(20_000);
        expect(isPassed(solveAll(round, 30_000))).toBe(true);
        expect(isPassed(solveAll(round, 30_001))).toBe(false);
    });

    it('reports the round duration once finished', () => {
        const started = startTimer(roundOf(10), 2_000);

        expect(roundDuration(roundOf(10))).toBeNull();
        expect(roundDuration(started)).toBeNull();
        expect(roundDuration(solveAll(started, 47_500))).toBe(45_500);
    });
});

describe('freezing after wrong answers', () => {
    const wrong = (round, id, times, now = 1_000) =>
        Array.from({ length: times }).reduce((r) => registerWrongAnswer(r, id, now), round);

    it('starts with no wrong attempts', () => {
        expect(roundOf(10).tasks.every((t) => t.wrongAttempts === 0 && !t.frozen)).toBe(true);
    });

    it('freezes a task on the third wrong answer', () => {
        const round = startTimer(roundOf(10), 0);

        expect(MAX_ATTEMPTS).toBe(3);
        expect(findTask(wrong(round, 1, 2), 1)).toMatchObject({ wrongAttempts: 2, frozen: false });
        expect(findTask(wrong(round, 1, 3), 1)).toMatchObject({ wrongAttempts: 3, frozen: true });
        expect(frozenTasks(wrong(round, 1, 3))).toHaveLength(1);
        expect(remainingTasks(wrong(round, 1, 3))).toHaveLength(9);
    });

    it('ignores further answers on frozen or solved tasks', () => {
        const round = startTimer(roundOf(10), 0);
        const frozen = wrong(round, 1, 5);

        expect(findTask(frozen, 1)?.wrongAttempts).toBe(3);
        expect(findTask(solveTask(frozen, 1, 2_000), 1)?.solved).toBe(false);
        expect(findTask(wrong(solveTask(round, 2, 1_000), 2, 3), 2)?.frozen).toBe(false);
    });

    it('completes the round when every task is solved or frozen, but does not pass', () => {
        let round = startTimer(roundOf(10), 0);
        const [first, ...rest] = round.tasks;
        round = wrong(round, first.id, 3, 5_000);
        for (const task of rest) {
            round = solveTask(round, task.id, 10_000);
        }

        expect(isRoundComplete(round)).toBe(true);
        expect(round.finishedAt).toBe(10_000);
        expect(isPassed(round)).toBe(false);
    });

    it('ends as soon as the last open task freezes, even within the time', () => {
        let round = startTimer(roundOf(10), 0);
        for (const task of round.tasks) {
            round = wrong(round, task.id, 3, 20_000);
        }

        expect(isRoundComplete(round)).toBe(true);
        expect(round.finishedAt).toBe(20_000);
        expect(frozenTasks(round)).toHaveLength(10);
        expect(isPassed(round)).toBe(false);
    });
});
