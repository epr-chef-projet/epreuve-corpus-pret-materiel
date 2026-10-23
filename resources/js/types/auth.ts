import type { AuthenticatedUserData } from '@/types/generated/types/App/Modules/IdentityAccess/Identity/Data';
import type { SessionContextData } from '@/types/generated/types/App/Modules/IdentityAccess/Access/Data';

export type User = AuthenticatedUserData;
export type Auth = Omit<SessionContextData, 'locale'>;
