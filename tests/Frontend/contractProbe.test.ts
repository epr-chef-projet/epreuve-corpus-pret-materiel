import type { ManagementPageData } from '@/types/generated/types/App/Modules/Catalog/Equipment/UseCases/ReadEquipment';
import catalog from './generated/catalog.json';
import type {
    EquipmentData,
    EquipmentPage,
    EquipmentQuery,
} from '@/types/generated/types/App/Modules/Catalog/PublicApi';
import type {
    ClosedOperationResultData,
    OperationResultData,
} from '@/types/generated/types/App/Infrastructure/Operations';
import { expect, expectTypeOf, it } from 'vitest';
import serialized from './generated/sample.json';
import identity from './generated/identity.json';
import type { User } from '@/types/auth';
import presentation from './generated/presentation.json';
import type { SessionContextData } from '@/types/generated/types/App/Modules/IdentityAccess/Access/Data';
import type { SessionCandidatePage } from '@/types/generated/types/App/Modules/IdentityAccess/PublicApi';
import type {
    IssueData,
    ValidationPresentationData,
} from '@/types/generated/types/App/Shared/Errors';
import type { NotificationEnvelopeData } from '@/types/generated/types/App/Shared/Realtime';

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

it('matches selected presentation, routing and notification JSON with their generated contracts', () => {
    const expected: {
        context: SessionContextData;
        issue: IssueData;
        validation: ValidationPresentationData;
        notification: NotificationEnvelopeData;
        candidates: SessionCandidatePage;
    } = {
        context: {
            user: { ...identity, role: 'manager' },
            broadcastToken: 'a'.repeat(64),
            authGeneration: 1,
            expiresAt: '2026-10-20T15:30:00.000Z',
            locale: 'en',
        },
        issue: {
            errorCode: 'identity_access.authentication_required',
            messageKey: 'issues.identity_access.authentication_required',
            parameters: {},
            correlationId: '019a23d0-0000-7000-8000-000000000003',
            outcome: 'notApplied',
            nextAction: { kind: 'signIn', messageKey: 'actions.signIn' },
        },
        validation: {
            locale: 'fr',
            fieldErrors: {
                login: [{ messageKey: 'validation.required', parameters: {} }],
            },
        },
        notification: {
            eventId: '019a23d0-0000-7000-8000-000000000003',
            contractVersion: 1,
            authGeneration: 1,
            type: 'catalog.invalidated',
            payload: { projection: 'catalog' },
        },
        candidates: {
            candidates: [
                {
                    actorId: identity.id,
                    role: 'manager',
                    broadcastToken: 'a'.repeat(64),
                },
            ],
            nextCursor: null,
        },
    };
    expectTypeOf<IssueData['outcome']>().toEqualTypeOf<
        'notApplied' | 'applied' | 'unknown'
    >();
    expectTypeOf<
        NotificationEnvelopeData['contractVersion']
    >().toEqualTypeOf<1>();
    expectTypeOf<NotificationEnvelopeData['type']>().toEqualTypeOf<
        | 'request.snapshot'
        | 'document.snapshot'
        | 'catalog.invalidated'
        | 'requests.invalidated'
    >();
    expect(presentation).toEqual(expected);
    expect(Array.isArray(presentation.issue.parameters)).toBe(false);
    expect(presentation.issue).not.toHaveProperty('fieldErrors');
});

it('matches real Catalog and Operations JSON with generated immutable contracts', () => {
    const expected: {
        equipment: EquipmentData;
        page: EquipmentPage;
        query: EquipmentQuery;
        operation: OperationResultData;
        closedOperation: ClosedOperationResultData;
        managementPage: ManagementPageData;
    } = {
        equipment: {
            equipmentId: '019a23d0-0000-7000-8000-000000000003',
            name: 'Perceuse électricité',
            description: null,
            category: 'Outillage',
            totalQuantity: 2,
            status: 'active',
            equipmentVersion: 1,
            image: null,
        },
        page: {
            items: [
                {
                    equipmentId: '019a23d0-0000-7000-8000-000000000003',
                    name: 'Perceuse électricité',
                    description: null,
                    category: 'Outillage',
                    totalQuantity: 2,
                    status: 'active',
                    equipmentVersion: 1,
                    image: null,
                },
            ],
            page: 1,
            perPage: 25,
            hasMore: false,
            observedAt: '2026-10-20T15:30:00.000Z',
        },
        query: {
            search: '%_',
            category: 'Outillage',
            page: 1,
            perPage: 25,
            audience: 'catalogManagement',
        },
        managementPage: {
            items: [
                {
                    equipmentId: '019a23d0-0000-7000-8000-000000000003',
                    name: 'Perceuse électricité',
                    category: 'Outillage',
                    totalQuantity: 2,
                    status: 'active',
                    equipmentVersion: 1,
                    image: null,
                },
            ],
            page: 1,
            perPage: 25,
            hasMore: false,
            observedAt: '2026-10-20T15:30:00.000Z',
        },
        operation: {
            status: 'applied',
            receipt: {
                operationKey: '019a23d0-0000-7000-8000-000000000003',
                operationType: 'catalog.equipment.create',
                resourceType: 'equipment',
                resourceId: '019a23d0-0000-7000-8000-000000000003',
                resultVersion: 1,
                recordedAt: '2026-10-20T15:30:00.000Z',
                replayed: false,
            },
        },
        closedOperation: {
            status: 'closed',
            receipt: null,
            closure: {
                operationKey: '019a23d0-0000-7000-8000-000000000003',
                operationType: 'catalog.equipment.create',
                closedAt: '2026-10-20T15:30:00.000Z',
            },
        },
    };
    expect(catalog).toEqual(expected);
    expectTypeOf<EquipmentData['status']>().toEqualTypeOf<
        'active' | 'archived'
    >();
    expectTypeOf<OperationResultData['status']>().toEqualTypeOf<
        'applied' | 'notEstablished'
    >();
    expectTypeOf<
        ClosedOperationResultData['status']
    >().toEqualTypeOf<'closed'>();
    expectTypeOf<ClosedOperationResultData['receipt']>().toEqualTypeOf<null>();
    expectTypeOf<
        ClosedOperationResultData['closure']['operationType']
    >().toEqualTypeOf<'catalog.equipment.create'>();
});
