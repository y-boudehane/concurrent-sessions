<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useSessionMonitor } from '@/composables/useSessionMonitor';
import SessionDisconnectedAlert from '@/components/SessionDisconnectedAlert.vue';
import ConcurrentSessionDialog from '@/components/ConcurrentSessionDialog.vue';

const page = usePage();
const {
    isDisconnected,
    showConcurrentSessionDialog,
    sessionData,
    setupSessionListener,
    closeConcurrentSessionDialog,
} = useSessionMonitor();

onMounted(() => {
    const user = page.props.auth?.user;
    const sessionId = page.props.sessionId as string;

    if (user && sessionId) {
        setupSessionListener(user.id, sessionId);
    }
});

watch(() => page.props.auth?.user, (newUser) => {
    const sessionId = page.props.sessionId as string;
    if (newUser && sessionId) {
        setupSessionListener(newUser.id, sessionId);
    }
});
</script>

<template>
    <!-- Session Disconnected Alert -->
    <SessionDisconnectedAlert
        v-if="isDisconnected"
    />

    <!-- Concurrent Session Dialog -->
    <ConcurrentSessionDialog
        :open="showConcurrentSessionDialog"
        :session-data="sessionData"
        @close="closeConcurrentSessionDialog"
    />
</template>
