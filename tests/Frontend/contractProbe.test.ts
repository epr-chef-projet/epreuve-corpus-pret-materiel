import { expect, expectTypeOf, it } from 'vitest';
import serialized from './generated/sample.json';

it('matches the generated PHP serialization and declared contract', () => {
    const expected: Tests.Fixtures.Contracts.ProbeData = {
        id: '019a23d0-0000-7000-8000-000000000001',
        occurredAt: '2026-10-09T12:30:00+00:00',
        state: 'ready',
        note: null,
        labels: ['fr', 'en'],
    };
    expectTypeOf<
        Tests.Fixtures.Contracts.ProbeData['occurredAt']
    >().toEqualTypeOf<string>();
    expectTypeOf<Tests.Fixtures.Contracts.ProbeState>().toEqualTypeOf<
        'ready' | 'complete'
    >();
    expect(serialized).toEqual(expected);
});
