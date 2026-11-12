// Se carga en <head> (bloqueante) para aplicar el tema guardado antes del
// primer pintado y evitar el destello del modo claro. La CSP permite 'self'.
(function () {
  try {
    if (localStorage.getItem('netlinks-theme') === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  } catch (error) {
    /* almacenamiento no disponible: se mantiene el modo claro */
  }
})();
