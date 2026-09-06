<?php
/**
 * Adjuntos de las entregas.
 *
 * El estudiante acompaña su respuesta con documentos, hojas de cálculo,
 * cuadernos de Python, comprimidos o PDF. El archivo se guarda en S3 (bucket
 * privado) y en la base de datos queda solo su ficha.
 *
 * Si S3 no está configurado, se guarda en disco como hasta ahora: así el
 * portal sigue funcionando en un equipo local o en un servidor sin AWS.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/s3.php';

/** Todas las extensiones admitidas, en una sola lista. */
function adjunto_extensiones(): array {
    static $todas = null;
    if ($todas === null) $todas = array_merge(...array_values(ADJUNTO_FAMILIAS));
    return $todas;
}

/** Familia a la que pertenece una extensión, para agrupar en la interfaz. */
function adjunto_familia(string $ext): string {
    foreach (ADJUNTO_FAMILIAS as $familia => $exts) {
        if (in_array($ext, $exts, true)) return $familia;
    }
    return 'Archivo';
}

/**
 * Tipo de contenido con el que se guarda el archivo.
 *
 * Lo que un navegador podría ejecutar al abrirlo —HTML, SVG, XML— se guarda
 * como binario. Aunque las descargas van con Content-Disposition: attachment,
 * si alguien llega al objeto por otro camino no queremos que se renderice.
 */
function adjunto_mime(string $ext): string {
    $riesgo = ['html', 'svg', 'xml', 'xhtml'];
    if (in_array($ext, $riesgo, true)) return 'application/octet-stream';
    return match ($ext) {
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'rtf'  => 'application/rtf',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',
        'csv', 'tsv' => 'text/csv',
        'txt', 'md'  => 'text/plain',
        'py', 'sql', 'java', 'c', 'cpp', 'h', 'css', 'yml', 'yaml' => 'text/plain',
        'json', 'ipynb' => 'application/json',
        'zip'  => 'application/zip',
        'rar'  => 'application/vnd.rar',
        '7z'   => 'application/x-7z-compressed',
        'tar'  => 'application/x-tar',
        'gz', 'tgz' => 'application/gzip',
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
}

/** Tamaño legible: 1,4 MB en vez de 1468006. */
function adjunto_peso(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}

/** Etiqueta corta para el icono de la lista. */
function adjunto_etiqueta(string $ext): string {
    return strtoupper(mb_substr($ext, 0, 4));
}

/**
 * Guarda un archivo subido y devuelve [true, $fila] o [false, 'motivo'].
 * Valida antes de tocar el almacenamiento: extensión, tamaño y errores de PHP.
 */
function adjunto_guardar(array $archivo, int $entregaId, ?int $faseId, int $usuarioId): array {
    $err = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        return [false, match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'El archivo supera el tamaño que admite el servidor.',
            UPLOAD_ERR_PARTIAL   => 'La subida se interrumpió; inténtalo otra vez.',
            UPLOAD_ERR_NO_FILE   => 'No se recibió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                'El servidor no pudo escribir el archivo temporal.',
            default              => 'No se pudo recibir el archivo.',
        }];
    }

    $nombre = mb_substr(utf8_limpio((string) ($archivo['name'] ?? '')), 0, 200);
    $ext    = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $bytes  = (int) ($archivo['size'] ?? 0);

    if ($ext === '' || !in_array($ext, adjunto_extensiones(), true)) {
        return [false, 'No se admiten archivos .' . h($ext) . '. Revisa los formatos permitidos.'];
    }
    if ($bytes <= 0)                    return [false, 'El archivo está vacío.'];
    if ($bytes > ADJUNTO_MAX_BYTES) {
        return [false, 'El archivo pesa ' . adjunto_peso($bytes) . ' y el máximo es '
                     . adjunto_peso(ADJUNTO_MAX_BYTES) . '.'];
    }
    if (!is_uploaded_file($archivo['tmp_name'] ?? '')) {
        return [false, 'La subida no es válida.'];
    }

    $mime = adjunto_mime($ext);
    // Nombre aleatorio: la clave no revela nada y no se puede adivinar.
    $base = bin2hex(random_bytes(16)) . '.' . $ext;

    if (s3_configurado()) {
        $clave = trim(s3_prefijo() . '/entregas/' . $entregaId . '/' . $base, '/');
        [$ok, $motivo] = s3_subir($archivo['tmp_name'], $clave, $mime);
        if (!$ok) return [false, $motivo];
        $almacen = 's3';
    } else {
        // Sin S3 configurado, el archivo se queda en disco.
        $dir = RUTA_SUBIDAS . DIRECTORY_SEPARATOR . 'entregas';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!move_uploaded_file($archivo['tmp_name'], $dir . DIRECTORY_SEPARATOR . $base)) {
            return [false, 'No se pudo guardar el archivo en el servidor.'];
        }
        $clave = 'entregas/' . $base;
        $almacen = 'local';
    }

    $id = insertar('entrega_adjuntos', [
        'entrega_id' => $entregaId,
        'fase_id'    => $faseId ?: null,
        'subido_por' => $usuarioId,
        'nombre'     => $nombre,
        'clave'      => $clave,
        'almacen'    => $almacen,
        'tipo'       => $mime,
        'extension'  => $ext,
        'bytes'      => $bytes,
    ]);
    auditar('adjunto_subido', 'entregas', $entregaId, $nombre . ' (' . adjunto_peso($bytes) . ')');

    return [true, fila('SELECT * FROM entrega_adjuntos WHERE id = ?', [$id]) ?? []];
}

