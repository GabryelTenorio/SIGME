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

document.querySelectorAll('[data-auto-submit]').forEach((field) => {
    field.addEventListener('change', () => field.form?.requestSubmit());
});

document.querySelectorAll('[data-location-base][data-location-param]').forEach((field) => {
    field.addEventListener('change', () => {
        const target = new URL(field.dataset.locationBase, window.location.origin);
        target.searchParams.set(field.dataset.locationParam, field.value);
        window.location.assign(target);
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

const logoutForm = document.querySelector('[data-logout-form]');

if (logoutForm) {
    const loginUrl = logoutForm.dataset.logoutRedirect;
    const logoutChannel = 'BroadcastChannel' in window
        ? new BroadcastChannel('sigme-auth')
        : null;

    logoutChannel?.addEventListener('message', (event) => {
        if (event.data?.type === 'logout' && loginUrl) {
            window.location.replace(loginUrl);
        }
    });

    logoutForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = logoutForm.querySelector('button[type="submit"]');
        submitButton?.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(logoutForm.action, {
                body: new FormData(logoutForm),
                credentials: 'same-origin',
                method: 'POST',
            });

            if (!response.ok) {
                throw new Error('Não foi possível encerrar a sessão.');
            }

            logoutChannel?.postMessage({ type: 'logout' });
            window.location.replace(loginUrl || response.url);
        } catch {
            HTMLFormElement.prototype.submit.call(logoutForm);
        }
    });
}

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function initialCurrencyCents(value) {
    const normalized = String(value).trim();
    const decimal = normalized.match(/^(\d+)[.,](\d{1,2})$/);

    if (decimal) {
        return Number(decimal[1]) * 100 + Number(decimal[2].padEnd(2, '0'));
    }

    return Number(normalized.replace(/\D/g, '') || 0);
}

document.querySelectorAll('[data-currency-input]').forEach((field) => {
    const setCents = (cents) => {
        const safeCents = Math.min(Number(cents) || 0, 999999999999);
        field.dataset.currencyCents = String(safeCents);
        field.value = currencyFormatter.format(safeCents / 100);
    };

    setCents(initialCurrencyCents(field.value));

    field.addEventListener('input', () => {
        setCents(field.value.replace(/\D/g, ''));
    });

    field.form?.addEventListener('submit', () => {
        field.value = (Number(field.dataset.currencyCents || 0) / 100).toFixed(2);
    });
});
