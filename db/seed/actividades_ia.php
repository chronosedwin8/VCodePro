<?php
/**
 * Banco complementario · Uso de asistentes de inteligencia artificial
 *
 * Dos actividades por nivel (6.º a 12.º) en las que el estudiante debe usar un
 * asistente de propósito general —ChatGPT, Claude, Gemini, Copilot o el que
 * autorice el colegio— cumpliendo los requisitos del portal:
 *
 *   1. Declarar el uso de IA en la entrega (campo obligatorio).
 *   2. Adjuntar el registro completo de las conversaciones.
 *   3. Verificar de forma independiente todo lo que la IA afirme.
 *   4. Distinguir con claridad la autoría propia de la asistida.
 *   5. Respetar el modo examen del grupo cuando esté activo.
 *
 * El arreglo está indexado por el código del nivel.
 */

declare(strict_types=1);

return [

// =========================================================== 6.º · PAI 1 ===
'N6' => [

['codigo' => 'VCP-6-11', 'titulo' => 'El detective de respuestas: comprobar lo que dice la IA',
 'objeto' => 'el informe de verificación',
 'resumen' => 'Hacerle diez preguntas verificables a un asistente de IA, comprobar cada respuesta en fuentes independientes y medir cuántas veces acertó.',
 'descripcion' => '<p>El estudiante le hace a un asistente de IA diez preguntas cuya respuesta se puede comprobar: la altura de una montaña, el año de un hecho, el resultado de una operación larga, la población de una ciudad. Después verifica cada respuesta en dos fuentes independientes y anota si coincidió, si falló o si la IA se contradijo al repetir la pregunta.</p><p>El resultado sorprende siempre: el asistente responde con la misma seguridad cuando acierta y cuando inventa. Al terminar, cada estudiante escribe su propia regla de oro sobre cuándo puede confiar en una respuesta y cuándo tiene que comprobarla, y esa regla se pega en la cartelera del salón.</p>',
 'pregunta' => 'Si una máquina responde siempre con seguridad, ¿como sé cuándo me está diciendo la verdad?',
 'contexto' => 'Innovación científica y técnica', 'concepto' => 'Comunicación',
 'perfil' => 'Indagador|Pensador|Íntegro', 'atl' => 'Alfabetización mediática|Pensamiento crítico',
 'objetivos' => 'Formular preguntas cuya respuesta se pueda verificar|Contrastar una respuesta con fuentes independientes|Medir la tasa de acierto de una herramienta|Registrar y declarar el uso de IA de forma completa',
 'entregables' => 'Tabla de diez preguntas con la respuesta de la IA y la de las fuentes|Registro completo de las conversaciones|Regla de oro personal sobre cuándo verificar|Declaración de uso de IA en la entrega',
 'lenguaje' => 'Lenguaje natural', 'dificultad' => 'inicial', 'sesiones' => 3, 'horas' => 3.0, 'ia' => 1,
 'codigo_inicial' => "# Tabla de verificacion (completa en texto)\n# | # | Pregunta | Respuesta de la IA | Fuente 1 | Fuente 2 | Coincide? |\n# | 1 |          |                    |          |          |           |\n#\n# Recuerda: pega el enlace o el nombre exacto de cada fuente.\n# Guarda TODA la conversacion, tambien las respuestas equivocadas.",
 'fases' => [
   ['indagar', 'Qué es una pregunta verificable', 'Con tu grupo, clasifica veinte preguntas en verificables (tienen una respuesta comprobable) y no verificables (opinión, gusto, predicción). Explica por qué las segundas no sirven para esta actividad.', 'Lista de veinte preguntas clasificadas con la justificación de tres de ellas.', 45],
   ['desarrollar', 'Elegir las diez preguntas y las fuentes', 'Selecciona diez preguntas verificables de temas distintos, incluida al menos una operación matemática larga. Decide de antemano qué dos fuentes usarás para comprobar cada una y por qué son confiables.', 'Las diez preguntas con las dos fuentes elegidas para cada una.', 45],
   ['crear', 'Preguntar, registrar y comprobar', 'Haz las diez preguntas al asistente autorizado por el colegio. Copia la respuesta completa, sin resumirla. Repite dos preguntas en otra conversación para ver si responde igual. Luego verifica todo en tus fuentes.', 'Tabla completa de verificación y el registro íntegro de las conversaciones.', 70],
   ['evaluar', 'Mi regla de oro', 'Calcula cuántas respuestas acertó de diez. Identifica qué tipo de pregunta falló más. Escribe tu regla de oro: en qué casos usarás la IA sin comprobar y en cuáles siempre verificarás.', 'Tasa de acierto, análisis del tipo de error y regla de oro escrita.', 40],
 ],
 'recursos' => [
   ['plantilla', 'Tabla de verificación de respuestas', 'Formato de seis columnas listo para imprimir.'],
   ['plantilla', 'Formato de declaración de uso de IA', 'Qué herramienta, para qué, y qué parte del trabajo es propia.'],
   ['lectura', 'Por qué una IA inventa datos', 'Explicación para estudiantes de PAI 1, sin tecnicismos.'],
 ]],

['codigo' => 'VCP-6-12', 'titulo' => 'Mi cuento con IA: qué puso la máquina y qué puse yo',
 'objeto' => 'el cuento con autoría marcada',
 'resumen' => 'Escribir un cuento propio usando la IA solo para generar ideas y sugerir mejoras, marcando con colores qué parte es de cada uno.',
 'descripcion' => '<p>El estudiante escribe un cuento breve. Puede pedirle a la IA lluvia de ideas, nombres de personajes o sugerencias para mejorar un párrafo, pero no puede pedirle que escriba el cuento. Cada frase del texto final se marca con un color: propia, propia mejorada con una sugerencia, o tomada de la IA.</p><p>El conteo final es la clave de la actividad: cuando un cuento queda mayoritariamente en color de la IA, el propio estudiante se da cuenta de que ya no es suyo. Es la forma más directa de entender qué significa la probidad académica antes de que se convierta en un reglamento abstracto.</p>',
 'pregunta' => '¿Hasta dónde puede ayudarme una máquina sin que el trabajo deje de ser mío?',
 'contexto' => 'Expresión personal y cultural', 'concepto' => 'Comunicación',
 'perfil' => 'Íntegro|Buen comunicador|Reflexivo', 'atl' => 'Pensamiento creativo|Reflexión',
 'objetivos' => 'Usar la IA como apoyo y no como sustituto|Distinguir la autoría propia de la asistida|Evaluar el efecto de una sugerencia sobre el texto|Declarar el uso de IA con evidencia',
 'entregables' => 'Cuento final con cada frase marcada por autoría|Conteo de frases por color|Registro de las conversaciones con la IA|Reflexión sobre qué habría cambiado sin la ayuda',
 'lenguaje' => 'Lenguaje natural', 'dificultad' => 'inicial', 'sesiones' => 4, 'horas' => 4.0, 'ia' => 1,
 'codigo_inicial' => "# Codigo de colores de autoria\n# VERDE   frase escrita por mi, sin ayuda\n# AMARILLO frase mia que mejore con una sugerencia de la IA\n# ROJO    frase tomada de la IA casi tal cual\n#\n# Regla de la actividad: el cuento debe quedar con mas VERDE que\n# AMARILLO y ROJO juntos. Si no es asi, hay que reescribirlo.",
 'fases' => [
   ['indagar', 'Ayudas que suman y ayudas que reemplazan', 'Analiza tres formas de ayuda para escribir: que alguien te dé una idea, que te corrija la ortografía, que te escriba el párrafo. Ordénalas de la que más te enseña a la que menos, y explica el orden.', 'Análisis de tres tipos de ayuda con el orden justificado.', 40],
   ['desarrollar', 'La idea es mía', 'Escribe tú el planteamiento del cuento: personaje, problema y final. Solo después pide a la IA cinco ideas para el ambiente o los nombres. Anota cuáles tomaste y cuáles descartaste, con el motivo.', 'Planteamiento propio y lista de ideas de la IA aceptadas y descartadas.', 45],
   ['crear', 'Escribir y marcar', 'Escribe el cuento completo. Cuando pidas una sugerencia, cópiala en el registro antes de aplicarla. Al terminar, marca cada frase con su color y cuenta cuántas hay de cada uno.', 'Cuento terminado con las frases marcadas y el conteo por color.', 75],
   ['evaluar', 'Sin la ayuda, ¿qué cambiaba?', 'Elige tres frases amarillas o rojas y reescríbelas sin la IA. Compara las dos versiones y decide cuál queda en el cuento final. Explica si la ayuda te enseñó algo o solo te ahorró trabajo.', 'Las tres frases en sus dos versiones y la reflexión final.', 40],
 ],
 'recursos' => [
   ['plantilla', 'Hoja de marcado de autoría', 'Cuadrícula de tres colores para imprimir.'],
   ['lectura', 'Probidad académica en el PAI, explicada para 6.º', 'Una página con ejemplos del aula.'],
 ]],
],

// =========================================================== 7.º · PAI 2 ===
'N7' => [

['codigo' => 'VCP-7-11', 'titulo' => 'Laboratorio de instrucciones: preguntar mejor',
 'objeto' => 'el laboratorio de instrucciones',
 'resumen' => 'Comparar tres formas de pedir lo mismo a una IA y medir con una rúbrica propia cuál produce el mejor resultado.',
 'descripcion' => '<p>El estudiante define una tarea concreta —por ejemplo, explicar los ciclos a un compañero de 6.º— y la pide de tres formas: vaga, específica, y específica con un ejemplo y un formato de salida. Evalúa las tres respuestas con una rúbrica que él mismo redacta antes de ver los resultados.</p><p>La lección no es una lista de trucos: es que la calidad de una respuesta depende de la calidad de la pregunta, igual que ocurre con las personas. El estudiante descubre que escribir una buena instrucción exige entender primero lo que quiere, y eso es pensamiento computacional, no magia.</p>',
 'pregunta' => '¿Por qué la misma máquina me da respuestas tan distintas según cómo le pregunte?',
 'contexto' => 'Innovación científica y técnica', 'concepto' => 'Comunicación',
 'perfil' => 'Pensador|Buen comunicador', 'atl' => 'Comunicación|Pensamiento crítico',
 'objetivos' => 'Redactar instrucciones precisas con contexto, formato y ejemplo|Diseñar una rúbrica antes de evaluar|Comparar resultados con criterios explícitos|Registrar y declarar el uso de IA',
 'entregables' => 'Las tres instrucciones y sus tres respuestas completas|Rúbrica propia de cuatro criterios|Tabla comparativa con la puntuación de cada versión|Registro de conversaciones y declaración de uso',
 'lenguaje' => 'Lenguaje natural', 'dificultad' => 'inicial', 'sesiones' => 3, 'horas' => 3.0, 'ia' => 1,
 'codigo_inicial' => "# Las tres versiones de la misma peticion\n# A) Vaga:        \"explicame los ciclos\"\n# B) Especifica:  \"explica los ciclos for a un estudiante de 6.o que\n#                  ya sabe usar variables, en menos de 120 palabras\"\n# C) Con ejemplo y formato: la version B, mas un ejemplo del tono que\n#    quieres y la instruccion de terminar con una pregunta de repaso.\n#\n# Evalua las tres con TU rubrica, escrita ANTES de leer las respuestas.",
 'fases' => [
   ['indagar', 'Instrucciones ambiguas en la vida real', 'Recoge tres instrucciones ambiguas que hayas recibido esta semana (de un adulto, de un instructivo, de un juego). Explica qué información faltaba y qué salió mal.', 'Ficha de tres instrucciones ambiguas con la información faltante.', 40],
   ['desarrollar', 'La rúbrica antes que la respuesta', 'Define la tarea que le pedirás a la IA y redacta una rúbrica de cuatro criterios para juzgar la respuesta (por ejemplo: claridad, nivel adecuado, ejemplos, longitud). Escribe las tres versiones de la instrucción.', 'Rúbrica de cuatro criterios y las tres instrucciones redactadas.', 45],
   ['crear', 'Ejecutar y puntuar a ciegas', 'Pide las tres versiones en conversaciones separadas y guarda las respuestas completas. Pide a un compañero que las puntúe con tu rúbrica sin saber qué instrucción produjo cada una.', 'Tres respuestas guardadas y la puntuación cruzada del compañero.', 60],
   ['evaluar', 'Qué elemento hizo la diferencia', 'Compara las puntuaciones y determina qué elemento de la instrucción mejoró más el resultado: el contexto, el formato o el ejemplo. Escribe tu plantilla personal de instrucción para el resto del año.', 'Análisis comparativo y plantilla personal de instrucción.', 40],
 ],
 'recursos' => [
   ['plantilla', 'Rúbrica de calidad de respuesta', 'Cuatro criterios con escala de 1 a 4.'],
   ['plantilla', 'Registro de conversaciones', 'Formato de anexo exigido en las entregas.'],
 ]],

['codigo' => 'VCP-7-12', 'titulo' => 'Depurar con IA: aceptar, corregir o rechazar',
 'objeto' => 'el registro de depuración asistida',
 'resumen' => 'Corregir un programa con errores en tres condiciones —solo, con IA y con IA verificada— y decidir con datos cuándo conviene cada una.',
 'descripcion' => '<p>El estudiante recibe tres programas con errores del mismo tipo. Corrige el primero solo, el segundo pidiendo ayuda a la IA sin comprobar nada, y el tercero pidiendo ayuda pero verificando cada sugerencia antes de aplicarla. Cronometra las tres y anota si el programa quedó realmente correcto.</p><p>Entre las sugerencias hay al menos una equivocada, plantada por el docente en el enunciado del problema. El estudiante que la aplica sin verificar termina con un programa peor que el original, y esa experiencia enseña más que cualquier advertencia sobre el uso responsable de la IA.</p>',
 'pregunta' => '¿Me hace más rápido pedir ayuda a la IA, o solo me hace sentir más rápido?',
 'contexto' => 'Innovación científica y técnica', 'concepto' => 'Lógica',
 'perfil' => 'Pensador|Íntegro|Reflexivo', 'atl' => 'Pensamiento crítico|Autogestión',
 'objetivos' => 'Aplicar un método de depuración propio|Evaluar críticamente una sugerencia antes de aplicarla|Medir tiempo y corrección en tres condiciones|Documentar cada decisión sobre la ayuda recibida',
 'entregables' => 'Los tres programas corregidos|Tabla de tiempos y resultados de las tres condiciones|Registro de sugerencias con la decisión tomada en cada una|Declaración de uso de IA',
 'lenguaje' => 'Python', 'dificultad' => 'intermedio', 'sesiones' => 4, 'horas' => 4.0, 'ia' => 1,
 'codigo_inicial' => "# Registro de sugerencias\n# | # | Sugerencia de la IA | La probe? | Decision | Motivo |\n# | 1 |                     | si / no   | acepto / corrijo / rechazo |  |\n#\n# Regla: ninguna sugerencia entra al codigo sin ejecutarla antes.",
 'fases' => [
   ['indagar', 'Mi método actual', 'Corrige el primer programa sin ninguna ayuda y cronometra. Anota los pasos que seguiste para encontrar el error: eso es tu método actual de depuración.', 'Programa 1 corregido, tiempo empleado y método propio escrito.', 45],
   ['desarrollar', 'Protocolo de la comparación', 'Define cómo compararás las tres condiciones: qué mides, cuándo detienes el reloj y cómo compruebas que el programa quedó realmente correcto (casos de prueba escritos antes).', 'Protocolo de comparación con los casos de prueba definidos.', 40],
   ['crear', 'Las tres condiciones', 'Corrige el programa 2 aplicando lo que diga la IA sin comprobar. Corrige el programa 3 verificando cada sugerencia con tus casos de prueba antes de aplicarla. Registra todas las sugerencias.', 'Programas 2 y 3 con el registro completo de sugerencias y decisiones.', 70],
   ['evaluar', 'La sugerencia equivocada', 'Ejecuta tus casos de prueba en los tres programas. Localiza la sugerencia incorrecta y explica por qué parecía razonable. Concluye con datos cuándo usarás IA para depurar y con qué condición.', 'Tabla comparativa, análisis de la sugerencia equivocada y conclusión.', 45],
 ],
 'recursos' => [
   ['codigo', 'tres_programas_con_errores.py', 'Los tres programas de partida, con errores equivalentes.'],
   ['plantilla', 'Registro de sugerencias de IA', 'Sugerencia, comprobación, decisión y motivo.'],
 ]],
],

// =========================================================== 8.º · PAI 3 ===
'N8' => [

['codigo' => 'VCP-8-11', 'titulo' => 'Documentar mi código con IA y cazar las descripciones falsas',
 'objeto' => 'la documentación verificada',
 'resumen' => 'Pedir a la IA que documente un programa propio y comprobar, ejecutando, que cada afirmación de la documentación es cierta.',
 'descripcion' => '<p>El estudiante entrega uno de sus programas a un asistente de IA y le pide comentarios y descripciones de cada función. La documentación resultante suena impecable, pero contiene afirmaciones que el código no cumple: parámetros que se describen mal, casos límite que no se manejan, valores de retorno inventados.</p><p>La tarea real es verificar: por cada afirmación de la documentación, el estudiante escribe una prueba que la confirme o la desmienta. La documentación final solo conserva lo que se pudo comprobar, y esa disciplina es la misma que se le exigirá en la evaluación interna del Diploma.</p>',
 'pregunta' => '¿Una explicación que suena correcta describe realmente lo que hace mi programa?',
 'contexto' => 'Innovación científica y técnica', 'concepto' => 'Sistemas',
 'perfil' => 'Íntegro|Pensador', 'atl' => 'Pensamiento crítico|Organización',
 'objetivos' => 'Convertir afirmaciones en pruebas ejecutables|Detectar descripciones falsas en una documentación|Documentar funciones con precisión verificable|Registrar y declarar el uso de IA',
 'entregables' => 'Documentación generada por la IA, sin editar|Tabla de afirmaciones con su prueba y resultado|Documentación final corregida|Registro de conversaciones y declaración de uso',
 'lenguaje' => 'Python', 'dificultad' => 'intermedio', 'sesiones' => 4, 'horas' => 4.0, 'ia' => 1,
 'codigo_inicial' => "# Verificacion de la documentacion\n# | # | Afirmacion de la documentacion | Prueba que la comprueba | Resultado |\n# | 1 | \"devuelve None si la lista esta vacia\" | promedio([]) | ERROR: division por cero |\n#\n# Solo sobrevive a la version final lo que quede comprobado.",
 'fases' => [
   ['indagar', 'Qué debe decir una buena documentación', 'Compara la documentación de dos funciones de la biblioteca estándar de Python. Anota qué información aparece siempre: qué recibe, qué devuelve, qué hace ante casos límite y qué errores lanza.', 'Análisis de dos funciones con los cuatro elementos identificados.', 40],
   ['desarrollar', 'Preparar el encargo y las pruebas', 'Elige un programa propio de al menos cuatro funciones. Redacta la instrucción exacta que darás a la IA. Antes de pedirla, escribe qué casos límite tiene realmente tu código.', 'Instrucción redactada y lista de casos límite reales del programa.', 45],
   ['crear', 'Generar y verificar afirmación por afirmación', 'Pide la documentación y guárdala sin editar. Descompónla en afirmaciones numeradas. Escribe y ejecuta una prueba por cada una. Marca las que resulten falsas.', 'Documentación original, tabla de afirmaciones y pruebas ejecutadas.', 80],
   ['evaluar', 'La versión que sí es cierta', 'Corrige o elimina las afirmaciones falsas y produce la documentación final. Calcula qué proporción de la documentación original era incorrecta y explica por qué sonaba convincente.', 'Documentación final y análisis de la proporción de errores.', 45],
 ],
 'recursos' => [
   ['plantilla', 'Tabla de verificación de afirmaciones', 'Afirmación, prueba, resultado, decisión.'],
   ['lectura', 'Qué es una buena docstring', 'Parámetros, retorno, excepciones y casos límite.'],
 ]],

['codigo' => 'VCP-8-12', 'titulo' => 'Experto simulado contra experto real',
 'objeto' => 'el informe de contraste',
 'resumen' => 'Preparar una entrevista con ayuda de IA, entrevistar a una persona real y documentar qué aportó cada fuente.',
 'descripcion' => '<p>El estudiante investiga un tema técnico del colegio —la red, el mantenimiento de los equipos, la gestión de la biblioteca— primero preguntando a un asistente de IA y después entrevistando a la persona que hace ese trabajo todos los días. Documenta las dos versiones y las compara punto por punto.</p><p>El contraste es siempre revelador: la IA describe el caso general y la persona conoce las excepciones, las restricciones de presupuesto y las decisiones que nadie escribió. El estudiante aprende que la IA es un buen punto de partida y un pésimo punto de llegada cuando el conocimiento es local.</p>',
 'pregunta' => '¿Qué sabe la persona que hace el trabajo y que ninguna IA puede saber?',
 'contexto' => 'Identidades y relaciones', 'concepto' => 'Relaciones',
 'perfil' => 'Indagador|Buen comunicador|De mentalidad abierta', 'atl' => 'Alfabetización informacional|Comunicación',
 'objetivos' => 'Usar la IA para preparar una indagación, no para reemplazarla|Formular preguntas de entrevista abiertas|Contrastar conocimiento general y conocimiento local|Citar y declarar cada fuente utilizada',
 'entregables' => 'Guion de entrevista con las preguntas propuestas y las descartadas|Acta de la entrevista real|Tabla de contraste entre las dos fuentes|Registro de conversaciones y declaración de uso',
 'lenguaje' => 'Lenguaje natural', 'dificultad' => 'intermedio', 'sesiones' => 4, 'horas' => 4.0, 'ia' => 1,
 'codigo_inicial' => "# Tabla de contraste\n# | Tema | Version de la IA | Version de la persona | Quien acerto y como lo comprobe |\n#\n# Marca con * cada dato que SOLO podia conocer la persona entrevistada.",
 'fases' => [
   ['indagar', 'Qué dice la IA sobre el tema', 'Elige el tema y la persona que entrevistarás. Pregunta a la IA cómo funciona ese proceso en general y guarda la respuesta completa. Subraya las afirmaciones que podrías comprobar en tu colegio.', 'Respuesta completa de la IA con las afirmaciones comprobables subrayadas.', 45],
   ['desarrollar', 'Guion de entrevista', 'Pide a la IA diez preguntas para la entrevista, descarta las que no apliquen a tu colegio y escribe tres propias que la IA no podría haber formulado. Explica por qué descartaste cada una.', 'Guion final con las preguntas propuestas, descartadas y propias.', 45],
   ['crear', 'La entrevista real', 'Realiza la entrevista con registro. Pregunta expresamente por las excepciones y por lo que la teoría no contempla. No corrijas a la persona con lo que dijo la IA.', 'Acta de entrevista con al menos ocho respuestas registradas.', 65],
   ['evaluar', 'Punto por punto', 'Construye la tabla de contraste. Marca cada dato que solo podía conocer la persona. Concluye para qué sirve la IA en una investigación y en qué momento deja de servir.', 'Tabla de contraste completa y conclusión de una página.', 45],
 ],
 'recursos' => [
   ['plantilla', 'Guion de entrevista', 'Preguntas abiertas y errores que sesgan la respuesta.'],
   ['plantilla', 'Tabla de contraste de fuentes', 'Tema, fuente A, fuente B, verificación.'],
 ]],
],

// =========================================================== 9.º · PAI 4 ===
'N9' => [

['codigo' => 'VCP-9-11', 'titulo' => 'Programar en pareja con IA: aceptar, corregir o rechazar',
 'objeto' => 'la sesión de programación asistida',
 'resumen' => 'Desarrollar una función con asistencia de IA registrando cada sugerencia y la decisión tomada, y medir cuántas necesitaron corrección.',
 'descripcion' => '<p>El estudiante desarrolla una funcionalidad real con un asistente de IA al lado, pero con una regla estricta: ninguna sugerencia entra al proyecto sin que él la lea, la entienda y decida. Cada sugerencia se registra como aceptada, modificada o rechazada, siempre con el motivo escrito.</p><p>Al final se calculan dos indicadores: qué porcentaje de las sugerencias necesitó corrección y cuántas líneas del archivo final el estudiante es capaz de explicar sin mirar. El segundo indicador es el que importa: código que no puedes explicar no es tuyo, aunque esté en tu repositorio.</p>',
 'pregunta' => '¿Puedo explicar cada línea del programa que voy a entregar con mi nombre?',
 'contexto' => 'Innovación científica y técnica', 'concepto' => 'Sistemas',
 'perfil' => 'Íntegro|Pensador|Reflexivo', 'atl' => 'Pensamiento crítico|Autogestión|Reflexión',
 'objetivos' => 'Evaluar críticamente el código sugerido antes de integrarlo|Registrar decisiones técnicas con su justificación|Medir la fiabilidad de la asistencia recibida|Sostener la autoría del trabajo entregado',
 'entregables' => 'Funcionalidad terminada y probada|Registro de todas las sugerencias con su decisión y motivo|Porcentaje de sugerencias que necesitaron corrección|Sustentación oral de cinco fragmentos elegidos por el docente',
 'lenguaje' => 'Python o JavaScript', 'dificultad' => 'intermedio', 'sesiones' => 5, 'horas' => 5.0, 'ia' => 1,
 'codigo_inicial' => "// Registro de sesion asistida\n// | # | Que pedi | Sugerencia (resumen) | Decision | Motivo | Prueba que corri |\n// | 1 |          |                      | acepto / modifico / rechazo |  |  |\n//\n// Al terminar: marca en el archivo final las lineas que NO podrias\n// explicar en voz alta. Esas se reescriben antes de entregar.",
 'fases' => [
   ['indagar', 'Qué necesito construir', 'Define con precisión la funcionalidad: entradas, salidas, casos límite y criterios de aceptación. Escribe las pruebas antes de programar nada y antes de abrir el asistente.', 'Especificación de la funcionalidad y sus casos de prueba.', 50],
   ['desarrollar', 'Reglas de la sesión asistida', 'Redacta las reglas que respetarás: qué le pedirás a la IA, qué no le pedirás nunca, y qué comprobación harás antes de aceptar una sugerencia. Acuérdalas con tu docente.', 'Reglas de la sesión firmadas por el docente.', 40],
   ['crear', 'Construir registrando cada decisión', 'Desarrolla la funcionalidad. Por cada sugerencia, anota qué pediste, qué te respondió, qué probaste y qué decidiste. Ejecuta tus pruebas después de cada integración.', 'Funcionalidad terminada, pruebas en verde y registro completo de sugerencias.', 95],
   ['evaluar', 'Prueba de autoría', 'Calcula el porcentaje de sugerencias que tuviste que corregir o rechazar. El docente elige cinco fragmentos del archivo final y tú los explicas en voz alta. Reescribe lo que no puedas explicar.', 'Indicadores calculados, acta de sustentación y fragmentos reescritos.', 55],
 ],
 'recursos' => [
   ['plantilla', 'Registro de sesión asistida', 'Petición, sugerencia, prueba, decisión, motivo.'],
   ['plantilla', 'Acta de sustentación de código', 'Cinco fragmentos, explicación y valoración del docente.'],
 ]],

['codigo' => 'VCP-9-12', 'titulo' => 'Revisión de código: la IA contra un compañero',
 'objeto' => 'la revisión de código comparada',
 'resumen' => 'Definir una lista de verificación propia, pedir una revisión a la IA y otra a un compañero, y comparar qué encontró cada uno.',
 'descripcion' => '<p>El estudiante redacta primero su propia lista de verificación de calidad —nombres, funciones con una sola responsabilidad, manejo de errores, casos límite, accesibilidad si aplica— y solo después pide dos revisiones del mismo archivo: una a un asistente de IA y otra a un compañero de clase.</p><p>La comparación es instructiva en las dos direcciones: la IA suele encontrar más problemas de forma y el compañero suele entender mejor la intención del programa y detectar lo que falta. El estudiante decide qué observaciones aplica y justifica cada rechazo, incluidos los de la IA.</p>',
 'pregunta' => '¿Qué tipo de error encuentra una máquina y cuál solo lo ve una persona que entiende mi objetivo?',
 'contexto' => 'Identidades y relaciones', 'concepto' => 'Relaciones',
 'perfil' => 'De mentalidad abierta|Reflexivo|Solidario', 'atl' => 'Colaboración|Pensamiento crítico',
 'objetivos' => 'Definir criterios de calidad propios antes de recibir opiniones|Contrastar dos fuentes de revisión|Aceptar o rechazar observaciones con argumentos|Aplicar mejoras verificables al código',
 'entregables' => 'Lista de verificación propia de ocho criterios|Las dos revisiones completas|Tabla de observaciones con la decisión y el motivo|Versión final del código con las mejoras aplicadas',
 'lenguaje' => 'Python o JavaScript', 'dificultad' => 'intermedio', 'sesiones' => 4, 'horas' => 4.0, 'ia' => 1,
 'codigo_inicial' => "// Tabla de observaciones\n// | # | Observacion | Origen (IA / companero) | Tipo (forma, logica, intencion) |\n// |   | Aplico? | Motivo |\n//\n// Cuenta al final cuantas observaciones de cada tipo aporto cada fuente.",
 'fases' => [
   ['indagar', 'Qué hace bueno a un código', 'Investiga dos guías de estilo reales. Extrae ocho criterios comprobables y descarta los que sean cuestión de gusto. Explica por qué descartaste tres.', 'Lista de verificación de ocho criterios con los descartes justificados.', 45],
   ['desarrollar', 'Preparar las dos revisiones', 'Elige el archivo a revisar y redacta la instrucción para la IA usando tu lista de verificación. Prepara la misma lista impresa para tu compañero, sin darle pistas de lo que encontraste tú.', 'Instrucción para la IA y hoja de revisión para el compañero.', 40],
   ['crear', 'Recoger y clasificar las observaciones', 'Obtén las dos revisiones completas. Clasifica cada observación por tipo: forma, lógica o intención del programa. Marca las que coinciden entre ambas fuentes.', 'Las dos revisiones y la tabla de observaciones clasificada.', 70],
   ['evaluar', 'Decidir con argumentos', 'Decide qué aplicas y justifica cada rechazo, también los de la IA. Aplica las mejoras y comprueba que las pruebas siguen pasando. Concluye qué aporta cada fuente de revisión.', 'Código final, justificación de los rechazos y conclusión comparativa.', 45],
 ],
 'recursos' => [
   ['plantilla', 'Lista de verificación de calidad', 'Ocho criterios comprobables, sin cuestiones de gusto.'],
   ['plantilla', 'Hoja de revisión entre pares', 'Formato para la revisión del compañero.'],
 ]],
],

// ========================================================== 10.º · PAI 5 ===
'N10' => [

['codigo' => 'VCP-10-11', 'titulo' => 'IA en la investigación de usuarios: generar y descartar',
 'objeto' => 'la investigación de usuarios contrastada',
 'resumen' => 'Usar la IA para generar perfiles de usuario y guiones, contrastarlos con usuarios reales y documentar cuánto de lo generado era falso.',
 'descripcion' => '<p>El estudiante pide a un asistente de IA que proponga perfiles de usuario, necesidades y un guion de entrevista para su proyecto. Después sale a hablar con usuarios reales y marca, dato por dato, qué se confirmó, qué se desmintió y qué necesidad importante la IA no mencionó nunca.</p><p>Es la actividad que previene el error más caro del criterio A: construir una solución para un usuario imaginado. El indicador final —la proporción de supuestos generados que no resistieron el contacto con usuarios reales— suele superar la mitad, y esa cifra convence más que cualquier advertencia del docente.</p>',
 'pregunta' => '¿Estoy investigando a mis usuarios o confirmando lo que una máquina supuso sobre ellos?',
 'contexto' => 'Equidad y desarrollo', 'concepto' => 'Relaciones',
 'perfil' => 'Indagador|De mentalidad abierta|Íntegro', 'atl' => 'Alfabetización informacional|Pensamiento crítico',
 'objetivos' => 'Distinguir supuesto generado de dato recogido|Diseñar preguntas que puedan desmentir una hipótesis|Medir la validez de los supuestos con usuarios reales|Trazar cada especificación a evidencia verificable',
 'entregables' => 'Perfiles y supuestos generados por la IA, sin editar|Registro de tres entrevistas reales|Tabla de supuestos confirmados, desmentidos y omitidos|Especificaciones finales trazadas a evidencia',
 'lenguaje' => 'Documentación', 'dificultad' => 'avanzado', 'sesiones' => 5, 'horas' => 5.0, 'ia' => 1,
 'codigo_inicial' => "// Tabla de validacion de supuestos\n// | # | Supuesto generado por la IA | Como lo comprobe | Confirmado / Desmentido |\n// | -- | Necesidad real que la IA NO menciono | Evidencia | Usuario que la planteo |\n//\n// Indicador final: % de supuestos que no resistieron el contacto real.",
 'fases' => [
   ['indagar', 'Generar los supuestos', 'Describe el contexto de tu proyecto a la IA y pídele perfiles de usuario, necesidades y un guion de entrevista. Guarda la respuesta sin editar: es tu punto de partida, no tu conclusión.', 'Perfiles, necesidades y guion generados, guardados íntegros.', 50],
   ['desarrollar', 'Preguntas que pueden desmentir', 'Convierte cada supuesto en una pregunta abierta capaz de desmentirlo. Elimina las preguntas que solo pueden confirmar lo que ya crees. Agrega dos preguntas propias sobre el contexto local.', 'Guion final de entrevista con las preguntas de refutación marcadas.', 50],
   ['crear', 'Tres usuarios reales', 'Entrevista a tres usuarios reales con registro. No menciones los perfiles generados ni corrijas al usuario. Anota literalmente lo que digan, incluido lo que contradiga tus supuestos.', 'Registro de tres entrevistas con citas literales.', 85],
   ['evaluar', 'Qué sobrevivió al contacto real', 'Completa la tabla de validación y calcula el porcentaje de supuestos desmentidos. Identifica la necesidad más importante que la IA no mencionó. Reescribe tus especificaciones con la evidencia real.', 'Tabla de validación, indicador calculado y especificaciones corregidas.', 55],
 ],
 'recursos' => [
   ['plantilla', 'Tabla de validación de supuestos', 'Supuesto, comprobación, veredicto, evidencia.'],
   ['plantilla', 'Matriz de trazabilidad', 'Especificación, comprobación, evidencia de usuario.'],
 ]],

['codigo' => 'VCP-10-12', 'titulo' => 'Política de uso de IA para el departamento',
 'objeto' => 'la política de uso de IA',
 'resumen' => 'Redactar, consultando a docentes y estudiantes, una política de uso aceptable de IA aplicable en el colegio, y someterla a revisión.',
 'descripcion' => '<p>El estudiante investiga qué dicen las orientaciones del IB sobre el uso de herramientas de inteligencia artificial, consulta a docentes y compañeros sobre los casos que de verdad generan dudas, y redacta una política de uso aceptable para su departamento: qué se permite, qué se permite declarando, y qué está prohibido.</p><p>La exigencia es que la política resuelva casos concretos, no que enuncie principios. Se prueba con diez situaciones reales de clase, y cada una debe poder clasificarse sin discusión. Las que quedan ambiguas obligan a reescribir la regla, que es justamente el trabajo que hacen los colegios cuando redactan su reglamento.</p>',
 'pregunta' => '¿Puede una regla escrita resolver un caso real sin que dos personas la interpreten distinto?',
 'contexto' => 'Equidad y desarrollo', 'concepto' => 'Sistemas',
 'perfil' => 'Íntegro|Buen comunicador|Solidario', 'atl' => 'Comunicación|Pensamiento crítico|Colaboración',
 'objetivos' => 'Interpretar orientaciones institucionales y traducirlas a reglas aplicables|Consultar a las partes interesadas|Redactar normas verificables sin ambigüedad|Probar una norma contra casos reales',
 'entregables' => 'Resumen de las orientaciones consultadas con su fuente|Consulta a dos docentes y cinco estudiantes|Política de uso en una página|Prueba de la política contra diez casos reales',
 'lenguaje' => 'Documentación', 'dificultad' => 'avanzado', 'sesiones' => 5, 'horas' => 5.0, 'ia' => 1,
 'codigo_inicial' => "// Estructura de la politica\n// 1. Alcance: a que trabajos aplica\n// 2. Permitido sin declarar\n// 3. Permitido declarando (que se declara y como)\n// 4. Prohibido\n// 5. Que pasa si se incumple\n// 6. Diez casos resueltos como ejemplo\n//\n// Cada regla debe poder responderse con si o no ante un caso concreto.",
 'fases' => [
   ['indagar', 'Qué dicen las orientaciones y qué dudas hay', 'Consulta las orientaciones del IB sobre uso de IA y el reglamento del colegio. Entrevista a dos docentes y cinco estudiantes sobre los casos que les generan dudas reales.', 'Resumen con fuentes citadas y registro de las siete consultas.', 60],
   ['desarrollar', 'De los principios a las reglas', 'Convierte cada duda recogida en una regla que responda sí o no. Clasifícalas en las tres categorías: sin declarar, declarando y prohibido. Elimina toda formulación que dependa de la interpretación.', 'Borrador de la política con las reglas clasificadas.', 55],
   ['crear', 'Redactar y probar contra casos', 'Redacta la política en una página. Reúne diez casos reales de clase y clasifícalos con tu propia política. Reescribe cada regla que deje un caso ambiguo.', 'Política redactada y tabla de los diez casos clasificados.', 75],
   ['evaluar', 'Revisión con quienes la aplicarían', 'Presenta la política a un docente y a tres compañeros; pídeles clasificar tres casos nuevos usándola. Si no coinciden entre ellos, la regla falla: corrígela y documenta el cambio.', 'Acta de la revisión, coincidencias medidas y versión final corregida.', 50],
 ],
 'recursos' => [
   ['lectura', 'Orientaciones del IB sobre herramientas de IA', 'Resumen con los puntos que afectan a las entregas del colegio.'],
   ['plantilla', 'Diez casos de uso para clasificar', 'Situaciones reales de aula, listas para la prueba.'],
 ]],
],

// ============================================================ 11.º · DP 1 ===
'N11' => [

['codigo' => 'VCP-11-11', 'titulo' => 'Auditar código generado: corrección, complejidad y casos límite',
 'objeto' => 'la auditoría del código generado',
 'resumen' => 'Pedir a la IA tres soluciones al mismo problema algorítmico, analizar su complejidad, probarlas y encontrar la que falla.',
 'descripcion' => '<p>El estudiante plantea un problema algorítmico no trivial y pide a un asistente de IA tres soluciones distintas. Analiza la complejidad temporal y espacial de cada una, diseña un conjunto de pruebas con casos límite y las ejecuta con conjuntos de datos crecientes.</p><p>Al menos una de las soluciones falla: por un caso límite no contemplado, por una complejidad peor de la anunciada o por un error silencioso en datos grandes. Encontrarla exige exactamente las destrezas que evalúa el examen del Diploma, con la ventaja de que aquí el error lo cometió otro y el estudiante ejerce de auditor.</p>',
 'pregunta' => '¿Una solución que compila y pasa el ejemplo es una solución correcta?',
 'contexto' => 'Innovación científica y técnica', 'concepto' => 'Sistemas',
 'perfil' => 'Pensador|Íntegro|Indagador', 'atl' => 'Pensamiento crítico|Transferencia',
 'objetivos' => 'Analizar la complejidad de un algoritmo dado|Diseñar pruebas con casos límite y datos crecientes|Detectar errores silenciosos en código ajeno|Justificar la elección de una solución con evidencia empírica',
 'entregables' => 'Las tres soluciones generadas, sin editar|Análisis de complejidad de cada una|Conjunto de pruebas con casos límite y mediciones|Informe con el fallo localizado y la solución elegida',
 'lenguaje' => 'Java o Python', 'dificultad' => 'avanzado', 'sesiones' => 5, 'horas' => 5.0, 'ia' => 1,
 'codigo_inicial' => "// Ficha de auditoria por solucion\n// Complejidad anunciada:            Complejidad real analizada:\n// Casos limite contemplados:        Casos limite que fallan:\n// Medicion n=100 / 1000 / 100000:\n// Veredicto: correcta / incorrecta / correcta pero ineficiente\n//\n// Prueba siempre con: vacio, un elemento, todos iguales, ya ordenado,\n// orden inverso y el mayor tamano que soporte tu equipo.",
 'fases' => [
   ['indagar', 'El problema y su solución de referencia', 'Elige el problema algorítmico y resuélvelo tú primero, aunque sea de forma ingenua. Determina la complejidad de tu solución: será la referencia contra la que juzgues las demás.', 'Problema definido, solución propia y su análisis de complejidad.', 50],
   ['desarrollar', 'Diseñar la auditoría', 'Redacta la petición para obtener tres enfoques distintos. Diseña el conjunto de pruebas —casos normales, límite y de volumen— antes de ver ninguna solución generada.', 'Petición redactada y conjunto de pruebas con al menos doce casos.', 50],
   ['crear', 'Generar, analizar y medir', 'Obtén las tres soluciones y guárdalas sin editar. Analiza la complejidad de cada una a mano. Ejecuta las pruebas y mide tiempos con n de 100, 1.000 y 100.000.', 'Las tres soluciones, sus análisis y la tabla de mediciones.', 95],
   ['evaluar', 'Encontrar el fallo y elegir', 'Localiza la solución que falla y explica exactamente por qué: caso límite, complejidad o error silencioso. Elige la solución que usarías y justifica con tus mediciones, no con la explicación de la IA.', 'Informe de auditoría con el fallo demostrado y la elección justificada.', 55],
 ],
 'recursos' => [
   ['plantilla', 'Ficha de auditoría de solución', 'Complejidad, casos límite, mediciones y veredicto.'],
   ['lectura', 'Catálogo de casos límite', 'Quince entradas que rompen la mayoría de algoritmos.'],
 ]],

['codigo' => 'VCP-11-12', 'titulo' => 'IA en la evaluación interna: dónde ayuda y dónde está prohibida',
 'objeto' => 'el plan de uso de IA en la evaluación interna',
 'resumen' => 'Delimitar por escrito, con las orientaciones del IB en la mano, qué partes de la evaluación interna pueden apoyarse en IA y cuáles deben ser íntegramente propias.',
 'descripcion' => '<p>El estudiante toma su propia propuesta de evaluación interna y la descompone en tareas: consulta al cliente, criterios de éxito, diagramas, algoritmos, código, pruebas, video y evaluación. Para cada tarea decide, citando la orientación correspondiente, si el apoyo de IA está permitido, permitido con declaración o prohibido.</p><p>Después pone a prueba su propio plan: usa la IA en dos tareas permitidas, registra íntegramente las conversaciones y comprueba que el resultado sigue siendo defendible ante el docente. La entrega es el plan más la evidencia, y ambos se conservan hasta la entrega final del año siguiente.</p>',
 'pregunta' => '¿Qué parte de mi evaluación interna dejaría de ser mía si la apoyo en una IA?',
 'contexto' => 'Equidad y desarrollo', 'concepto' => 'Sistemas',
 'perfil' => 'Íntegro|Reflexivo|Pensador', 'atl' => 'Autogestión|Reflexión|Alfabetización informacional',
 'objetivos' => 'Interpretar las orientaciones sobre uso de IA y aplicarlas a un caso propio|Delimitar el alcance permitido tarea por tarea|Registrar el uso de forma trazable y auditable|Sostener la autoría ante una sustentación',
 'entregables' => 'Descomposición de la evaluación interna en tareas|Plan de uso de IA con la orientación citada por tarea|Registro íntegro del uso en dos tareas permitidas|Acta de sustentación con el docente supervisor',
 'lenguaje' => 'Documentación', 'dificultad' => 'avanzado', 'sesiones' => 4, 'horas' => 5.0, 'ia' => 1,
 'codigo_inicial' => "// Plan de uso por tarea de la evaluacion interna\n// | Tarea | Apoyo de IA | Orientacion que lo sustenta | Que declaro |\n// | Consulta al cliente        | prohibido  |  |  |\n// | Criterios de exito         | ?          |  |  |\n// | Diagramas                  | ?          |  |  |\n// | Algoritmos y codigo        | ?          |  |  |\n// | Pruebas                    | ?          |  |  |\n// | Video de demostracion      | ?          |  |  |\n// | Evaluacion final           | ?          |  |  |",
 'fases' => [
   ['indagar', 'Leer la norma antes de opinar', 'Consulta las orientaciones del IB sobre uso de herramientas de IA y las normas del colegio. Extrae las cinco reglas que afectan directamente a la evaluación interna, citando la fuente de cada una.', 'Resumen de cinco reglas con la cita textual y la fuente de cada una.', 50],
   ['desarrollar', 'Delimitar tarea por tarea', 'Descompón tu evaluación interna en al menos siete tareas. Para cada una decide el nivel de apoyo permitido y sustenta la decisión con una de las reglas anteriores. Somételo a tu supervisor.', 'Plan de uso completo, revisado por el docente supervisor.', 55],
   ['crear', 'Usar y registrar en dos tareas', 'Aplica el apoyo de IA en dos tareas que tu plan permita. Guarda las conversaciones íntegras y señala qué de lo obtenido usaste, qué modificaste y qué descartaste.', 'Registro íntegro de las dos tareas con lo usado, modificado y descartado.', 75],
   ['evaluar', 'Sustentar la autoría', 'Explica ante tu supervisor las decisiones tomadas en esas dos tareas sin consultar las conversaciones. Ajusta el plan donde la práctica mostró que la regla era imprecisa.', 'Acta de sustentación y plan de uso corregido.', 55],
 ],
 'recursos' => [
   ['lectura', 'Orientaciones del IB sobre herramientas de IA', 'Puntos aplicables a la evaluación interna de Informática.'],
   ['plantilla', 'Plan de uso de IA por tarea', 'Tarea, nivel permitido, orientación, declaración.'],
   ['plantilla', 'Anexo de conversaciones', 'Formato de registro que acompaña la entrega final.'],
 ]],
],

// ============================================================ 12.º · DP 2 ===
'N12' => [

['codigo' => 'VCP-12-11', 'titulo' => 'Declaración de uso de IA de la evaluación interna',
 'objeto' => 'la declaración de uso de IA',
 'resumen' => 'Producir el registro trazable y auditable del uso de IA en la solución final, y someterlo a una auditoría entre pares.',
 'descripcion' => '<p>El estudiante reúne todo el uso de inteligencia artificial que hizo durante el desarrollo de su solución y produce la declaración que acompaña la entrega: qué herramienta, en qué fecha, para qué tarea, qué obtuvo, qué conservó y qué es de su autoría. Cada afirmación de la declaración debe poder respaldarse con la conversación correspondiente.</p><p>La auditoría la hace un compañero: toma la declaración, elige tres entradas al azar y comprueba que la evidencia existe y coincide. Una declaración que no resiste esa auditoría tampoco resistiría una consulta del colegio, y el estudiante lo descubre a tiempo de corregirla.</p>',
 'pregunta' => '¿Mi declaración resiste que alguien la revise línea por línea contra la evidencia?',
 'contexto' => 'Equidad y desarrollo', 'concepto' => 'Comunicación',
 'perfil' => 'Íntegro|Reflexivo|Buen comunicador', 'atl' => 'Organización|Reflexión|Comunicación',
 'objetivos' => 'Reconstruir y ordenar el uso de IA de un proyecto largo|Redactar una declaración verificable|Vincular cada afirmación con su evidencia|Auditar y ser auditado sobre la probidad del trabajo',
 'entregables' => 'Declaración de uso de IA completa y fechada|Anexo con las conversaciones referenciadas|Informe de la auditoría recibida de un compañero|Versión corregida tras la auditoría',
 'lenguaje' => 'Documentación', 'dificultad' => 'avanzado', 'sesiones' => 4, 'horas' => 5.0, 'ia' => 1,
 'codigo_inicial' => "// Entrada de la declaracion\n// Fecha:            Herramienta y version:\n// Tarea de la EI:   Criterio afectado (A-E):\n// Que pedi exactamente:\n// Que obtuve:\n// Que conserve, que modifique y que descarte:\n// Evidencia: anexo n.o ___, pagina ___\n//\n// Regla: si una entrada no tiene evidencia localizable, se elimina\n// de la declaracion y el trabajo correspondiente se rehace.",
 'fases' => [
   ['indagar', 'Qué exige exactamente la declaración', 'Consulta las orientaciones vigentes y el reglamento del colegio. Determina qué información es obligatoria, en qué formato y hasta cuándo debe conservarse la evidencia.', 'Ficha de requisitos con la fuente de cada exigencia.', 45],
   ['desarrollar', 'Reconstruir el historial', 'Recorre tu registro de desarrollo y localiza cada momento en que usaste IA. Ordénalos por fecha y vincúlalos con la tarea y el criterio de la evaluación interna afectado.', 'Historial ordenado con la tarea y el criterio de cada entrada.', 55],
   ['crear', 'Redactar la declaración y el anexo', 'Redacta la declaración con una entrada por uso. Prepara el anexo con las conversaciones numeradas. Elimina toda entrada cuya evidencia no puedas localizar y marca el trabajo que habría que rehacer.', 'Declaración completa y anexo de conversaciones numerado.', 80],
   ['evaluar', 'Auditoría entre pares', 'Intercambia la declaración con un compañero. Cada uno elige tres entradas al azar y comprueba que la evidencia existe y coincide con lo declarado. Corrige lo que la auditoría señale.', 'Informe de auditoría recibido y declaración corregida.', 55],
 ],
 'recursos' => [
   ['plantilla', 'Formato de declaración de uso de IA', 'Una entrada por uso, con evidencia referenciada.'],
   ['plantilla', 'Protocolo de auditoría entre pares', 'Selección al azar, comprobación y veredicto.'],
 ]],

['codigo' => 'VCP-12-12', 'titulo' => 'ITGS: auditar un sistema de IA en uso',
 'objeto' => 'la auditoría del sistema de IA',
 'resumen' => 'Analizar un sistema de IA real en producción, probarlo con casos propios y evaluar su impacto sobre las partes interesadas.',
 'descripcion' => '<p>El estudiante elige un sistema de IA que ya afecta a personas —selección de personal, moderación de contenido, recomendación educativa, apoyo diagnóstico— y lo audita con el marco de ITGS: quiénes son las partes interesadas, qué decide el sistema, qué recurso tiene quien resulta perjudicado y qué evidencia pública existe sobre su desempeño.</p><p>Además de la documentación, el estudiante diseña y ejecuta una prueba propia: un conjunto de casos comparables que se diferencien solo en un atributo, para observar si el sistema responde distinto. La prueba se realiza únicamente sobre sistemas de acceso público y sin datos de personas reales, y esa restricción metodológica forma parte de la evaluación.</p>',
 'pregunta' => '¿Quién responde cuando un sistema automático se equivoca con una persona concreta?',
 'contexto' => 'Globalización y sustentabilidad', 'concepto' => 'Relaciones',
 'perfil' => 'De mentalidad abierta|Íntegro|Solidario|Pensador', 'atl' => 'Pensamiento crítico|Alfabetización mediática|Comunicación',
 'objetivos' => 'Aplicar el marco de análisis de ITGS a un sistema de IA real|Diseñar una prueba controlada y ética|Evaluar impactos sobre partes interesadas en conflicto|Proponer medidas de rendición de cuentas viables',
 'entregables' => 'Análisis del sistema con seis fuentes evaluadas|Mapa de tres partes interesadas y sus intereses|Diseño y resultados de la prueba propia|Propuesta de dos medidas de rendición de cuentas',
 'lenguaje' => 'Documentación', 'dificultad' => 'avanzado', 'sesiones' => 5, 'horas' => 6.0, 'ia' => 1,
 'codigo_inicial' => "// Diseno de la prueba controlada\n// Hipotesis: el sistema responde distinto al cambiar ______\n// Casos: pares identicos que solo difieren en ese atributo\n// Repeticiones por caso: ___ (para descartar variacion aleatoria)\n// Registro: entrada exacta, salida exacta, fecha y hora\n//\n// Restricciones eticas obligatorias:\n// - Solo sistemas de acceso publico.\n// - Datos inventados; jamas datos de personas reales.\n// - Sin intentar eludir controles ni terminos de uso.",
 'fases' => [
   ['indagar', 'El sistema y sus partes interesadas', 'Elige el sistema y reúne seis fuentes, al menos una de la empresa que lo opera y una crítica. Identifica tres partes interesadas con intereses en conflicto y qué gana o pierde cada una.', 'Seis fuentes evaluadas y mapa de partes interesadas.', 65],
   ['desarrollar', 'Diseñar una prueba defendible', 'Formula una hipótesis y diseña la prueba: pares de casos que solo difieran en un atributo, número de repeticiones y qué registrarás. Documenta las restricciones éticas que respetarás.', 'Diseño experimental con las restricciones éticas por escrito.', 55],
   ['crear', 'Ejecutar y documentar', 'Ejecuta la prueba registrando entrada y salida exactas con fecha. Analiza si las diferencias observadas son consistentes o atribuibles al azar. No extrapoles más allá de tus datos.', 'Registro completo de la prueba y análisis de los resultados.', 90],
   ['evaluar', 'Rendición de cuentas', 'Evalúa el impacto sobre cada parte interesada con tu evidencia y la documentada. Propón dos medidas de rendición de cuentas viables e indica sus límites. Somete el análisis a la prueba de equilibrio con un compañero.', 'Análisis de impacto, dos medidas propuestas y registro de la prueba de equilibrio.', 60],
 ],
 'recursos' => [
   ['plantilla', 'Marco de análisis ITGS', 'Sistema, partes interesadas, impactos, soluciones.'],
   ['plantilla', 'Protocolo de prueba controlada', 'Hipótesis, pares de casos, repeticiones, registro.'],
   ['lectura', 'Límites éticos de la auditoría de sistemas', 'Qué se puede probar y qué no, y por qué.'],
 ]],
],

];
