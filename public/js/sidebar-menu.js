document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.shell');
    const sidebar = document.getElementById('crm-sidebar');
    const mobile = () => window.matchMedia('(max-width: 760px)').matches;
    const tablet = () => window.matchMedia('(max-width: 1100px)').matches;
    let sidebarInvoker = null;

    if (shell && sidebar) {
        const stored = localStorage.getItem('crm_sidebar_collapsed');
        const initiallyCollapsed = stored === 'true' || (stored === null && tablet() && !mobile());
        shell.classList.toggle('sidebar-collapsed', initiallyCollapsed);
        document.documentElement.classList.toggle('crm-sidebar-collapsed', initiallyCollapsed);
        if (!mobile()) {
            document.querySelectorAll('[data-sidebar-toggle]').forEach((item) => item.setAttribute('aria-expanded', initiallyCollapsed ? 'false' : 'true'));
        }

        const focusable = () => [...sidebar.querySelectorAll('a[href], button:not([disabled]), select:not([disabled]), input:not([disabled])')]
            .filter((item) => !item.hidden && item.offsetParent !== null);
        const syncMobileSidebar = (open, returnFocus = true) => {
            shell.classList.toggle('sidebar-open', open);
            document.body.classList.toggle('sidebar-drawer-open', open);
            sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.querySelectorAll('[data-sidebar-toggle]').forEach((item) => item.setAttribute('aria-expanded', open ? 'true' : 'false'));

            if (open) {
                window.requestAnimationFrame(() => (focusable()[0] || sidebar).focus());
            } else if (returnFocus && sidebarInvoker) {
                sidebarInvoker.focus();
            }
        };
        const closeMobileSidebar = (returnFocus = true) => {
            if (mobile()) syncMobileSidebar(false, returnFocus);
        };

        if (mobile()) sidebar.setAttribute('aria-hidden', 'true');

        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => {
            if (mobile()) {
                const opening = !shell.classList.contains('sidebar-open');
                if (opening) sidebarInvoker = button;
                syncMobileSidebar(opening);
            } else {
                const collapsed = shell.classList.toggle('sidebar-collapsed');
                document.documentElement.classList.toggle('crm-sidebar-collapsed', collapsed);
                localStorage.setItem('crm_sidebar_collapsed', collapsed ? 'true' : 'false');
                document.querySelectorAll('[data-sidebar-toggle]').forEach((item) => item.setAttribute('aria-expanded', collapsed ? 'false' : 'true'));
            }
        }));
        document.querySelectorAll('[data-sidebar-close]').forEach((button) => button.addEventListener('click', () => closeMobileSidebar()));
        sidebar.querySelectorAll('a[href]').forEach((link) => link.addEventListener('click', () => closeMobileSidebar(false)));

        window.addEventListener('resize', () => {
            if (mobile()) {
                if (!shell.classList.contains('sidebar-open')) sidebar.setAttribute('aria-hidden', 'true');
            } else {
                shell.classList.remove('sidebar-open');
                document.body.classList.remove('sidebar-drawer-open');
                sidebar.removeAttribute('aria-hidden');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab' || !mobile() || !shell.classList.contains('sidebar-open')) return;
            const items = focusable();
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
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
        if (event.key === 'Escape' && shell?.classList.contains('sidebar-open')) {
            shell.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-drawer-open');
            sidebar?.setAttribute('aria-hidden', 'true');
            document.querySelectorAll('[data-sidebar-toggle]').forEach((item) => item.setAttribute('aria-expanded', 'false'));
            sidebarInvoker?.focus();
        }
    });
});
