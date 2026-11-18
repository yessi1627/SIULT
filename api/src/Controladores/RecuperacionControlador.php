<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\BaseDatos;
use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Recuperacion de contraseña en dos pasos:
 *  1. POST /auth/recuperar { email }: genero un token aleatorio, guardo su hash con vencimiento de
 *     30 minutos y envio el enlace al correo. Respondo SIEMPRE el mismo mensaje, exista o no el
 *     correo, para no revelar que usuarios estan registrados.
 *  2. POST /auth/restablecer { token, password, password_confirmacion }: si el token es valido,
 *     no esta usado y no vencio, cambio la contraseña y lo marco como usado (sirve una sola vez).
 */
final class RecuperacionControlador
{
    private const MINUTOS_VIGENCIA = 30;
    private const LONGITUD_MINIMA = 6;
    private const MENSAJE_GENERICO = 'Si el correo está registrado, enviamos un enlace para restablecer la contraseña.';

    public function solicitar(Peticion $peticion): Respuesta
    {
        $validador = new Validador($peticion);
        $email = $validador->email('email');
        $validador->verificar();

        $respuesta = ['mensaje' => self::MENSAJE_GENERICO];
        $usuario = Usuario::activos()->where('email', $email)->first();
        if (!$usuario) {
            return Respuesta::ok($respuesta);
        }

        $token = bin2hex(random_bytes(32));
        BaseDatos::transaccion(function () use ($usuario, $token) {
            // Un solo enlace activo por usuario: invalido los anteriores
            Capsule::table('recuperaciones_clave')->where('id_usuario', $usuario->id_usuario)->delete();
            Capsule::table('recuperaciones_clave')->insert([
                'id_usuario' => $usuario->id_usuario,
                'token_hash' => hash('sha256', $token),
                'expira' => date('Y-m-d H:i:s', time() + self::MINUTOS_VIGENCIA * 60),
                'fecha_creacion' => date('Y-m-d H:i:s'),
            ]);
        });

        $enlace = rtrim(getenv('FRONTEND_URL') ?: 'http://localhost:4200', '/') . '/restablecer?token=' . $token;
        $enviado = $this->enviarCorreo($usuario->email, $usuario->nombres, $enlace);

        // Sin servidor de correo configurado (entorno local), devuelvo el enlace para poder probar el flujo.
        // En produccion (ENTORNO=produccion) nunca se devuelve: solo viaja por correo.
        if (!$enviado && getenv('ENTORNO') !== 'produccion') {
            $respuesta['enlace_desarrollo'] = $enlace;
        }
        return Respuesta::ok($respuesta);
    }

    public function restablecer(Peticion $peticion): Respuesta
    {
        $validador = new Validador($peticion);
        $token = $validador->texto('token', 'El enlace');
        $password = $validador->texto('password', 'La contraseña nueva');
        if ($password !== null && mb_strlen($password) < self::LONGITUD_MINIMA) {
            $validador->agregarError('password', 'La contraseña debe tener al menos ' . self::LONGITUD_MINIMA . ' caracteres');
        }
        if ($password !== null && $password !== $peticion->campo('password_confirmacion')) {
            $validador->agregarError('password_confirmacion', 'Las contraseñas no coinciden');
        }
        $validador->verificar();

        BaseDatos::transaccion(function () use ($token, $password) {
            // lockForUpdate: si el mismo enlace se usa dos veces al tiempo, la segunda espera y lo ve usado
            $registro = Capsule::table('recuperaciones_clave')
                ->where('token_hash', hash('sha256', (string) $token))
                ->lockForUpdate()
                ->first();
            if (!$registro || $registro->usado || $registro->expira < date('Y-m-d H:i:s')) {
                throw new ErrorHttp(410, 'El enlace no es válido o ya venció. Solicite uno nuevo.');
            }
            Usuario::whereKey($registro->id_usuario)->update(['password' => password_hash((string) $password, PASSWORD_DEFAULT)]);
            Capsule::table('recuperaciones_clave')->where('id_recuperacion', $registro->id_recuperacion)->update(['usado' => 1]);
        });

        return Respuesta::ok(['mensaje' => 'Su contraseña fue actualizada. Ya puede iniciar sesión.']);
    }

    // Envio el correo con mail() solo si el servidor de correo esta habilitado (CORREO_HABILITADO=1)
    private function enviarCorreo(string $email, string $nombre, string $enlace): bool
    {
        if (getenv('CORREO_HABILITADO') !== '1') {
            return false;
        }
        $asunto = 'SIULT - Restablecer contraseña';
        $cuerpo = "Hola $nombre,\n\nPara crear una contraseña nueva abre este enlace (vence en "
            . self::MINUTOS_VIGENCIA . " minutos):\n\n$enlace\n\nSi no pediste el cambio, ignora este correo.";
        return @mail($email, $asunto, $cuerpo, 'Content-Type: text/plain; charset=UTF-8');
    }
}
