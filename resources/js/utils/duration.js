/**
 * Duration formatting shared by the analytics KPIs and the admin dashboard.
 *
 * @param {number|null|undefined} minutes
 * @param {{minutes?: string, hours?: string, days?: string}} translations
 */
export function formatMinutes(minutes, translations = {}) {
    const rounded = Math.round(minutes || 0);
    if (rounded < 60) return `${rounded} ${translations.minutes || 'min'}`;
    const hours = rounded / 60;
    if (hours < 48) {
        const whole = Math.floor(hours);
        const rem = Math.round((hours - whole) * 60);
        return rem
            ? `${whole}${translations.hours || 'h'} ${rem}${translations.minutes || 'min'}`
            : `${whole}${translations.hours || 'h'}`;
    }
    const days = rounded / 1440;
    const wholeDays = Math.floor(days);
    const remHours = Math.round((days - wholeDays) * 24);
    return remHours
        ? `${wholeDays}${translations.days || 'd'} ${remHours}${translations.hours || 'h'}`
        : `${wholeDays}${translations.days || 'd'}`;
}
