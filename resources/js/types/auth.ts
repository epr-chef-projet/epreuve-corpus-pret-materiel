import type { AuthenticatedUserData } from '@/types/generated/types/App/Modules/IdentityAccess/Accounts/Data';

export type User = AuthenticatedUserData;
export type Auth = { user: User | null };
