import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

/** users.meal / companion_meal value meaning "typed my own" (the text is in *_other). */
export const OTHER_MEAL = 'other';

// Meal preferences admin manages in Settings, shared by HandleInertiaRequests on every page
export function useMealOptions() {
    const page = usePage();
    const { t, tr } = useI18n();
    const mealOptions = computed(() => page.props.mealOptions ?? []);

    /** Display name for a code (+ the typed text under "Other"). */
    const mealName = (code, other) => {
        if (!code) return '';
        if (code === OTHER_MEAL) return other || t('meals.other');
        const found = mealOptions.value.find((m) => m.code === code);
        return found ? tr(found.name) : code;
    };

    return {
        mealOptions,
        mealName,
        /** Own meal of a user/registrant row. */
        mealOf: (person) => mealName(person?.meal, person?.meal_other),
        /** The companion's meal of a user/registrant row. */
        companionMealOf: (person) => mealName(person?.companion_meal, person?.companion_meal_other),
    };
}

/** Form fields for the meal and the companion, filled from a user/registrant row (or empty). */
export function mealFormFields(person = null) {
    return {
        meal: person?.meal ?? '',
        meal_other: person?.meal_other ?? '',
        companion: !!person?.companion_name,
        companion_name: person?.companion_name ?? '',
        companion_meal: person?.companion_meal ?? '',
        companion_meal_other: person?.companion_meal_other ?? '',
    };
}

/** What the server takes (MealOption::rules): typed text only with "Other", no companion fields without the tick. */
export function mealPayload(data) {
    const typed = (code, text) => (code === OTHER_MEAL ? text : null);
    return {
        meal: data.meal || null,
        meal_other: typed(data.meal, data.meal_other),
        companion: data.companion,
        companion_name: data.companion ? data.companion_name : null,
        companion_meal: data.companion ? data.companion_meal : null,
        companion_meal_other: data.companion ? typed(data.companion_meal, data.companion_meal_other) : null,
    };
}
