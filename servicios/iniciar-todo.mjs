// Arranca los tres servicios Node en una sola terminal, con el nombre de cada uno antes de sus mensajes.
// Uso: npm run iniciar   (Ctrl + C los detiene todos)
import { spawn } from 'node:child_process';

const servicios = [
  { nombre: 'notificaciones', color: 35 },
  { nombre: 'calendario', color: 36 },
  { nombre: 'gateway', color: 33 },
];

const procesos = servicios.map(({ nombre, color }) => {
  const hijo = spawn(process.execPath, ['src/index.js'], { cwd: new URL(`./${nombre}/`, import.meta.url) });
  const prefijo = `\x1b[${color}m[${nombre}]\x1b[0m `;
  const escribir = (destino) => (datos) =>
    datos
      .toString()
      .split(/\r?\n/)
      .filter(Boolean)
      .forEach((linea) => destino.write(prefijo + linea + '\n'));
  hijo.stdout.on('data', escribir(process.stdout));
  hijo.stderr.on('data', escribir(process.stderr));
  hijo.on('exit', (codigo) => console.log(`${prefijo}terminó (código ${codigo})`));
  return hijo;
});

const detener = () => {
  procesos.forEach((p) => p.kill('SIGTERM'));
  setTimeout(() => process.exit(0), 1500);
};
process.on('SIGINT', detener);
process.on('SIGTERM', detener);
