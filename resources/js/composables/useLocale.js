import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const USER_LANG_KEY = 'userLocale';
const SUPPORTED = ['fr', 'en', 'es'];

export function useLocale() {
    const { locale } = useI18n();
    const page       = usePage();

    // App-level default (from admin settings)
    const appLang = computed(() => page.props.appSettings?.language ?? 'fr');

    // User personal override (localStorage takes priority)
    function getUserLang() {
        const stored = localStorage.getItem(USER_LANG_KEY);
        return stored && SUPPORTED.includes(stored) ? stored : null;
    }

    // Sync i18n locale: user override > app setting
    function syncLocale() {
        locale.value = getUserLang() ?? appLang.value;
    }

    watch(appLang, syncLocale, { immediate: true });

    // Called from the language switcher in the nav
    function setLocale(lang) {
        if (!SUPPORTED.includes(lang)) return;
        localStorage.setItem(USER_LANG_KEY, lang);
        locale.value = lang;
    }

    // Locale-aware short month labels (index 0 = '', 1-12 = Jan-Dec)
    const moisCourts = computed(() => {
        const fmt = new Intl.DateTimeFormat(locale.value, { month: 'short' });
        return ['', ...Array.from({ length: 12 }, (_, i) =>
            fmt.format(new Date(2000, i, 1))
        )];
    });

    function formatDate(dateStr) {
        if (!dateStr) return '';
        // Append noon to avoid UTC-midnight → local-previous-day shift
        const d = new Date(dateStr.slice(0, 10) + 'T12:00:00');
        return d.toLocaleDateString(locale.value);
    }

    return { locale, moisCourts, formatDate, setLocale, supported: SUPPORTED };
}
