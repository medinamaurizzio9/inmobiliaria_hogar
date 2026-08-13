document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-portal-header]');
    const menu = document.getElementById('portal-nav');
    const menuButton = document.querySelector('[data-portal-menu]');
    const dialog = document.querySelector('[data-login-dialog]');
    const setHeader = () => header?.classList.toggle('scrolled', window.scrollY > 24);
    setHeader();
    window.addEventListener('scroll', setHeader, { passive: true });
    menuButton?.addEventListener('click', () => {
        const open = menu?.classList.toggle('open');
        menuButton.setAttribute('aria-expanded', String(Boolean(open)));
    });
    menu?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
        menu.classList.remove('open');
        menuButton?.setAttribute('aria-expanded', 'false');
    }));
    document.querySelector('[data-login-open]')?.addEventListener('click', () => dialog?.showModal());
    document.querySelector('[data-login-close]')?.addEventListener('click', () => dialog?.close());
    dialog?.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
});
