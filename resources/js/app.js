// Show a full-screen loading overlay while AI analysis runs, and auto-submit
// the photo form as soon as a picture is chosen or captured.

// Don't let the browser restore the previous scroll position on reload/back —
// pages should open at the top.
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

function showLoading(message) {
    const overlay = document.getElementById('loading-overlay');
    const text = document.getElementById('loading-text');
    if (!overlay) return;
    if (text && message) text.textContent = message;
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
}

document.addEventListener('DOMContentLoaded', () => {
    // Reveal the overlay whenever an AI form is submitted.
    document.querySelectorAll('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', () => showLoading(form.dataset.loading));
    });

    // Auto-submit the photo form once a file is selected.
    document.querySelectorAll('input[data-autosubmit]').forEach((input) => {
        input.addEventListener('change', () => {
            if (input.files && input.files.length > 0) {
                input.form.requestSubmit();
            }
        });
    });

    // Home links should always land at the very top.
    document.querySelectorAll('a[data-home]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = new URL(link.href, window.location.origin);
            if (target.pathname === window.location.pathname && target.search === window.location.search) {
                event.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });

    // "+" button scrolls to the Add food section without leaving a #hash in the URL.
    document.querySelectorAll('a[data-add-food]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const section = document.getElementById('add-food');
            if (section) {
                event.preventDefault();
                section.scrollIntoView({ behavior: 'smooth' });
            } else {
                // On another page — remember to scroll once the dashboard loads.
                sessionStorage.setItem('scrollToAddFood', '1');
            }
        });
    });

    // On load: jump to Add food once (after tapping + from another page), else the top.
    if (sessionStorage.getItem('scrollToAddFood')) {
        sessionStorage.removeItem('scrollToAddFood');
        document.getElementById('add-food')?.scrollIntoView();
    } else {
        window.scrollTo(0, 0);
    }

    initProfileSuggestion();
});

// Live daily-calorie suggestion on the profile form (Mifflin-St Jeor + activity).
function initProfileSuggestion() {
    const form = document.getElementById('profile-form');
    if (!form) return;

    const el = (id) => document.getElementById(id);
    const factors = { sedentary: 1.2, light: 1.375, moderate: 1.55, active: 1.725, athlete: 1.9 };

    const suggest = () => {
        const gender = el('gender')?.value;
        const age = 30; // representative default — age is no longer collected
        const height = parseFloat(el('height_cm')?.value);
        const weight = parseFloat(el('weight_kg')?.value);
        const factor = factors[el('activity_level')?.value] || factors.moderate;

        let goal;
        if (height > 0 && weight > 0) {
            const genderConstant = gender === 'male' ? 5 : gender === 'female' ? -161 : -78;
            const bmr = 10 * weight + 6.25 * height - 5 * age + genderConstant;
            goal = Math.round((bmr * factor) / 10) * 10;
        } else {
            goal = gender === 'male' ? 2500 : gender === 'female' ? 2000 : 2200;
        }
        return Math.max(1200, Math.min(5000, goal));
    };

    const suggestionEl = el('goal-suggestion');
    const goalInput = el('daily_goal');
    // Track the goal automatically until the user edits it by hand.
    let goalTouched = Boolean(goalInput && goalInput.value !== '');

    const render = () => {
        const goal = suggest();
        if (suggestionEl) suggestionEl.textContent = goal.toLocaleString();
        if (goalInput && !goalTouched) goalInput.value = goal;
    };

    ['gender', 'height_cm', 'weight_kg', 'activity_level'].forEach((id) => {
        el(id)?.addEventListener('input', render);
        el(id)?.addEventListener('change', render);
    });
    goalInput?.addEventListener('input', () => {
        goalTouched = true;
    });
    el('use-suggestion')?.addEventListener('click', () => {
        goalTouched = false;
        render();
    });

    render();
}
