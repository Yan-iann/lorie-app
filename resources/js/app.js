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

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (!overlay) return;
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
}

// Page-to-page navigation feedback: a progress bar straight away, then a
// skeleton in place of the content if the next page is slow to arrive.
const SKELETON_DELAY_MS = 250;
let skeletonTimer = null;

function startPageLoading() {
    document.getElementById('nav-progress')?.classList.add('is-active');

    clearTimeout(skeletonTimer);
    skeletonTimer = setTimeout(() => {
        const content = document.getElementById('page-content');
        const skeleton = document.getElementById('page-skeleton');
        if (!content || !skeleton) return;
        content.classList.add('hidden');
        skeleton.classList.remove('hidden');
        window.scrollTo(0, 0);
    }, SKELETON_DELAY_MS);
}

function stopPageLoading() {
    clearTimeout(skeletonTimer);
    document.getElementById('nav-progress')?.classList.remove('is-active');
    document.getElementById('page-skeleton')?.classList.add('hidden');
    document.getElementById('page-content')?.classList.remove('hidden');
}

// Links to another page of the app. Listening on the document means a link's
// own handler has already run, so anything it cancelled is skipped here.
document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const link = event.target.closest?.('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;

    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    // Same page, just a #hash — nothing loads.
    if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) return;

    startPageLoading();
});

// Form posts (save profile, sign in, remove entry…). AI forms have their own overlay.
document.addEventListener('submit', (event) => {
    if (event.defaultPrevented || event.target.matches('form[data-loading], form[data-async]')) return;

    startPageLoading();
});

// Coming back via the browser's Back button can restore the page from the
// back/forward cache with a loading state still showing — clear it.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    hideLoading();
    stopPageLoading();
});

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

    // Pages open at the top.
    window.scrollTo(0, 0);

    initAddFood();
    initToasts();
    initConfirmDialog();
    initProfileSuggestion();
});

// Forms marked data-confirm (remove an entry, undo a drink…) ask first, in the
// app's own dialog. Listening in the capture phase means this runs before the
// page-loading and background-save handlers, which only see the form once the
// user has agreed.
function initConfirmDialog() {
    const dialog = document.getElementById('confirm-dialog');
    if (!dialog) return;

    const supported = typeof dialog.showModal === 'function';
    const accept = dialog.querySelector('[data-confirm-accept]');
    let pending = null;

    const close = () => {
        dialog.classList.add('is-closing');
        setTimeout(() => {
            dialog.classList.remove('is-closing');
            dialog.close();
        }, 160);
    };

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;

            // Second time round: the user said yes, let it through.
            if (form.dataset.confirmed) {
                delete form.dataset.confirmed;
                return;
            }

            const { confirmTitle = 'Are you sure?', confirmText = '', confirmAction = 'Remove', confirmIcon = 'trash' } = form.dataset;

            // Very old browsers without <dialog>: fall back to the built-in prompt.
            if (!supported) {
                if (!window.confirm(`${confirmTitle} ${confirmText}`.trim())) event.preventDefault();
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            pending = { form, submitter: event.submitter };
            dialog.querySelector('#confirm-title').textContent = confirmTitle;
            dialog.querySelector('#confirm-text').textContent = confirmText;
            accept.textContent = confirmAction;
            dialog.querySelectorAll('[data-confirm-icon]').forEach((icon) => {
                icon.classList.toggle('hidden', icon.dataset.confirmIcon !== confirmIcon);
            });

            dialog.showModal();
        },
        true,
    );

    accept.addEventListener('click', () => {
        if (!pending) return;
        const { form, submitter } = pending;
        pending = null;
        close();

        if (!form.isConnected) return;
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter?.form === form ? submitter : undefined);
    });

    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', close);
    // Escape, and a tap on the dimmed area outside the box, both cancel.
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    dialog.addEventListener('close', () => {
        pending = null;
    });
}

// Toast notifications dismiss themselves after a moment, or straight away on tap.
function armToast(toast) {
    const dismiss = () => {
        if (toast.classList.contains('is-leaving')) return;
        toast.classList.add('is-leaving');
        // Removed once the exit transition has had time to finish.
        setTimeout(() => toast.remove(), 300);
    };

    toast.addEventListener('click', dismiss);
    setTimeout(dismiss, Number(toast.dataset.timeout) || 3500);
}

function initToasts() {
    document.querySelectorAll('[data-toast]').forEach(armToast);
}

// Show a toast the server sent back as HTML, replacing any still on screen.
function showToast(html) {
    const stack = document.getElementById('toasts');
    if (!stack || !html) return;

    stack.querySelectorAll('[data-toast]').forEach((toast) => toast.remove());
    stack.insertAdjacentHTML('beforeend', html);
    stack.querySelectorAll('[data-toast]').forEach(armToast);
}

