import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useFlash() {
    const page = usePage();

    return {
        success: computed(() => page.props.flash?.success ?? null),
        error:   computed(() => page.props.flash?.error   ?? null),
    };
}
