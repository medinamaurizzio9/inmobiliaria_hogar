document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.shell');
    const sidebar = document.getElementById('crm-sidebar');
    const mobile = () => window.matchMedia('(max-width: 760px)').matches;
    const tablet = () => window.matchMedia('(max-width: 1100px)').matches;

    if (shell && sidebar) {
        const stored = localStorage.getItem('crm_sidebar_collapsed');
        const initiallyCollapsed = stored === 'true' || (stored === null && tablet() && !mobile());
        shell.classList.toggle('sidebar-collapsed', initiallyCollapsed);
        document.documentElement.classList.toggle('crm-sidebar-collapsed', initiallyCollapsed);

        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => {
            if (mobile()) {
                shell.classList.toggle('sidebar-open');
            } else {
                const collapsed = shell.classList.toggle('sidebar-collapsed');
                document.documentElement.classList.toggle('crm-sidebar-collapsed', collapsed);
                localStorage.setItem('crm_sidebar_collapsed', collapsed ? 'true' : 'false');
                document.querySelectorAll('[data-sidebar-toggle]').forEach((item) => item.setAttribute('aria-expanded', collapsed ? 'false' : 'true'));
            }
        }));
        document.querySelectorAll('[data-sidebar-close]').forEach((button) => button.addEventListener('click', () => shell.classList.remove('sidebar-open')));
    }

    document.querySelectorAll('[data-sidebar-accordion]').forEach((accordion) => {
        accordion.querySelectorAll('[data-menu-toggle]').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const group = toggle.closest('.sidebar-group');
                if (!group) return;
                const isOpen = group.classList.toggle('open');
                group.classList.toggle('active', isOpen || Boolean(group.querySelector('.sidebar-link.active')));
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    });

    const userTrigger = document.querySelector('[data-user-menu]');
    const userDropdown = document.querySelector('[data-user-dropdown]');
    userTrigger?.addEventListener('click', () => {
        const opening = userDropdown.hasAttribute('hidden');
        userDropdown.toggleAttribute('hidden');
        userTrigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    document.addEventListener('click', (event) => {
        if (userTrigger && userDropdown && !event.target.closest('.user-menu')) {
            userDropdown.setAttribute('hidden', '');
            userTrigger.setAttribute('aria-expanded', 'false');
        }
    });

    const search = document.querySelector('.crm-search input');
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && search) {
            event.preventDefault();
            search.focus();
        }
        if (event.key === 'Escape') shell?.classList.remove('sidebar-open');
    });
});
