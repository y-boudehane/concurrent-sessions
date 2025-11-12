<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { AlertTriangle, Monitor, Smartphone, Loader2 } from 'lucide-vue-next';

interface Device {
    id: string;
    ip_address: string;
    user_agent: string;
    last_activity: string;
}

interface SessionData {
    currentDevice: {
        ip_address: string;
        user_agent: string;
    };
    otherDevices: Device[];
}

interface Props {
    open: boolean;
    sessionData?: SessionData | null;
}

interface Emits {
    (e: 'close'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useI18n();

const submitting = ref(false);
const error = ref<string | null>(null);

const handleChoice = async (choice: 'keep_current' | 'keep_other') => {
    if (submitting.value) return;

    submitting.value = true;

    router.post(
        '/confirm-device',
        { choice },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('close');
            },
            onError: (errors) => {
                console.error('Confirmation error:', errors);
                error.value = 'Failed to process your choice. Please try again.';
            },
            onFinish: () => {
                submitting.value = false;
            },
        }
    );
};
</script>

<template>
    <Dialog :open="open">
        <DialogContent class="sm:max-w-2xl" >
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/20">
                        <AlertTriangle class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                        <DialogTitle class="text-xl sm:text-2xl">{{ t('auth.concurrentSession.title') }}</DialogTitle>
                        <DialogDescription>
                            {{ t('auth.concurrentSession.description') }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Error State -->
            <div v-if="error" class="py-6">
                <Card class="border-destructive">
                    <CardContent class="p-4">
                        <p class="text-sm text-destructive">{{ error }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Session Data -->
            <div v-else-if="sessionData" class="flex flex-col gap-6 py-4">
                <!-- Current Device -->
                <div class="space-y-3">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('auth.concurrentSession.thisDevice') }}
                    </h3>
                    <Card class="border-2 border-primary">
                        <CardContent class="flex items-start gap-4 p-4">
                            <Monitor class="mt-1 h-8 w-8 text-primary shrink-0" />
                            <div class="flex-1 space-y-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100 break-words">
                                    {{ sessionData.currentDevice.user_agent }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    IP: {{ sessionData.currentDevice.ip_address }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('common.today') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Other Devices -->
                <div v-if="sessionData.otherDevices.length > 0" class="space-y-3">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('auth.concurrentSession.otherDevices') }}
                    </h3>
                    <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
                        <Card v-for="device in sessionData.otherDevices" :key="device.id">
                            <CardContent class="flex items-start gap-4 p-4">
                                <Smartphone class="mt-1 h-8 w-8 text-gray-400 shrink-0" />
                                <div class="flex-1 space-y-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 break-words">
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
                        <Loader2 v-if="submitting" class="mr-2 h-5 w-5 animate-spin" />
                        <Monitor v-else class="mr-2 h-5 w-5" />
                        {{ t('auth.concurrentSession.disconnectOther') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1"
                        size="lg"
                        :disabled="submitting"
                        @click="handleChoice('keep_other')"
                    >
                        <Smartphone class="mr-2 h-5 w-5" />
                        {{ t('auth.concurrentSession.stayOnOther') }}
                    </Button>
                </div>

                <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ t('auth.concurrentSession.warning') }}
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