// Water (and anything else marked data-async) saves in the background: no page
// reload. The server sends back the redrawn water parts of the dashboard (the
// gauge and the controls); each replaces the one on the page with the same
// data-water-part name, and the glass glides from its old level to the new one.
function swapWaterParts(html) {
    if (!html) return;

    const template = document.createElement('template');
    template.innerHTML = html.trim();

    template.content.querySelectorAll('[data-water-part]').forEach((fresh) => {
        const current = document.querySelector(`[data-water-part="${fresh.dataset.waterPart}"]`);
        if (!current) return;

        // It's a redraw, not a new arrival: skip the entrance animation.
        fresh.classList.remove('animate-rise');

        // Keep the list of drinks open if it was.
        const details = fresh.querySelector('details');
        if (details && current.querySelector('details')?.open) details.open = true;

        // Start the new glass at the old level, then let it settle to the new one.
        const glass = fresh.querySelector('.water-glass');
        const target = fresh.dataset.level;
        glass?.style.setProperty('--level', current.dataset.level || '0');

        current.replaceWith(fresh);

        if (glass) {
            void glass.getBoundingClientRect(); // commit the starting level
            glass.style.setProperty('--level', target);
        }
    });
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form[data-async]') || event.defaultPrevented) return;

    event.preventDefault();

    const data = new FormData(form);
    // The tapped button says which container it was.
    if (event.submitter?.name) data.set(event.submitter.name, event.submitter.value);

    const buttons = form.querySelectorAll('button');
    buttons.forEach((button) => (button.disabled = true));

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: data,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const result = await response.json();
        swapWaterParts(result.parts);
        // Lorie's bubble mentions water too; keep it in step when the gauge is on this page.
        const coach = document.querySelector('[data-coach-message]');
        if (coach && result.coach && document.querySelector('[data-water-part]')) coach.textContent = result.coach;
        showToast(result.toast);
        document.dispatchEvent(new CustomEvent('add-food:close'));
    } catch {
        // Couldn't save in the background (offline, expired session…): fall
        // back to a normal form post, which reports the problem properly.
        if (event.submitter?.name) {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = event.submitter.name;
            field.value = event.submitter.value;
            form.append(field);
        }
        form.submit();
    } finally {
        buttons.forEach((button) => (button.disabled = false));
    }
});

// "Add food" popup behind the + button: a short menu (type it / take a photo),
// then the text form if the user chose to type.
function initAddFood() {
    const panel = document.getElementById('add-food-panel');
    const backdrop = document.getElementById('add-food-backdrop');
    if (!panel || !backdrop) return;

    const toggles = document.querySelectorAll('[data-add-food-toggle]');
    const views = panel.querySelectorAll('[data-add-food-view]');
    const isOpen = () => panel.dataset.open === 'true';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let heightTimer = null;

    const show = (name) => {
        const from = panel.offsetHeight;
        views.forEach((view) => view.classList.toggle('hidden', view.dataset.addFoodView !== name));

        // The two views differ in height: glide between them instead of snapping.
        if (isOpen() && !reducedMotion.matches) {
            panel.style.height = '';
            const to = panel.offsetHeight;
            panel.style.height = `${from}px`;
            void panel.offsetHeight; // commit the starting height before animating
            panel.style.height = `${to}px`;
            clearTimeout(heightTimer);
            heightTimer = setTimeout(() => (panel.style.height = ''), 350);
        }

        if (isOpen()) focusFirst();
    };

    const focusFirst = () => {
        // The text box when typing; otherwise the first thing that can be pressed.
        const view = [...views].find((candidate) => !candidate.classList.contains('hidden'));
        const first = view?.querySelector('input[name="description"]') ?? view?.querySelector('button, label[for]');
        first?.focus({ preventScroll: true });
    };

    const setOpen = (open, view = 'menu') => {
        if (open && !isOpen()) show(view);
        panel.dataset.open = String(open);
        backdrop.dataset.open = String(open);
        toggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close' : 'Add food');
        });
        if (open) focusFirst();
    };

    toggles.forEach((toggle) => toggle.addEventListener('click', () => setOpen(!isOpen())));
    // Shortcuts straight to one view, e.g. tapping the water glass. Listened for
    // on the document because the glass is replaced whenever water is logged.
    document.addEventListener('click', (event) => {
        const opener = event.target.closest?.('[data-add-food-open]');
        if (opener) setOpen(true, opener.dataset.addFoodOpen);
    });
    panel.querySelectorAll('[data-add-food-show]').forEach((button) => {
        button.addEventListener('click', () => show(button.dataset.addFoodShow));
    });
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('add-food:close', () => isOpen() && setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) setOpen(false);
    });

    // Reopen on the text form when the server sent the description back with an error.
    if (panel.dataset.initialView) setOpen(true, panel.dataset.initialView);
}

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

    // Water: the same sum the server does (WaterCalculator), using its numbers
    // passed down on the form so the two can't drift apart.
    const water = form.dataset;
    const waterExtra = JSON.parse(water.waterActivityExtra || '{}');
    const waterDefaults = JSON.parse(water.waterDefaults || '{}');

    const suggestWaterMl = () => {
        const weight = parseFloat(el('weight_kg')?.value);
        if (!(weight > 0)) return waterDefaults[el('gender')?.value] ?? waterDefaults.other;

        const extra = waterExtra[el('activity_level')?.value] ?? waterExtra.moderate;
        const goal = Math.round((weight * Number(water.waterMlPerKg) + extra) / 100) * 100;
        return Math.max(Number(water.waterMin), Math.min(Number(water.waterMax), goal));
    };

    const render = () => {
        const goal = suggest();
        if (suggestionEl) suggestionEl.textContent = goal.toLocaleString();
        if (goalInput && !goalTouched) goalInput.value = goal;

        const litres = suggestWaterMl() / 1000;
        if (el('water-suggestion')) el('water-suggestion').textContent = `${litres} L`;
        if (el('daily_water_goal_l')) el('daily_water_goal_l').placeholder = litres;
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
