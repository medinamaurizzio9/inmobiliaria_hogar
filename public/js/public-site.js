document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-faq-button]').forEach(button => {
        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            const panel = document.getElementById(button.getAttribute('aria-controls'));
            button.setAttribute('aria-expanded', String(!expanded));
            if (panel) panel.hidden = expanded;
        });
    });
});
