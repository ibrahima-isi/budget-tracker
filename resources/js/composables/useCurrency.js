import { computed, ref } from 'vue';
import { usePage }       from '@inertiajs/vue3';

const USER_CURRENCY_KEY = 'userCurrency';

// Module-level reactive ref so all composable instances share the same state.
const _selected = ref(localStorage.getItem(USER_CURRENCY_KEY) ?? null);

export function useCurrency() {
    const page = usePage();

    /** Active currencies list from the Inertia shared prop. */
    const currencies = computed(() => page.props.currencies ?? []);

    /** App-wide default code from admin settings. */
    const defaultCode = computed(() => page.props.appSettings?.default_currency ?? 'XOF');

    /**
     * Resolved currency code:
     *   1. User's localStorage preference (if still active)
     *   2. App default from Settings
     */
    const currentCode = computed(() => {
        const stored = _selected.value;
        if (stored && currencies.value.some(c => c.code === stored)) return stored;
        return defaultCode.value;
    });

    /** Full currency object { code, name, symbol } for the current selection. */
    const currentCurrency = computed(() =>
        currencies.value.find(c => c.code === currentCode.value)
        ?? { code: currentCode.value, name: currentCode.value, symbol: currentCode.value }
    );

    /** Persist the user's currency choice. */
    function setCurrency(code) {
        if (!currencies.value.some(c => c.code === code)) return;
        localStorage.setItem(USER_CURRENCY_KEY, code);
        _selected.value = code;
    }

    /**
     * Format an amount using the current currency and the fr-FR locale.
     * Uses Intl.NumberFormat which is available in all modern browsers.
     */
    function format(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style:                'currency',
            currency:             currentCode.value,
            maximumFractionDigits: 0,
        }).format(Number(amount) || 0);
    }

    return { currencies, currentCode, currentCurrency, setCurrency, format };
}
