import type { Passkey } from '@/types/auth';

export type ManagePasskeysProps = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

export type ManageTwoFactorProps = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};
