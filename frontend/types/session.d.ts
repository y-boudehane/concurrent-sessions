// Session-related TypeScript type definitions

export interface Device {
    id: string;
    ip_address: string;
    user_agent: string;
    last_activity: string;
}

export interface SessionData {
    currentDevice: {
        ip_address: string;
        user_agent: string;
    };
    otherDevices: Device[];
}

export interface ConcurrentSessionDialogProps {
    open: boolean;
    sessionData?: SessionData | null;
}

export interface ConcurrentSessionDialogEmits {
    (e: 'close'): void;
}

// Laravel Echo types for WebSocket
declare global {
    interface Window {
        Echo: {
            private(channel: string): {
                listen(event: string, callback: () => void): any;
                error(callback: (error: any) => void): any;
            };
            leave?(channel: string): void;
        };
    }
}

// Inertia page props extension
declare module '@inertiajs/core' {
    interface PageProps {
        auth?: {
            user?: {
                id: number;
                name: string;
                email: string;
            };
        };
        sessionId?: string;
    }
}
