import { expect, expectTypeOf, it } from 'vitest';
import serialized from './generated/sample.json';
import identity from './generated/identity.json';
import type { User } from '@/types/auth';

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

it('matches the real identity projection and its generated closed role type', () => {
    const expected: User = {
        id: '019a23d0-0000-7000-8000-000000000001',
        login: 'synthetic-manager',
        displayName: 'Compte témoin',
        workshopId: '019a23d0-0000-7000-8000-000000000002',
        workshopName: 'Atelier témoin',
        role: 'manager',
    };
    expectTypeOf<User['role']>().toEqualTypeOf<
        'borrower' | 'staff' | 'manager'
    >();
    expect(identity).toEqual(expected);
});
