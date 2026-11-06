import { describe, expect, it } from 'vitest';
import {
    appendDigit,
    appendLetter,
    removeLastDigit,
} from '../../../../assets/games/shared/input.js';

describe('keypad input', () => {
    it('appends digits up to the maximum length', () => {
        expect(appendDigit('', '1')).toBe('1');
        expect(appendDigit('10', '0')).toBe('100');
        expect(appendDigit('100', '5')).toBe('100');
        expect(appendDigit('1', '2', 1)).toBe('1');
    });

    it('ignores anything that is not a single digit', () => {
        expect(appendDigit('1', 'a')).toBe('1');
        expect(appendDigit('1', '-')).toBe('1');
        expect(appendDigit('1', '12')).toBe('1');
    });

    it('removes the last digit', () => {
        expect(removeLastDigit('12')).toBe('1');
        expect(removeLastDigit('')).toBe('');
    });
});

describe('letter input', () => {
    it('appends letters in lowercase, up to the maximum length', () => {
        expect(appendLetter('', 'k', 5)).toBe('k');
        expect(appendLetter('ka', 'T', 5)).toBe('kat');
        expect(appendLetter('kamer', 's', 5)).toBe('kamer');
    });

    it('accepts letters with accents', () => {
        expect(appendLetter('caf', 'é', 5)).toBe('café');
    });

    it('ignores anything that is not a single letter', () => {
        expect(appendLetter('ka', '1', 5)).toBe('ka');
        expect(appendLetter('ka', ' ', 5)).toBe('ka');
        expect(appendLetter('ka', '-', 5)).toBe('ka');
        expect(appendLetter('ka', 'Enter', 5)).toBe('ka');
        expect(appendLetter('ka', 'tt', 5)).toBe('ka');
        expect(appendLetter('ka', '', 5)).toBe('ka');
    });
});
