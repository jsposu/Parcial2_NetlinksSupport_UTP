document.querySelectorAll('[data-confirm]').forEach((button) => {
  button.addEventListener('click', (event) => {
    if (!window.confirm(button.dataset.confirm)) event.preventDefault();
  });
});

// Selector de tema claro/oscuro: el tema se aplica en <head> para evitar el
// destello blanco; aquí solo se sincroniza el estado del control y se guarda.
(() => {
  const root = document.documentElement;
  const toggle = document.getElementById('theme-toggle');
  if (!toggle) return;

  const storageKey = 'netlinks-theme';

  const applyTheme = (theme) => {
    const isDark = theme === 'dark';
    if (isDark) root.setAttribute('data-theme', 'dark');
    else root.removeAttribute('data-theme');
    toggle.setAttribute('aria-checked', String(isDark));
    toggle.setAttribute('aria-label', isDark ? 'Desactivar modo oscuro' : 'Activar modo oscuro');
    toggle.title = isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
  };

  const readTheme = () => {
    try {
      return localStorage.getItem(storageKey);
    } catch {
      return null;
    }
  };

  applyTheme(readTheme() === 'dark' ? 'dark' : 'light');

  toggle.addEventListener('click', () => {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    try {
      localStorage.setItem(storageKey, next);
    } catch {
      /* almacenamiento no disponible: el tema solo dura la sesión */
    }
  });
})();

