/**
 * Portuguese replacements for the browser's own constraint messages.
 *
 * `required`, `type="email"` and `min` make the browser block the submit and
 * show its own bubble before the request ever reaches the server, so Laravel's
 * translations never get a chance at them. Worse, those bubbles follow the
 * *browser's* UI language rather than the page's, so the wording a visitor gets
 * depends on how their Chrome is configured. Setting the text ourselves makes
 * it Portuguese for everyone.
 */

type Field = HTMLInputElement | HTMLTextAreaElement;

function isInput(field: Field): field is HTMLInputElement {
    return field.tagName === 'INPUT';
}

function messageFor(field: Field): string {
    const validity = field.validity;

    if (validity.valueMissing) {
        return 'Preencha este campo.';
    }

    if (validity.typeMismatch) {
        return isInput(field) && field.type === 'email'
            ? 'Informe um e-mail válido.'
            : 'Informe um valor válido.';
    }

    if (validity.badInput) {
        return 'Informe um valor válido.';
    }

    if (validity.rangeUnderflow) {
        // The only lower bound in this application is the task deadline, whose
        // rule the server words the same way.
        return isInput(field) && field.type === 'datetime-local'
            ? 'O prazo não pode ser anterior à data e hora atuais.'
            : 'Informe um valor maior.';
    }

    if (validity.rangeOverflow) {
        return 'Informe um valor menor.';
    }

    if (validity.tooShort) {
        return `Use pelo menos ${field.minLength} caracteres.`;
    }

    if (validity.tooLong) {
        return `Use no máximo ${field.maxLength} caracteres.`;
    }

    if (validity.stepMismatch || validity.patternMismatch) {
        return 'Informe um valor válido.';
    }

    return 'Verifique este campo.';
}

/**
 * Handlers to spread onto an input or textarea.
 *
 * A custom message keeps a field invalid until it is cleared, so it is cleared
 * on every edit — and again when the browser re-reports the field, in case the
 * value was filled in a way that fires no input event, such as autofill.
 */
export const nativeValidationHandlers = {
    onInvalid(event: React.InvalidEvent<Field>) {
        const field = event.currentTarget;

        // Drop the previous message first, so what is read below is the state
        // of the value itself rather than the message left over from it.
        field.setCustomValidity('');

        if (!field.validity.valid) {
            field.setCustomValidity(messageFor(field));
        }
    },

    onInput(event: React.FormEvent<Field>) {
        event.currentTarget.setCustomValidity('');
    },

    onChange(event: React.ChangeEvent<Field>) {
        event.currentTarget.setCustomValidity('');
    },
};
