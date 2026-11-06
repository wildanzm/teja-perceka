import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
window.flatpickr = flatpickr;
window.flatpickrIndonesian = Indonesian;

import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';
window.flatpickrMonthSelect = monthSelectPlugin;

import Chart from 'chart.js/auto';
window.Chart = Chart;

import Swal from 'sweetalert2';
window.Swal = Swal;

// ─── id-ID currency formatting (dot every 3 digits) for nominal inputs ───
// Usage: <input type="text" inputmode="numeric" data-rupiah wire:model="...">
// The server must parse via App\Support\Rupiah::parse() before validating/saving.
function rupiahDigits(value) {
    return String(value ?? '').replace(/\D/g, '').slice(0, 15);
}

function formatRupiahValue(digits) {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatRupiahInput(el, notifyLivewire) {
    const digits = rupiahDigits(el.value);
    const caretDigits = rupiahDigits(el.value.slice(0, el.selectionStart ?? el.value.length)).length;
    const formatted = formatRupiahValue(digits);

    if (el.value === formatted) {
        return;
    }

    el.value = formatted;

    // Keep the caret following the digit instead of jumping to the end.
    if (el.type === 'text') {
        let seen = 0;
        let pos = formatted.length;
        for (let i = 0; i < formatted.length; i++) {
            if (/\d/.test(formatted[i])) {
                seen++;
            }
            if (seen >= caretDigits) {
                pos = i + 1;
                break;
            }
        }
        try {
            el.setSelectionRange(pos, pos);
        } catch {
            // Ignore: some input types reject setSelectionRange.
        }
    }

    if (notifyLivewire) {
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function formatRupiahScope(scope) {
    scope.querySelectorAll('input[data-rupiah]').forEach((el) => formatRupiahInput(el, false));
}

document.addEventListener('input', (event) => {
    const el = event.target;
    if (el instanceof HTMLInputElement && el.hasAttribute('data-rupiah')) {
        formatRupiahInput(el, true);
    }
});

document.addEventListener('DOMContentLoaded', () => formatRupiahScope(document));
document.addEventListener('livewire:navigated', () => formatRupiahScope(document));

function hookRupiahMorph(attempts = 100) {
    if (window.Livewire?.hook) {
        window.Livewire.hook('morphed', ({ el }) => {
            if (el instanceof HTMLElement) {
                formatRupiahScope(el);
            }
        });
    } else if (attempts > 0) {
        setTimeout(() => hookRupiahMorph(attempts - 1), 100);
    }
}
hookRupiahMorph();
