/**
 * Validacion de sesion para los microservicios.
 * La sesion vive en el backend PHP (cookie PHPSESSID). Antes de enviar una peticion a un microservicio,
 * el gateway pregunta a la API PHP (/auth/me) quien es el usuario y le pasa sus datos en cabeceras
 * X-Usuario-*. Los microservicios no manejan sesiones: confian en el gateway.
 * Guardo la respuesta 15 s por cookie para no consultar al backend en cada peticion.
 */
const CABECERAS_USUARIO = ['x-usuario-id', 'x-usuario-rol', 'x-usuario-materias'];
const CACHE_MS = 15_000;

export function crearValidadorSesion(descubrimiento) {
  const cache = new Map();

  return async function exigirSesion(req, res, next) {
    // Nadie desde afuera puede hacerse pasar por otro usuario enviando estas cabeceras
    for (const cabecera of CABECERAS_USUARIO) delete req.headers[cabecera];

    const cookie = req.headers.cookie ?? '';
    if (!cookie.includes('PHPSESSID=')) {
      return res.status(401).json({ data: null, error: 'Debe iniciar sesion' });
    }

    const guardado = cache.get(cookie);
    if (guardado && guardado.expira > Date.now()) {
      req.usuario = guardado.usuario;
      return next();
    }

    const apiPhp = await descubrimiento.resolver('api-php');
    if (!apiPhp) {
      return res.status(503).json({ data: null, error: 'El servicio de autenticacion no esta disponible' });
    }
    try {
      const respuesta = await fetch(`${apiPhp.url}${apiPhp.rutaBase}/auth/me`, {
        headers: { cookie, 'X-Requested-With': 'XMLHttpRequest' },
        signal: AbortSignal.timeout(5000),
      });
      if (respuesta.status === 401) {
        cache.delete(cookie);
        return res.status(401).json({ data: null, error: 'Debe iniciar sesion' });
      }
      if (!respuesta.ok) throw new Error(`auth/me respondio ${respuesta.status}`);
      const { data } = await respuesta.json();
      const usuario = { id: data.id, rol: data.rol?.nombre ?? '', materias: data.materias ?? [] };
      if (cache.size > 1000) cache.clear();
      cache.set(cookie, { usuario, expira: Date.now() + CACHE_MS });
      req.usuario = usuario;
      next();
    } catch (error) {
      console.warn(`[auth] ${error.message}`);
      res.status(503).json({ data: null, error: 'No se pudo validar la sesion' });
    }
  };
}

// Agrego las cabeceras del usuario a la peticion que va al microservicio
export function cabecerasDeUsuario(usuario) {
  return {
    'x-usuario-id': String(usuario.id),
    'x-usuario-rol': usuario.rol,
    'x-usuario-materias': usuario.materias.join(','),
  };
}
