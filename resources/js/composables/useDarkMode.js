import { ref } from 'vue';

const STORAGE_KEY = 'darkMode';

function getInitial() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored !== null) return stored === 'true';
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

const isDark = ref(false);

function applyDark(value) {
    isDark.value = value;
    document.documentElement.classList.toggle('dark', value);
    localStorage.setItem(STORAGE_KEY, String(value));
}

// Initialize once when the module loads (client side only)
if (typeof window !== 'undefined') {
    applyDark(getInitial());
}

export function useDarkMode() {
    function toggleDark() {
        applyDark(!isDark.value);
    }

    return { isDark, toggleDark };
}
