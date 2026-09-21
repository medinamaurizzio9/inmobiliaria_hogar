document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-client-match-form]');
    if (!form) return;

    const fields = [...form.querySelectorAll('[data-match-field]')];
    const preview = form.querySelector('[data-match-preview]');
    let controller = null;

    function render(data) {
        preview.replaceChildren();
        const matches = data.exact ? [data.exact] : (data.potential || []);
        if (!matches.length) {
            preview.hidden = true;
            return;
        }

        const exact = Boolean(data.exact);
        const box = document.createElement('div');
        box.className = `commercial-match-card ${exact ? 'exact' : 'potential'}`;
        const title = document.createElement('strong');
        title.textContent = exact ? 'Cliente ya registrado. No se creará otro registro.' : 'Posible cliente existente. Verifique antes de guardar.';
        box.appendChild(title);

        matches.forEach((match) => {
            const item = document.createElement('p');
            const protection = match.protection?.label ? ` · ${match.protection.label}` : '';
            item.textContent = `${match.nombre} · CI ${match.documento || 'sin CI'} · ${match.responsible_advisor}${protection}`;
            box.appendChild(item);
        });
        preview.appendChild(box);
        preview.hidden = false;
    }

    async function checkMatches() {
        const params = new URLSearchParams();
        fields.forEach((field) => {
            if (field.value.trim()) params.set(field.name, field.value.trim());
        });
        if (![...params.values()].some(Boolean)) return;

        controller?.abort();
        controller = new AbortController();
        try {
            const response = await fetch(`${form.dataset.matchUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (response.ok) render(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') preview.hidden = true;
        }
    }

    fields.forEach((field) => field.addEventListener('blur', checkMatches));
});
