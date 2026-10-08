import { ref } from 'vue';

const STORAGE_KEY = 'skdiit-theme';

// The <head> script in app.blade.php applies the saved theme before first paint
const isDark = ref(document.documentElement.classList.contains('dark'));

function toggleTheme() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    try {
        localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light');
    } catch {
        /* storage unavailable: theme lasts for this visit only */
    }
}

export function useTheme() {
    return { isDark, toggleTheme };
}
