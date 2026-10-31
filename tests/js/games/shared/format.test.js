import { describe, expect, it } from 'vitest';
import {
    format,
    formatDuration,
    formatSum,
    formatTime,
} from '../../../../assets/games/shared/format.js';

describe('format', () => {
    it('fills placeholders', () => {
        expect(format('Nog %count% sommen', { count: 7 })).toBe('Nog 7 sommen');
        expect(format('%sum% = %answer%', { sum: '2 × 6', answer: 12 })).toBe('2 × 6 = 12');
    });

    it('leaves unknown placeholders untouched', () => {
        expect(format('Hallo %name%', {})).toBe('Hallo %name%');
    });
});

describe('formatSum', () => {
    it('writes the sum with its operator sign', () => {
        expect(formatSum({ left: 7, operator: 'plus', right: 5 })).toBe('7 + 5');
        expect(formatSum({ left: 13, operator: 'min', right: 6 })).toBe('13 − 6');
        expect(formatSum({ left: 3, operator: 'keer', right: 6 })).toBe('3 × 6');
    });
});

describe('formatTime', () => {
    it.each([
        [60_000, '1:00'],
        [59_001, '1:00'],
        [59_000, '0:59'],
        [9_500, '0:10'],
        [1, '0:01'],
        [0, '0:00'],
        [-500, '0:00'],
    ])('formats %i ms as %s', (ms, expected) => {
        expect(formatTime(ms)).toBe(expected);
    });
});

describe('formatDuration', () => {
    const texts = {
        secondsOne: '1 seconde',
        secondsMany: '%count% seconden',
        minutesOne: '1 minuut',
        minutesMany: '%count% minuten',
        both: '%minutes% en %seconds%',
    };

    it.each([
        [41_900, '41 seconden'],
        [1_000, '1 seconde'],
        [60_000, '1 minuut'],
        [65_400, '1 minuut en 5 seconden'],
        [121_000, '2 minuten en 1 seconde'],
        [120_000, '2 minuten'],
        [-5, '0 seconden'],
    ])('writes %i ms as %s', (ms, expected) => {
        expect(formatDuration(ms, texts)).toBe(expected);
    });
});
