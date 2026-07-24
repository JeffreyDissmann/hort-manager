import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * The current user's accounting access flags (shared from HandleInertiaRequests).
 * Use `canWrite` to hide write controls from read-only accounting users; the server
 * enforces the same via the `accounting:write` middleware.
 */
export function useAccountingAccess() {
    const user = computed(() => usePage().props.auth?.user);

    return {
        canRead: computed(() => user.value?.can_read_accounting ?? false),
        canWrite: computed(() => user.value?.can_write_accounting ?? false),
    };
}
