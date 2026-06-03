import { describe, expect, it } from 'vitest';
import { paginateBots, resolveBotEnvironmentTone } from './telegramBotAdmin.helpers';

describe('telegramBotAdmin helpers', () => {
    it('clamps pagination metadata when the requested page is outside the result range', () => {
        const bots = Array.from({ length: 3 }, (_, index) => ({ key: `bot-${index}` }));

        expect(paginateBots(bots, 99)).toMatchObject({
            totalPages: 1,
            safePage: 1,
            firstItem: 1,
            lastItem: 3,
            items: bots,
        });
    });

    it('returns an empty display range when no bots are available', () => {
        expect(paginateBots([], 2)).toMatchObject({
            totalPages: 1,
            safePage: 1,
            firstItem: 0,
            lastItem: 0,
            items: [],
        });
    });

    it('uses stable badge tones for known bot environments', () => {
        expect(resolveBotEnvironmentTone('production')).toBe('prod');
        expect(resolveBotEnvironmentTone('staging')).toBe('staging');
        expect(resolveBotEnvironmentTone('local')).toBe('dev');
        expect(resolveBotEnvironmentTone('shared')).toBe('qa');
    });
});
