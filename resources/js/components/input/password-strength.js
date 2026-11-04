/*
|--------------------------------------------------------------------------
| Password Strength Component
|--------------------------------------------------------------------------
| - Requirements validated in real time (length, uppercase/lowercase, symbol/number)
| - Visual strength indicator with 3 segments (Weak / Medium / Strong)
| - Accessibility: progressive aria-valuenow on the bar
*/

export default function passwordStrengthComponent() {
    return {
        password: '',

        get lengthOk() {
            return this.password.length >= 8;
        },

        get caseOk() {
            return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password);
        },

        get symbolNumberOk() {
            return /\d/.test(this.password) || /[^a-zA-Z0-9]/.test(this.password);
        },

        get score() {
            return [this.lengthOk, this.caseOk, this.symbolNumberOk].filter(Boolean).length;
        },

        get level() {
            if (this.score <= 1) {
                return 'weak';
            }

            if (this.score === 2) {
                return 'medium';
            }

            return 'strong';
        },

        get barColor() {
            if (this.score <= 1) {
                return 'bg-danger';
            }

            if (this.score === 2) {
                return 'bg-warning';
            }

            return 'bg-success';
        },

        get levelLabel() {
            const translations = window.SGM_UI_I18N || {};
            const labels = {
                weak: translations.passwordWeak || 'Fraca',
                medium: translations.passwordMedium || 'Média',
                strong: translations.passwordStrong || 'Forte',
            };

            return labels[this.level];
        },

        get levelClass() {
            if (this.level === 'weak') {
                return 'text-danger';
            }

            if (this.level === 'medium') {
                return 'text-warning';
            }

            return 'text-success';
        },
    };
}
