import { describe, expect, it, vi } from 'vitest';
import { postJson } from '../../../../assets/games/shared/api.js';

describe('postJson', () => {
    it('posts the payload as JSON with cookies and keepalive', async () => {
        const response = { ok: true, status: 201 };
        const fetchFn = vi.fn().mockResolvedValue(response);

        await expect(postJson('/resultaat', { durationMs: 41000 }, fetchFn)).resolves.toBe(
            response,
        );

        expect(fetchFn).toHaveBeenCalledWith('/resultaat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: '{"durationMs":41000}',
            credentials: 'same-origin',
            keepalive: true,
        });
    });

    it('rejects when the server does not answer 2xx', async () => {
        const fetchFn = vi.fn().mockResolvedValue({ ok: false, status: 422 });

        await expect(postJson('/resultaat', {}, fetchFn)).rejects.toThrow('HTTP 422');
    });

    it('rejects when the request itself fails', async () => {
        const fetchFn = vi.fn().mockRejectedValue(new TypeError('offline'));

        await expect(postJson('/oefentijd', { seconds: 5 }, fetchFn)).rejects.toThrow('offline');
    });
});
