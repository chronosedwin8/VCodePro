-- ============================================================================
-- VCodePro · Portal académico
-- Esquema de base de datos — MySQL 8.0 / InnoDB / utf8mb4
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- colegios --
CREATE TABLE IF NOT EXISTS colegios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(160) NOT NULL,
  slug          VARCHAR(160) NOT NULL UNIQUE,
  nit           VARCHAR(60)  DEFAULT NULL,
  pais          VARCHAR(80)  DEFAULT 'Colombia',
  ciudad        VARCHAR(80)  DEFAULT NULL,
  direccion     VARCHAR(200) DEFAULT NULL,
  telefono      VARCHAR(40)  DEFAULT NULL,
  email         VARCHAR(160) DEFAULT NULL,
  programa_ib   VARCHAR(120) DEFAULT 'PAI y Programa del Diploma',
  logo          VARCHAR(200) DEFAULT NULL,
  estado        ENUM('activo','suspendido','prueba') NOT NULL DEFAULT 'activo',
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- usuarios --
CREATE TABLE IF NOT EXISTS usuarios (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  colegio_id      INT UNSIGNED DEFAULT NULL,
  nombre          VARCHAR(80)  NOT NULL,
  apellidos       VARCHAR(80)  NOT NULL DEFAULT '',
  email           VARCHAR(160) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  rol             ENUM('admin','docente','estudiante','cliente') NOT NULL DEFAULT 'estudiante',
  estado          ENUM('activo','pendiente','suspendido') NOT NULL DEFAULT 'activo',
  documento       VARCHAR(40)  DEFAULT NULL,
  telefono        VARCHAR(40)  DEFAULT NULL,
  avatar          VARCHAR(200) DEFAULT NULL,
  cargo           VARCHAR(120) DEFAULT NULL,
  fecha_nacimiento DATE        DEFAULT NULL,
  tema            ENUM('light','dark') NOT NULL DEFAULT 'dark',
  codigo_externo  VARCHAR(40)  DEFAULT NULL,
  origen          VARCHAR(20)  NOT NULL DEFAULT 'local',
  ultimo_acceso   DATETIME     DEFAULT NULL,
  intentos        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME     DEFAULT NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_usuarios_rol (rol),
  INDEX idx_usuarios_colegio (colegio_id),
  INDEX idx_usuarios_externo (codigo_externo),
  CONSTRAINT fk_usuarios_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recordatorios_sesion (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  selector    CHAR(24) NOT NULL UNIQUE,
  validador   CHAR(64) NOT NULL,
  expira_en   DATETIME NOT NULL,
  CONSTRAINT fk_rec_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recuperaciones (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expira_en   DATETIME NOT NULL,
  usado       TINYINT(1) NOT NULL DEFAULT 0,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rec_token (token_hash),
  CONSTRAINT fk_recu_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- niveles --
CREATE TABLE IF NOT EXISTS niveles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo       VARCHAR(20) NOT NULL UNIQUE,
  nombre       VARCHAR(80) NOT NULL,
  grado        VARCHAR(20) NOT NULL,
  programa_ib  VARCHAR(80) NOT NULL,
  asignatura   VARCHAR(120) NOT NULL,
  edad         VARCHAR(30)  NOT NULL,
  descripcion  TEXT,
  contenidos   TEXT,
  proyecto_insignia VARCHAR(200),
  lenguajes    VARCHAR(160) DEFAULT NULL,
  color        VARCHAR(20)  DEFAULT '#0078d4',
  orden        TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- actividades --
CREATE TABLE IF NOT EXISTS actividades (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nivel_id       INT UNSIGNED NOT NULL,
  codigo         VARCHAR(24) NOT NULL UNIQUE,
  titulo         VARCHAR(200) NOT NULL,
  resumen        VARCHAR(400) NOT NULL,
  descripcion    MEDIUMTEXT NOT NULL,
  pregunta_indagacion VARCHAR(400) DEFAULT NULL,
  criterios_ib   VARCHAR(40)  NOT NULL DEFAULT 'A,B,C,D',
  contexto_global VARCHAR(120) NOT NULL,
  concepto_clave  VARCHAR(120) DEFAULT NULL,
  perfil_ib      VARCHAR(240) DEFAULT NULL,
  atl            VARCHAR(240) DEFAULT NULL,
  objetivos      TEXT,
  entregables    TEXT,
  lenguaje       VARCHAR(60)  NOT NULL DEFAULT 'Python',
  dificultad     ENUM('inicial','intermedio','avanzado') NOT NULL DEFAULT 'inicial',
  sesiones       TINYINT UNSIGNED NOT NULL DEFAULT 4,
  horas          DECIMAL(4,1) NOT NULL DEFAULT 4.0,
  ia_sugerida    TINYINT(1) NOT NULL DEFAULT 1,
  codigo_inicial MEDIUMTEXT,
  orden          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  publicada      TINYINT(1) NOT NULL DEFAULT 1,
  creado_por     INT UNSIGNED DEFAULT NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_act_nivel (nivel_id),
  CONSTRAINT fk_act_nivel FOREIGN KEY (nivel_id) REFERENCES niveles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actividad_fases (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actividad_id  INT UNSIGNED NOT NULL,
  fase          ENUM('indagar','desarrollar','crear','evaluar') NOT NULL,
  titulo        VARCHAR(160) NOT NULL,
  instrucciones TEXT NOT NULL,
  entregable    VARCHAR(300) NOT NULL,
  minutos       SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  orden         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_fase_act (actividad_id),
  CONSTRAINT fk_fase_act FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actividad_recursos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actividad_id  INT UNSIGNED NOT NULL,
  tipo          ENUM('lectura','video','plantilla','dataset','enlace','codigo') NOT NULL DEFAULT 'enlace',
  titulo        VARCHAR(200) NOT NULL,
  url           VARCHAR(400) DEFAULT NULL,
  detalle       VARCHAR(400) DEFAULT NULL,
  INDEX idx_rec_act (actividad_id),
  CONSTRAINT fk_rec_act FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rubrica_criterios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actividad_id  INT UNSIGNED NOT NULL,
  criterio      CHAR(1) NOT NULL,
  nombre        VARCHAR(120) NOT NULL,
  descriptor_12 VARCHAR(400) NOT NULL,
  descriptor_34 VARCHAR(400) NOT NULL,
  descriptor_56 VARCHAR(400) NOT NULL,
  descriptor_78 VARCHAR(400) NOT NULL,
  maximo        TINYINT UNSIGNED NOT NULL DEFAULT 8,
  INDEX idx_rub_act (actividad_id),
  CONSTRAINT fk_rub_act FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ grupos --
CREATE TABLE IF NOT EXISTS grupos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  colegio_id  INT UNSIGNED DEFAULT NULL,
  docente_id  INT UNSIGNED NOT NULL,
  nivel_id    INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  anio        SMALLINT UNSIGNED NOT NULL,
  periodo     VARCHAR(30) DEFAULT NULL,
  codigo      CHAR(8) NOT NULL UNIQUE,
  curso_externo VARCHAR(60) DEFAULT NULL,
  jornada     VARCHAR(40) DEFAULT NULL,
  modo_examen TINYINT(1) NOT NULL DEFAULT 0,
  ia_permitida TINYINT(1) NOT NULL DEFAULT 1,
  estado      ENUM('activo','archivado') NOT NULL DEFAULT 'activo',
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_grupo_doc (docente_id),
  CONSTRAINT fk_grupo_doc FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_grupo_nivel FOREIGN KEY (nivel_id) REFERENCES niveles(id),
  CONSTRAINT fk_grupo_col FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grupo_estudiantes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grupo_id     INT UNSIGNED NOT NULL,
  estudiante_id INT UNSIGNED NOT NULL,
  estado       ENUM('activo','retirado','pendiente') NOT NULL DEFAULT 'activo',
  inscrito_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grupo_est (grupo_id, estudiante_id),
  CONSTRAINT fk_ge_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
  CONSTRAINT fk_ge_est FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ asignaciones --
CREATE TABLE IF NOT EXISTS asignaciones (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grupo_id      INT UNSIGNED NOT NULL,
  actividad_id  INT UNSIGNED NOT NULL,
  docente_id    INT UNSIGNED NOT NULL,
  fecha_inicio  DATE NOT NULL,
  fecha_entrega DATE NOT NULL,
  instrucciones TEXT,
  peso          DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  ia_permitida  TINYINT(1) NOT NULL DEFAULT 1,
  estado        ENUM('borrador','abierta','cerrada') NOT NULL DEFAULT 'abierta',
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_asig (grupo_id, actividad_id),
  CONSTRAINT fk_asig_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
  CONSTRAINT fk_asig_act FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
  CONSTRAINT fk_asig_doc FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entregas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asignacion_id INT UNSIGNED NOT NULL,
  estudiante_id INT UNSIGNED NOT NULL,
  estado        ENUM('pendiente','en_progreso','entregada','revisada','rehacer') NOT NULL DEFAULT 'pendiente',
  progreso      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  texto         MEDIUMTEXT,
  url_repo      VARCHAR(300) DEFAULT NULL,
  archivo       VARCHAR(300) DEFAULT NULL,
  archivo_nombre VARCHAR(200) DEFAULT NULL,
  uso_ia        TEXT,
  intento       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  entregado_en  DATETIME DEFAULT NULL,
  nota_final    DECIMAL(5,2) DEFAULT NULL,
  nota_letra    VARCHAR(10) DEFAULT NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_entrega (asignacion_id, estudiante_id),
  CONSTRAINT fk_ent_asig FOREIGN KEY (asignacion_id) REFERENCES asignaciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_ent_est FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_fases (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrega_id  INT UNSIGNED NOT NULL,
  fase_id     INT UNSIGNED NOT NULL,
  contenido   MEDIUMTEXT,
  completada  TINYINT(1) NOT NULL DEFAULT 0,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ef (entrega_id, fase_id),
  CONSTRAINT fk_ef_ent FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE,
  CONSTRAINT fk_ef_fase FOREIGN KEY (fase_id) REFERENCES actividad_fases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calificaciones (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrega_id  INT UNSIGNED NOT NULL,
  criterio_id INT UNSIGNED NOT NULL,
  docente_id  INT UNSIGNED NOT NULL,
  puntaje     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  comentario  TEXT,
  fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_calif (entrega_id, criterio_id),
  CONSTRAINT fk_cal_ent FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE,
  CONSTRAINT fk_cal_cri FOREIGN KEY (criterio_id) REFERENCES rubrica_criterios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comentarios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrega_id  INT UNSIGNED NOT NULL,
  autor_id    INT UNSIGNED NOT NULL,
  mensaje     TEXT NOT NULL,
  privado     TINYINT(1) NOT NULL DEFAULT 0,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_com_ent (entrega_id),
  CONSTRAINT fk_com_ent FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE,
  CONSTRAINT fk_com_aut FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bitacora (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estudiante_id INT UNSIGNED NOT NULL,
  entrega_id    INT UNSIGNED DEFAULT NULL,
  fase          ENUM('indagar','desarrollar','crear','evaluar','general') NOT NULL DEFAULT 'general',
  titulo        VARCHAR(200) NOT NULL,
  contenido     MEDIUMTEXT NOT NULL,
  minutos       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bit_est (estudiante_id),
  CONSTRAINT fk_bit_est FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_bit_ent FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ gamificación --
CREATE TABLE IF NOT EXISTS insignias (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo      VARCHAR(40) NOT NULL UNIQUE,
  nombre      VARCHAR(120) NOT NULL,
  descripcion VARCHAR(300) NOT NULL,
  icono       VARCHAR(10) NOT NULL DEFAULT '*',
  puntos      SMALLINT UNSIGNED NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_insignias (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  insignia_id INT UNSIGNED NOT NULL,
  obtenida_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ui (usuario_id, insignia_id),
  CONSTRAINT fk_ui_u FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_ui_i FOREIGN KEY (insignia_id) REFERENCES insignias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- comercial --
CREATE TABLE IF NOT EXISTS licencias (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  colegio_id   INT UNSIGNED DEFAULT NULL,
  cliente_id   INT UNSIGNED DEFAULT NULL,
  clave        VARCHAR(30) NOT NULL UNIQUE,
  plan         ENUM('personal','escuela','sitio') NOT NULL DEFAULT 'escuela',
  cupo         SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  emitida_en   DATE NOT NULL,
  vence_en     DATE NOT NULL,
  estado       ENUM('activa','vencida','suspendida') NOT NULL DEFAULT 'activa',
  notas        VARCHAR(300) DEFAULT NULL,
  creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lic_col FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE SET NULL,
  CONSTRAINT fk_lic_cli FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS licencia_puestos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  licencia_id INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  email       VARCHAR(160) NOT NULL,
  dispositivo VARCHAR(120) DEFAULT NULL,
  estado      ENUM('activo','revocado') NOT NULL DEFAULT 'activo',
  asignado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pue_lic (licencia_id),
  CONSTRAINT fk_pue_lic FOREIGN KEY (licencia_id) REFERENCES licencias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facturas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT UNSIGNED NOT NULL,
  licencia_id INT UNSIGNED DEFAULT NULL,
  numero      VARCHAR(30) NOT NULL UNIQUE,
  concepto    VARCHAR(200) NOT NULL,
  monto       DECIMAL(14,2) NOT NULL,
  moneda      CHAR(3) NOT NULL DEFAULT 'COP',
  estado      ENUM('pagada','pendiente','vencida','anulada') NOT NULL DEFAULT 'pendiente',
  emitida_en  DATE NOT NULL,
  vence_en    DATE NOT NULL,
  CONSTRAINT fk_fac_cli FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_fac_lic FOREIGN KEY (licencia_id) REFERENCES licencias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT UNSIGNED NOT NULL,
  asunto      VARCHAR(200) NOT NULL,
  categoria   ENUM('tecnico','licencias','pedagogico','facturacion','otro') NOT NULL DEFAULT 'tecnico',
  prioridad   ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  estado      ENUM('abierto','en_proceso','resuelto','cerrado') NOT NULL DEFAULT 'abierto',
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tic_cli FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_mensajes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT UNSIGNED NOT NULL,
  autor_id   INT UNSIGNED NOT NULL,
  mensaje    TEXT NOT NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tm_t (ticket_id),
  CONSTRAINT fk_tm_t FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_a FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- sistema --
CREATE TABLE IF NOT EXISTS notificaciones (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  tipo       VARCHAR(40) NOT NULL DEFAULT 'info',
  titulo     VARCHAR(200) NOT NULL,
  mensaje    VARCHAR(400) NOT NULL,
  url        VARCHAR(300) DEFAULT NULL,
  leida      TINYINT(1) NOT NULL DEFAULT 0,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_not_u (usuario_id, leida),
  CONSTRAINT fk_not_u FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED DEFAULT NULL,
  accion     VARCHAR(80) NOT NULL,
  entidad    VARCHAR(60) DEFAULT NULL,
  entidad_id INT UNSIGNED DEFAULT NULL,
  detalle    VARCHAR(400) DEFAULT NULL,
  ip         VARCHAR(45) DEFAULT NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_aud_u (usuario_id),
  CONSTRAINT fk_aud_u FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ajustes (
  clave  VARCHAR(60) PRIMARY KEY,
  valor  TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes_contacto (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(120) NOT NULL,
  email     VARCHAR(160) NOT NULL,
  colegio   VARCHAR(160) DEFAULT NULL,
  telefono  VARCHAR(40) DEFAULT NULL,
  asunto    VARCHAR(120) DEFAULT NULL,
  mensaje   TEXT NOT NULL,
  estado    ENUM('nuevo','atendido','archivado') NOT NULL DEFAULT 'nuevo',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
