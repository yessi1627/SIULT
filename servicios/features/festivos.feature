# language: es
Característica: Aviso de días festivos al programar una tarea
  Como profesor
  quiero saber si la fecha de entrega que elijo es festivo en Colombia
  para no programar entregas en días sin clase

  Antecedentes:
    Dado que el calendario oficial de Colombia para 2026 incluye:
      | fecha      | nombre                  |
      | 2026-01-01 | Año Nuevo               |
      | 2026-07-20 | Día de la Independencia |
      | 2026-12-25 | Navidad                 |

  Esquema del escenario: Consultar si una fecha es festivo
    Cuando el profesor elige la fecha de entrega "<fecha>"
    Entonces el sistema indica que festivo es "<festivo>"
    Y el nombre del festivo es "<nombre>"

    Ejemplos:
      | fecha      | festivo | nombre                  |
      | 2026-12-25 | sí      | Navidad                 |
      | 2026-07-20 | sí      | Día de la Independencia |
      | 2026-11-13 | no      | -                       |

  Escenario: La segunda consulta del mismo año no vuelve a llamar al servicio externo
    Cuando el profesor elige la fecha de entrega "2026-12-25"
    Y el profesor elige la fecha de entrega "2026-01-01"
    Entonces el servicio externo de festivos se consultó 1 vez
    Y la última respuesta salió de la "cache"

  Escenario: El servicio externo se cae pero hay una copia de respaldo
    Dado que el profesor ya consultó la fecha "2026-12-25"
    Y la caché de 24 horas venció
    Y el servicio externo de festivos dejó de responder
    Cuando el profesor elige la fecha de entrega "2026-12-25"
    Entonces el sistema indica que festivo es "sí"
    Y la última respuesta salió de la "respaldo"

  Escenario: Cortocircuito tras fallas repetidas del servicio externo
    Dado que el servicio externo de festivos dejó de responder
    Cuando se consultan los festivos de 2030 5 veces
    Entonces el servicio externo de festivos se consultó 3 veces
    Y el cortocircuito queda abierto
