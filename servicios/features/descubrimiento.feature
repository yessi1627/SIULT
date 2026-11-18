# language: es
Característica: Descubrimiento de servicios en el API Gateway
  Como administrador del sistema
  quiero que el gateway encuentre solo las instancias vivas de cada microservicio
  para repartir la carga y no enviar peticiones a un servicio caído

  Escenario: Repartir la carga entre dos instancias
    Dado que en el registro hay 2 instancias vivas del servicio "calendario"
    Cuando llegan 4 peticiones para "calendario"
    Entonces cada instancia atiende 2 peticiones

  Escenario: Un servicio sin instancias vivas
    Dado que en el registro hay 0 instancias vivas del servicio "notificaciones"
    Cuando llega una petición para "notificaciones"
    Entonces el gateway no encuentra a quién enviarla

  Escenario: Una instancia deja de enviar latidos
    Dado que en el registro hay 2 instancias vivas del servicio "calendario"
    Y la instancia "calendario-2" deja de enviar latidos y su registro vence
    Cuando llegan 3 peticiones para "calendario"
    Entonces todas las peticiones las atiende "calendario-1"
