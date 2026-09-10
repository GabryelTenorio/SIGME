const body = document.body;
const sidebarButton = document.querySelector('[data-sidebar-open]');
const sidebarClosers = document.querySelectorAll('[data-sidebar-close]');
const sidebar = document.querySelector('#app-sidebar');
const mobileViewport = window.matchMedia('(max-width: 820px)');

function syncSidebarAccess() {
    if (sidebar) sidebar.inert = mobileViewport.matches && !body.classList.contains('sidebar-open');
}

function setSidebar(open) {
    body.classList.toggle('sidebar-open', open);
    sidebarButton?.setAttribute('aria-expanded', String(open));
    syncSidebarAccess();
    if (mobileViewport.matches) {
        if (open) sidebar?.querySelector('a')?.focus();
        else sidebarButton?.focus();
    }
}

sidebarButton?.addEventListener('click', () => setSidebar(!body.classList.contains('sidebar-open')));
sidebarClosers.forEach((element) => element.addEventListener('click', () => setSidebar(false)));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebar(false);
    }
    if (event.key === 'Tab' && mobileViewport.matches && body.classList.contains('sidebar-open')) {
        const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled])')];
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
});
mobileViewport.addEventListener('change', () => setSidebar(false));
syncSidebarAccess();
document.querySelectorAll('.sidebar-nav__item.is-active').forEach(link => link.setAttribute('aria-current', 'page'));
