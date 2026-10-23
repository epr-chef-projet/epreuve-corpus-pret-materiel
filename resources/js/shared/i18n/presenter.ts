export interface MessagePresenter {
    t(key: string): string;
    t(key: string, parameters: Record<string, unknown>): string;
    te(key: string): boolean;
}
