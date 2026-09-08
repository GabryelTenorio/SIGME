const body = document.body;
const sidebarButton = document.querySelector('[data-sidebar-open]');
const sidebarClosers = document.querySelectorAll('[data-sidebar-close]');

function setSidebar(open) {
    body.classList.toggle('sidebar-open', open);
    sidebarButton?.setAttribute('aria-expanded', String(open));
}

sidebarButton?.addEventListener('click', () => setSidebar(!body.classList.contains('sidebar-open')));
sidebarClosers.forEach((element) => element.addEventListener('click', () => setSidebar(false)));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebar(false);
    }
});
