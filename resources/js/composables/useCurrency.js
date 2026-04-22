import { computed }      from 'vue';
import { usePage, router } from '@inertiajs/vue3';

export function useCurrency() {
    const page = usePage();

    /** Active currencies list from the Inertia shared prop. */
    const currencies = computed(() => page.props.currencies ?? []);

    /**
     * The currency the current user has selected, driven by the server-side
     * session value shared as `currentCurrency` by HandleInertiaRequests.
     * Falls back to the app default, then to 'XOF'.
     */
    const currentCode = computed(() =>
        page.props.currentCurrency
        ?? page.props.appSettings?.default_currency
        ?? 'XOF'
    );

    /** Full currency object { code, name, symbol } for the current selection. */
    const currentCurrency = computed(() =>
        currencies.value.find(c => c.code === currentCode.value)
        ?? { code: currentCode.value, name: currentCode.value, symbol: currentCode.value }
    );

    /**
     * Persist the user's currency choice in the server session.
     * The POST to /user/currency stores it and redirects back, which triggers
     * an Inertia page reload with the updated `currentCurrency` shared prop and
     * freshly filtered data from the backend.
     */
    function setCurrency(code) {
        if (!currencies.value.some(c => c.code === code)) return;
        router.post(route('user.currency'), { currency: code }, { preserveScroll: true });
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
