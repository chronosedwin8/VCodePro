<?php
/**
 * Niveles del plan de aula VCodePro (6.º a 12.º).
 */

declare(strict_types=1);

return [
    [
        'codigo' => 'N6', 'nombre' => 'Sexto · Pensamiento computacional', 'grado' => '6.º',
        'programa_ib' => 'PAI 1', 'asignatura' => 'Diseño', 'edad' => '11 a 12 años',
        'descripcion' => 'Primer contacto con la programación. El estudiante descubre que un programa es una secuencia de decisiones que él controla, y aprende a describir un problema antes de escribir una sola línea de código.',
        'contenidos' => 'Algoritmos y secuencias|Variables y entrada de datos|Condicionales|Ciclos con repetición fija|Dibujo con Turtle|Primer agente de IA guiado',
        'proyecto_insignia' => 'Laberinto interactivo con niveles diseñados por el estudiante',
        'lenguajes' => 'Python (turtle), bloques', 'color' => '#22c55e', 'orden' => 1,
    ],
    [
        'codigo' => 'N7', 'nombre' => 'Séptimo · Programas con datos', 'grado' => '7.º',
        'programa_ib' => 'PAI 2', 'asignatura' => 'Diseño', 'edad' => '12 a 13 años',
        'descripcion' => 'El estudiante pasa de programas que solo muestran texto a programas que guardan, cuentan y transforman información, y publica su primer producto en la web.',
        'contenidos' => 'Listas y recorridos|Ciclos condicionales|Cadenas de texto|Aleatoriedad y simulación|Diccionarios|HTML y CSS iniciales',
        'proyecto_insignia' => 'Videojuego de plataformas en 2D con niveles propios',
        'lenguajes' => 'Python, HTML, CSS', 'color' => '#14b8a6', 'orden' => 2,
    ],
    [
        'codigo' => 'N8', 'nombre' => 'Octavo · Funciones, archivos y datos reales', 'grado' => '8.º',
        'programa_ib' => 'PAI 3', 'asignatura' => 'Diseño', 'edad' => '13 a 14 años',
        'descripcion' => 'La clase trabaja con datos reales del colegio: los lee de archivos, los limpia, los analiza y los comunica con gráficas. Aparecen las pruebas y la depuración sistemática.',
        'contenidos' => 'Funciones y parámetros|Lectura y escritura de archivos|CSV y datos abiertos|Gráficas con matplotlib|Manejo de errores|Casos de prueba',
        'proyecto_insignia' => 'Estación meteorológica que analiza datos reales del colegio',
        'lenguajes' => 'Python, JavaScript', 'color' => '#0ea5e9', 'orden' => 3,
    ],
    [
        'codigo' => 'N9', 'nombre' => 'Noveno · Objetos y aplicaciones web', 'grado' => '9.º',
        'programa_ib' => 'PAI 4', 'asignatura' => 'Diseño e Informática', 'edad' => '14 a 15 años',
        'descripcion' => 'El estudiante modela el mundo con clases y objetos, construye su primera aplicación web completa y aprende a trabajar en equipo con control de versiones.',
        'contenidos' => 'Programación orientada a objetos|Herencia y polimorfismo|Diagramas UML|Formularios y DOM|JSON y almacenamiento|SQL inicial|Git y trabajo colaborativo',
        'proyecto_insignia' => 'Aplicación web para una necesidad real de la comunidad escolar',
        'lenguajes' => 'Python, JavaScript, HTML, SQL', 'color' => '#6366f1', 'orden' => 4,
    ],
    [
        'codigo' => 'N10', 'nombre' => 'Décimo · Solución completa y Proyecto Personal', 'grado' => '10.º',
        'programa_ib' => 'PAI 5', 'asignatura' => 'Diseño, Informática y Proyecto Personal', 'edad' => '15 a 16 años',
        'descripcion' => 'Año de cierre del PAI: el estudiante conduce un proyecto de principio a fin, con usuarios reales, base de datos, servidor y una evaluación honesta de su producto.',
        'contenidos' => 'Investigación de usuarios|Modelo entidad-relación|Backend con PHP y MySQL|Autenticación y seguridad|Accesibilidad|Pruebas con usuarios|Despliegue|Ética de la IA',
        'proyecto_insignia' => 'Proyecto Personal: producto digital con bitácora completa del ciclo de diseño',
        'lenguajes' => 'PHP, MySQL, JavaScript, Python', 'color' => '#a855f7', 'orden' => 5,
    ],
    [
        'codigo' => 'N11', 'nombre' => 'Undécimo · Informática NM y NS (DP 1)', 'grado' => '11.º',
        'programa_ib' => 'DP 1', 'asignatura' => 'Informática NM y NS', 'edad' => '16 a 17 años',
        'descripcion' => 'Primer año del Diploma. Se estudian los fundamentos del sistema informático —representación, arquitectura, redes— y las estructuras de datos y algoritmos que sostienen cualquier solución seria.',
        'contenidos' => 'Representación de datos|Arquitectura del computador|Redes y protocolos|Pilas, colas y listas enlazadas|Árboles y recorridos|Complejidad algorítmica|POO en Java|Bases de datos|Aprendizaje automático',
        'proyecto_insignia' => 'Planteamiento y diseño de la solución de la evaluación interna',
        'lenguajes' => 'Java, Python, SQL', 'color' => '#f59e0b', 'orden' => 6,
    ],
    [
        'codigo' => 'N12', 'nombre' => 'Duodécimo · Evaluación interna y monografía (DP 2)', 'grado' => '12.º',
        'programa_ib' => 'DP 2', 'asignatura' => 'Informática NS, ITGS y Monografía', 'edad' => '17 a 18 años',
        'descripcion' => 'Año de producción final: el estudiante desarrolla, prueba, optimiza, documenta y sustenta su solución, y analiza su impacto social con las herramientas de ITGS.',
        'contenidos' => 'Diseño y registro de desarrollo|Técnicas avanzadas|Pruebas y casos límite|Optimización y complejidad|Documentación y video|Estudios de caso ITGS|Concurrencia|Monografía|Sustentación',
        'proyecto_insignia' => 'Solución final documentada, video de demostración y monografía de Informática',
        'lenguajes' => 'Java, Python, SQL, JavaScript', 'color' => '#ef4444', 'orden' => 7,
    ],
];
