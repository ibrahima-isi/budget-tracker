import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

export function useLocale() {
    const { locale } = useI18n();
    const page       = usePage();

    const appLang = computed(() => page.props.appSettings?.language ?? 'fr');

    // Keep i18n locale in sync with the app setting (changes on navigation too)
    watch(appLang, (lang) => { locale.value = lang; }, { immediate: true });

    // Locale-aware short month labels (index 0 = '', 1-12 = Jan-Dec)
    const moisCourts = computed(() => {
        const fmt = new Intl.DateTimeFormat(locale.value, { month: 'short' });
        return ['', ...Array.from({ length: 12 }, (_, i) =>
            fmt.format(new Date(2000, i, 1))
        )];
    });

    function formatDate(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString(locale.value);
    }

    return { locale, moisCourts, formatDate };
}