/** Adjuntos de una entrega. Con $faseId se filtran los de esa fase. */
function adjuntos_de(int $entregaId, int|string|null $faseId = 'todos'): array {
    if ($faseId === 'todos') {
        return filas('SELECT * FROM entrega_adjuntos WHERE entrega_id = ? ORDER BY id', [$entregaId]);
    }
    if ($faseId === null) {
        return filas('SELECT * FROM entrega_adjuntos WHERE entrega_id = ? AND fase_id IS NULL ORDER BY id', [$entregaId]);
    }
    return filas('SELECT * FROM entrega_adjuntos WHERE entrega_id = ? AND fase_id = ? ORDER BY id',
                 [$entregaId, (int) $faseId]);
}

/** Agrupa los adjuntos de una entrega por fase, para pintarlos en su sitio. */
function adjuntos_por_fase(int $entregaId): array {
    $out = [];
    foreach (adjuntos_de($entregaId) as $a) {
        $out[$a['fase_id'] === null ? 0 : (int) $a['fase_id']][] = $a;
    }
    return $out;
}

/** Borra el archivo del almacenamiento y su ficha. */
function adjunto_borrar(array $adj): bool {
    if ($adj['almacen'] === 's3') {
        s3_borrar((string) $adj['clave']);
    } else {
        @unlink(RUTA_SUBIDAS . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $adj['clave']));
    }
    borrar('entrega_adjuntos', 'id = ?', [(int) $adj['id']]);
    auditar('adjunto_borrado', 'entregas', (int) $adj['entrega_id'], (string) $adj['nombre']);
    return true;
}

/**
 * Enlace de descarga. En S3 es un enlace firmado que caduca en minutos, así
 * que no sirve para compartir el trabajo de un estudiante fuera del portal.
 */
function adjunto_url(array $adj, int $segundos = 300): string {
    if ($adj['almacen'] === 's3') {
        return s3_url_temporal((string) $adj['clave'], $segundos, (string) $adj['nombre']);
    }
    return URL_SUBIDAS . '/' . $adj['clave'];
}

/**
 * ¿Puede este usuario ver el adjunto?
 *
 * El dueño de la entrega, el docente que la califica y la administración.
 * Nadie más: son trabajos escolares de menores de edad.
 */
function adjunto_visible_para(array $adj, array $u): bool {
    if (es('admin')) return true;
    $e = fila('SELECT e.estudiante_id, a.docente_id
                 FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
                WHERE e.id = ?', [(int) $adj['entrega_id']]);
    if (!$e) return false;
    if ((int) $u['id'] === (int) $e['estudiante_id']) return true;
    return es('docente') && (int) $u['id'] === (int) $e['docente_id'];
}

/** ¿Puede borrarlo? Solo quien lo subió, y mientras la entrega siga abierta. */
function adjunto_borrable_por(array $adj, array $u, bool $entregaBloqueada): bool {
    if (es('admin')) return true;
    if ($entregaBloqueada) return false;
    return (int) $adj['subido_por'] === (int) $u['id'];
}

/**
 * Pinta el bloque de adjuntos: la lista de lo ya subido y, si se puede
 * escribir, la zona para añadir más. El comportamiento lo pone
 * assets/js/adjuntos.js; sin JavaScript la lista se sigue viendo y
 * descargando, que es lo que de verdad importa.
 */
function bloque_adjuntos(int $entregaId, ?int $faseId, array $lista, bool $editable, array $u,
                         bool $bloqueada = false, string $titulo = ''): string {
    $html = '<div class="adj" data-adj'
          . ' data-entrega="' . $entregaId . '"'
          . ' data-fase="' . ($faseId ?: '') . '"'
          . ' data-csrf="' . h(csrf_token()) . '"'
          . ' data-url="' . url('portal/api/adjunto.php') . '">';

    if ($titulo !== '') $html .= '<p class="adj-titulo">' . h($titulo) . '</p>';

    $html .= '<ul class="adj-lista" data-adj-lista>';
    foreach ($lista as $a) {
        $puede = $editable && adjunto_borrable_por($a, $u, $bloqueada);
        $html .= '<li data-id="' . (int) $a['id'] . '">'
              . '<span class="adj-tipo">' . h(adjunto_etiqueta((string) $a['extension'])) . '</span>'
              . '<a href="' . url('portal/adjunto.php?id=' . (int) $a['id']) . '">' . h($a['nombre']) . '</a>'
              . '<span class="adj-peso">' . h(adjunto_peso((int) $a['bytes'])) . '</span>'
              . ($puede ? '<button type="button" class="adj-quitar" data-quitar="' . (int) $a['id']
                        . '" title="Quitar" aria-label="Quitar ' . h($a['nombre']) . '">&times;</button>' : '')
              . '</li>';
    }
    $html .= '</ul>';

    if ($editable) {
        $acepta = '.' . implode(',.', adjunto_extensiones());
        $html .= '<label class="adj-zona" data-adj-zona>'
              . '<input type="file" multiple hidden accept="' . h($acepta) . '" data-adj-campo>'
              . '<span class="adj-invita"><b>Elige archivos</b> o arrástralos aquí</span>'
              . '<span class="adj-formatos">' . h(implode(' · ', array_keys(ADJUNTO_FAMILIAS)))
              . ' — hasta ' . adjunto_peso(ADJUNTO_MAX_BYTES) . ' cada uno</span>'
              . '</label>';
    } elseif (!$lista) {
        $html .= '<p class="txt-sm txt-muted mb-0">Sin archivos adjuntos.</p>';
    }

    $html .= '<p class="adj-estado" data-adj-estado role="status"></p></div>';
    return $html;
}
