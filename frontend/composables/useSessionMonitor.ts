import { onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

export function useSessionMonitor() {
    const isDisconnected = ref(false);
    const showConcurrentSessionDialog = ref(false);
    const sessionData = ref<any>(null);
    const isFetchingSessionData = ref(false);
    const currentSessionId = ref<string | null>(null);

    let echoChannel: any = null;

    const fetchSessionDataBeforeDialog = async () => {
        isFetchingSessionData.value = true;
        
        try {
            const response = await axios.get('/api/session-data');
            sessionData.value = response.data;
            showConcurrentSessionDialog.value = true;
        } catch (err: any) {
            console.error('Failed to fetch session data:', err);
            showConcurrentSessionDialog.value = true;
        } finally {
            isFetchingSessionData.value = false;
        }
    };

    const setupSessionListener = (userId: number, sessionId: string) => {
        currentSessionId.value = sessionId;

        if (!window.Echo) {
            console.warn('Laravel Echo not initialized. Real-time features disabled.');
            return;
        }

        echoChannel = window.Echo.private(`session.${sessionId}`)
            .listen('.new-login-detected', () => {
                fetchSessionDataBeforeDialog();
            })
            .listen('.session-disconnected', () => {
                isDisconnected.value = true;

                setTimeout(() => {
                    router.reload();
                }, 3000);
            })
            .listen('.session-confirmed', () => {
                showConcurrentSessionDialog.value = false;
                router.reload();
            })
            .error((error: any) => {
                console.error('Channel subscription error:', error);
            })
    };

    const closeConcurrentSessionDialog = () => {
        showConcurrentSessionDialog.value = false;
        sessionData.value = null;
    };

    const cleanup = () => {
        if (echoChannel && currentSessionId.value) {
            window.Echo?.leave(`session.${currentSessionId.value}`);
        }
    };

    onUnmounted(() => {
        cleanup();
    });

    return {
        isDisconnected,
        showConcurrentSessionDialog,
        sessionData,
        isFetchingSessionData,
        setupSessionListener,
        closeConcurrentSessionDialog,
    };
}
