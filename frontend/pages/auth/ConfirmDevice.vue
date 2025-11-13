<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertTriangle, Monitor, Smartphone } from 'lucide-vue-next';
import { ref } from 'vue';
import SessionMonitor from '@/components/SessionMonitor.vue';
import { useI18n } from 'vue-i18n';

interface Device {
    id: string;
    ip_address: string;
    user_agent: string;
    last_activity: string;
}

interface Props {
    currentDevice: {
        ip_address: string;
        user_agent: string;
    };
    otherDevices: Device[];
    newLoginAt: string;
}

defineProps<Props>();

const { t } = useI18n();
const submitting = ref(false);

const handleChoice = (choice: 'keep_current' | 'keep_other') => {
    if (submitting.value) return;

    submitting.value = true;

    router.post('/confirm-device',
        { choice },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Redirect will be handled by the controller
            },
            onError: (errors) => {
                console.error('Confirmation error:', errors);
            },
            onFinish: () => {
                submitting.value = false;
            },
        }
    );
};
</script>

<template>
    <Head :title="t('auth.concurrentSession.title')" />
    <SessionMonitor />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 dark:bg-gray-900 sm:px-6 lg:px-8">
        <Card class="w-full max-w-2xl">
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/20">
                        <AlertTriangle class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                        <CardTitle class="text-2xl">{{ t('auth.concurrentSession.title') }}</CardTitle>
                        <CardDescription>
                            {{ t('auth.concurrentSession.description') }}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">

                <!-- Current Device -->
                <div class="space-y-3">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('auth.concurrentSession.thisDevice') }}
                    </h3>
                    <Card class="border-2 border-primary">
                        <CardContent class="flex items-start gap-4 p-4">
                            <Monitor class="mt-1 h-8 w-8 text-primary" />
                            <div class="flex-1 space-y-1">
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ currentDevice.user_agent }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    IP: {{ currentDevice.ip_address }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('common.today') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Other Devices -->
                <div
                    v-if="otherDevices.length > 0"
                    class="space-y-3"
                >
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('auth.concurrentSession.otherDevices') }}
                    </h3>
                    <div class="space-y-2">
                        <Card
                            v-for="device in otherDevices"
                            :key="device.id"
                        >
                            <CardContent class="flex items-start gap-4 p-4">
                                <Smartphone class="mt-1 h-8 w-8 text-gray-400" />
                                <div class="flex-1 space-y-1">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ device.user_agent }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        IP: {{ device.ip_address }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('auth.concurrentSession.lastActive') }}: {{ device.last_activity }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col gap-3 pt-4 sm:flex-row">
                    <Button
                        class="flex-1"
                        size="lg"
                        :disabled="submitting"
                        @click="handleChoice('keep_current')"
                    >
                        <Monitor class="mr-2 h-5 w-5" />
                        {{ t('auth.concurrentSession.stayOnThisDevice') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1"
                        size="lg"
                        :disabled="submitting"
                        @click="handleChoice('keep_other')"
                    >
                        <Smartphone class="mr-2 h-5 w-5" />
                        {{ t('common.cancel') }}
                    </Button>
                </div>

                <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ t('auth.concurrentSession.warning') }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
