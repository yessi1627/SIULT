import { createProxyMiddleware } from 'http-proxy-middleware';
import { cabecerasDeUsuario } from './autenticacion.js';

// Cabeceras CORS que pone el backend; las quito porque el gateway maneja CORS de forma centralizada
const CABECERAS_CORS = [
  'access-control-allow-origin',
  'access-control-allow-credentials',
  'access-control-allow-methods',
  'access-control-allow-headers',
  'vary',
];

/**
 * Proxy hacia un servicio resuelto dinamicamente con el Service Discovery.
 *  - Primero busco una instancia viva en el registro; si no hay, respondo 503 de inmediato.
 *  - El proxy reescribe la ruta: /api/materias -> http://localhost/proyectoGestorEscolar/api/materias
 *  - timeout: si el servicio no responde a tiempo corto la espera (504) en lugar de colgar al cliente.
 */
export function proxyHacia(nombre, descubrimiento, { timeoutMs, rutaDe = (destino) => destino.rutaBase ?? '' }) {
  const resolverDestino = async (req, res, next) => {
    try {
      req.destino = await descubrimiento.resolver(nombre);
    } catch (error) {
      console.warn(`[gateway] registro no disponible: ${error.message}`);
    }
    if (!req.destino) {
      return res.status(503).json({ data: null, error: `El servicio ${nombre} no está disponible` });
    }
    res.setHeader('X-Servicio', `${nombre}/${req.destino.instancia}`);
    next();
  };

  const proxy = createProxyMiddleware({
    router: (req) => req.destino.url,
    changeOrigin: true,
    proxyTimeout: timeoutMs,
    timeout: timeoutMs,
    pathRewrite: (ruta, req) => rutaDe(req.destino) + ruta,
    on: {
      proxyReq: (peticionProxy, req) => {
        if (req.usuario) {
          for (const [cabecera, valor] of Object.entries(cabecerasDeUsuario(req.usuario))) {
            peticionProxy.setHeader(cabecera, valor);
          }
        }
      },
      proxyRes: (respuestaProxy) => {
        for (const cabecera of CABECERAS_CORS) delete respuestaProxy.headers[cabecera];
      },
      error: (error, req, res) => {
        const agotado = error.code === 'ECONNRESET' || error.code === 'ETIMEDOUT' || /timeout/i.test(error.message);
        console.warn(`[gateway] ${nombre} fallo: ${error.code ?? error.message}`);
        if (typeof res.status === 'function' && !res.headersSent) {
          res.status(agotado ? 504 : 502).json({
            data: null,
            error: agotado ? `El servicio ${nombre} tardó demasiado en responder` : `El servicio ${nombre} no responde`,
          });
        } else {
          res.end?.();
        }
      },
    },
  });

  return [resolverDestino, proxy];
}
