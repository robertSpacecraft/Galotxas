const closeDropdowns = (except = null) => {
    document.querySelectorAll('[data-admin-dropdown]').forEach((toggle) => {
        if (toggle === except) {
            return;
        }

        toggle.setAttribute('aria-expanded', 'false');
        toggle.nextElementSibling?.classList.remove('show');
    });
};

document.querySelectorAll('[data-admin-menu-toggle]').forEach((toggle) => {
    const target = document.querySelector(toggle.getAttribute('data-admin-menu-toggle'));

    if (! target) {
        return;
    }

    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(! expanded));
        toggle.setAttribute(
            'aria-label',
            expanded ? 'Abrir menú de administración' : 'Cerrar menú de administración'
        );
        target.classList.toggle('show', ! expanded);
    });
});

document.querySelectorAll('[data-admin-dropdown]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        closeDropdowns(toggle);
        toggle.setAttribute('aria-expanded', String(! expanded));
        toggle.nextElementSibling?.classList.toggle('show', ! expanded);
    });
});

document.addEventListener('click', (event) => {
    if (! event.target.closest('.dropdown')) {
        closeDropdowns();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const expandedDropdown = document.querySelector('[data-admin-dropdown][aria-expanded="true"]');
    closeDropdowns();
    expandedDropdown?.focus();

    const menuToggle = document.querySelector('[data-admin-menu-toggle][aria-expanded="true"]');
    const menu = menuToggle
        ? document.querySelector(menuToggle.getAttribute('data-admin-menu-toggle'))
        : null;

    if (menuToggle && menu) {
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Abrir menú de administración');
        menu.classList.remove('show');
        menuToggle.focus();
    }
});
