<?php
/**
 * Plantillas compartidas por el banco de actividades:
 * rúbricas del PAI (Diseño) y del Programa del Diploma (Informática).
 */

declare(strict_types=1);

/**
 * Rúbrica del ciclo de diseño del PAI, adaptada al objeto de la actividad.
 * $objeto: sintagma nominal, p. ej. "la estación meteorológica".
 */
function rubrica_myp(string $objeto): array {
    return [
        ['A', 'Indagación y análisis',
         "No explica la necesidad ni investiga fuentes sobre $objeto, o solo copia información sin analizarla.",
         "Enuncia la necesidad de $objeto y consulta alguna fuente, pero el análisis es superficial y no la relaciona con el problema.",
         "Explica la necesidad de $objeto, analiza productos o soluciones existentes y redacta un planteamiento del problema claro con fuentes citadas.",
         "Justifica con evidencia la necesidad de $objeto, analiza críticamente soluciones existentes, prioriza la información relevante y formula un planteamiento del problema preciso y bien documentado.",
         8],
        ['B', 'Desarrollo de ideas',
         "No propone especificaciones ni ideas de diseño para $objeto.",
         "Enumera algunas especificaciones y una sola idea, sin justificar la elección.",
         "Redacta especificaciones medibles, presenta varias ideas de diseño de $objeto y justifica la elegida frente a las especificaciones.",
         "Redacta especificaciones detalladas y verificables, compara ideas de diseño con criterios explícitos, justifica la elección de $objeto y planifica su construcción con diagramas y tiempos.",
         8],
        ['C', 'Creación de la solución',
         "El código no funciona o no corresponde con lo planeado para $objeto.",
         "Construye una versión incompleta de $objeto; sigue el plan de forma irregular y documenta poco.",
         "Construye $objeto siguiendo el plan, registra los cambios significativos y el programa funciona en los casos habituales.",
         "Construye $objeto de forma completa y ordenada, demuestra un uso técnico competente, documenta cada cambio y justifica las modificaciones al plan original.",
         8],
        ['D', 'Evaluación',
         "No prueba $objeto ni reflexiona sobre el resultado.",
         "Realiza pruebas informales de $objeto y describe el resultado sin compararlo con las especificaciones.",
         "Diseña pruebas pertinentes, contrasta $objeto con cada especificación, recoge opinión de usuarios y propone mejoras.",
         "Diseña pruebas rigurosas con casos límite, evalúa $objeto contra todas las especificaciones con datos de usuarios reales, explica el impacto de la solución y propone mejoras justificadas.",
         8],
    ];
}

/**
 * Rúbrica de la evaluación interna de Informática (Programa del Diploma).
 */
function rubrica_dp(string $objeto): array {
    return [
        ['A', 'Planificación',
         "No identifica al cliente ni el problema que resuelve $objeto.",
         "Describe el escenario de $objeto pero la propuesta de solución es vaga y no hay criterios de éxito.",
         "Presenta el escenario, consulta al cliente y define criterios de éxito medibles para $objeto.",
         "Justifica la necesidad con evidencia de la consulta al cliente, argumenta la propuesta de $objeto y define criterios de éxito completos y verificables.",
         6],
        ['B', 'Descripción general de la solución',
         "No presenta diseño previo de $objeto.",
         "Incluye un diagrama o un plan parcial, sin registro de desarrollo.",
         "Presenta diagramas adecuados, estructuras de datos y un registro de desarrollo de $objeto.",
         "Presenta un diseño completo de $objeto (diagramas, estructuras de datos, prototipos) y un registro de desarrollo detallado acordado con el cliente.",
         6],
        ['C', 'Desarrollo',
         "El producto es mínimo y no se explican las técnicas empleadas.",
         "Emplea técnicas sencillas y explica parcialmente por qué las eligió.",
         "Emplea técnicas apropiadas para $objeto, las explica con ejemplos de código y justifica su elección.",
         "Emplea técnicas complejas y bien seleccionadas en $objeto, explica su funcionamiento con precisión técnica, justifica cada decisión y demuestra ingenio algorítmico.",
         12],
        ['D', 'Funcionalidad y demostración en video',
         "$objeto no funciona o el video no muestra su uso.",
         "$objeto funciona parcialmente; el video omite funciones clave.",
         "$objeto funciona según lo planeado y el video muestra las funciones principales.",
         "$objeto funciona por completo y el video demuestra con claridad todas las funciones frente a los criterios de éxito.",
         4],
        ['E', 'Evaluación',
         "No evalúa $objeto ni recoge retroalimentación.",
         "Comenta el resultado sin usar los criterios de éxito ni la opinión del cliente.",
         "Evalúa $objeto contra los criterios de éxito con retroalimentación del cliente y propone mejoras.",
         "Evalúa $objeto contra todos los criterios de éxito con evidencia del cliente, analiza las limitaciones y propone mejoras realistas y justificadas.",
         6],
    ];
}

/** Devuelve la rúbrica que corresponde al código de nivel. */
function rubrica_para(string $nivel, string $objeto): array {
    return in_array($nivel, ['N11', 'N12'], true) ? rubrica_dp($objeto) : rubrica_myp($objeto);
}
