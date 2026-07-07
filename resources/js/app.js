// Show a full-screen loading overlay while AI analysis runs, and auto-submit
// the photo form as soon as a picture is chosen or captured.

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
});
