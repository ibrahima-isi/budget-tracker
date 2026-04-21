import { createI18n } from 'vue-i18n';
import fr from './fr.js';
import en from './en.js';
import es from './es.js';

export const i18n = createI18n({
    legacy:          false,
    globalInjection: true,
    locale:          'fr',
    fallbackLocale:  'fr',
    messages:        { fr, en, es },
});
