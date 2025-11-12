<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-vue-next';

const { t } = useI18n();
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
        <Alert variant="destructive" class="mx-4 max-w-lg border-2 shadow-2xl">
            <AlertCircle class="h-5 w-5" />
            <AlertTitle class="text-lg font-semibold">
                {{ t('auth.sessionDisconnected.title') }}
            </AlertTitle>
            <AlertDescription class="mt-2 space-y-3">
                <p>{{ t('auth.sessionDisconnected.defaultMessage') }}</p>
                <p class="text-sm">
                    {{ t('auth.sessionDisconnected.redirecting') }}
                </p>
            </AlertDescription>
        </Alert>
    </div>
</template>
