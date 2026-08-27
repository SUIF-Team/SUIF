/*==============================================================*/
/* SUIF — Reconstrucción de las ocho tablas que se perdieron     */
/*                                                              */
/* NO es parte de la instalación. Se ejecuta UNA vez sobre una  */
/* base a la que le faltan estas tablas, y no hace nada si ya   */
/* están. Puede volver a ejecutarse sin efectos secundarios.    */
/*==============================================================*/

/* Qué pasó: la suite de pruebas corrió con la configuración de Laravel
   cacheada. Con bootstrap/cache/config.php presente, phpunit.xml no logra
   imponer SQLite en memoria y las pruebas —que crean y borran su propio
   esquema con Schema::dropIfExists— se ejecutaron contra la base real.
   Alcanzaron a borrar ocho tablas antes de detenerse en una llave foránea:

       privilegio, privilegio_rol, tipo_documento, c_estado_pago,
       pago, estado_pago, evaluacion, referencia_bancaria

   Las otras 28 sobrevivieron, PERSONA y SOLICITUD incluidas.

   Este script NO usa drop en ninguna parte, a propósito: suif.sql sí lo
   hace y reconstruiría el esquema entero a costa de lo que quedó vivo.

   Deja el mismo esquema que habrían dejado suif.sql, suif_ajustes_esquema.sql,
   suif_referencias_bancarias.sql y suif_referencia_fecha_emision.sql juntos.
   Después de correrlo hay que ejecutar suif_catalogos.sql y
   suif_roles_administrativos.sql, que son idempotentes y reponen el resto. */

/*==============================================================*/
/* 1. Las tablas                                                */
/*==============================================================*/

create table if not exists PRIVILEGIO (
   PRIV_ID_PRIVILEGIO   SERIAL               not null,
   PRIV_PRIVILEGIO      VARCHAR(35)          not null,
   constraint PK_PRIVILEGIO primary key (PRIV_ID_PRIVILEGIO)
);

create table if not exists PRIVILEGIO_ROL (
   ROPR_ID_PRIVILEGIO_ROL SERIAL               not null,
   ROPR_ID_PRIVILEGIO   INT4                 not null,
   ROPR_ID_ROL          INT4                 not null,
   constraint PK_PRIVILEGIO_ROL primary key (ROPR_ID_PRIVILEGIO_ROL)
);

/* VARCHAR(60) y no (35): "Autorización de la publicación" no cabía, y
   suif_ajustes_esquema.sql ya la había ampliado. */
create table if not exists TIPO_DOCUMENTO (
   TIDO_ID_TIPO_DOCUMENTO SERIAL               not null,
   TIDO_TIPO_DOCUMENTO  VARCHAR(60)          not null,
   constraint PK_TIPO_DOCUMENTO primary key (TIDO_ID_TIPO_DOCUMENTO)
);

create table if not exists C_ESTADO_PAGO (
   ESPA_ID_C_ESTADO_PAGO SERIAL               not null,
   ESTA_ESTADO_PAGO     VARCHAR(15)          not null,
   constraint PK_C_ESTADO_PAGO primary key (ESPA_ID_C_ESTADO_PAGO)
);

/* Cuatro columnas nacen nulas: el renglón de PAGO se crea al asignar la
   referencia, y en ese momento todavía no hay datos fiscales, ni fecha de
   pago, ni comprobante. Es lo que hacía suif_referencias_bancarias.sql. */
create table if not exists PAGO (
   PAGO_ID_PAGO         SERIAL               not null,
   PAGO_ID_DATO_FISCAL  INT4                 null,
   PAGO_REFERENCIA_BANCARIA VARCHAR(20)      not null,
   PAGO_REFERENCIA_BANCARIA_PATH VARCHAR(200) not null,
   PAGO_MONTO_PAGADO    DECIMAL(10,4)        not null,
   PAGO_FECHA_PAGO      DATE                 null,
   PAGO_HORA_PAGO       TIME                 null,
   PAGO_COMPROBANTE_PATH VARCHAR(200)        null,
   PAGO_USO_CFDI        VARCHAR(25)          null,
   PAGO_NO_EMPLEADO     INT4                 null,
   constraint PK_PAGO primary key (PAGO_ID_PAGO)
);

create table if not exists ESTADO_PAGO (
   ESPA_ID_ESTADO_PAGO  SERIAL               not null,
   ESPA_ID_PAGO         INT4                 not null,
   ESPA_ID_C_ESTADO_PAGO INT4                not null,
   ESPA_FECHA           DATE                 null,
   ESPA_HORA            TIME                 not null,
   ESPA_COMENTARIO      TEXT                 null,
   constraint PK_ESTADO_PAGO primary key (ESPA_ID_ESTADO_PAGO)
);

/* EVAL_RESULTADO nace nulo: el resultado se captura después de aplicar. */
create table if not exists EVALUACION (
   EVAL_ID_EVALUACION   SERIAL               not null,
   GRUP_ID_GRUPO        INT4                 not null,
   EVAL_RESULTADO       INT4                 null,
   constraint PK_EVALUACION primary key (EVAL_ID_EVALUACION)
);

create table if not exists REFERENCIA_BANCARIA (
   REBA_ID_REFERENCIA_BANCARIA SERIAL        not null,
   REBA_ID_PAGO         INT4                 null,
   REBA_REFERENCIA      VARCHAR(20)          not null,
   REBA_PATH            VARCHAR(200)         null,
   REBA_MONTO           DECIMAL(10,4)        null,
   REBA_VIGENCIA        DATE                 null,
   REBA_FECHA_EMISION   DATE                 null,
   REBA_FECHA_CARGA     DATE                 not null,
   REBA_HORA_CARGA      TIME                 not null,
   REBA_FECHA_ASIGNACION DATE                null,
   REBA_HORA_ASIGNACION TIME                 null,
   constraint PK_REFERENCIA_BANCARIA primary key (REBA_ID_REFERENCIA_BANCARIA)
);

/*==============================================================*/
/* 2. Índices                                                   */
/*==============================================================*/

create unique index if not exists PRIVILEGIO_PK on PRIVILEGIO (PRIV_ID_PRIVILEGIO);
create unique index if not exists PRIVILEGIO_ROL_PK on PRIVILEGIO_ROL (ROPR_ID_PRIVILEGIO_ROL);
create index if not exists PRIVILEGIO_ROL_FK on PRIVILEGIO_ROL (ROPR_ID_PRIVILEGIO);
create index if not exists PRIVILEGIO_ROL2_FK on PRIVILEGIO_ROL (ROPR_ID_ROL);
create unique index if not exists TIPO_DOCUMENTO_PK on TIPO_DOCUMENTO (TIDO_ID_TIPO_DOCUMENTO);
create unique index if not exists C_ESTADO_PAGO_PK on C_ESTADO_PAGO (ESPA_ID_C_ESTADO_PAGO);
create unique index if not exists PAGO_PK on PAGO (PAGO_ID_PAGO);
create unique index if not exists ESTADO_PAGO_PK on ESTADO_PAGO (ESPA_ID_ESTADO_PAGO);
create unique index if not exists EVALUACION_PK on EVALUACION (EVAL_ID_EVALUACION);
create unique index if not exists REFERENCIA_BANCARIA_PK on REFERENCIA_BANCARIA (REBA_ID_REFERENCIA_BANCARIA);

/* El número de referencia no se repite dentro del catálogo, y una
   referencia pertenece a un solo pago. */
create unique index if not exists REFERENCIA_BANCARIA_AK on REFERENCIA_BANCARIA (REBA_REFERENCIA);
create unique index if not exists REFERENCIA_BANCARIA_PAGO_AK on REFERENCIA_BANCARIA (REBA_ID_PAGO);

/*==============================================================*/
/* 3. Catálogos que vivían en las tablas perdidas               */
/*==============================================================*/

/* Van ANTES de las llaves foráneas y no después: DOCUMENTO sobrevivió con
   sus renglones apuntando a estos identificadores, así que la llave hacia
   TIPO_DOCUMENTO no se puede validar mientras el catálogo esté vacío.

   Por lo mismo se conservan los números de suif_catalogos.sql: cambiarlos
   dejaría cada documento cargado con un tipo equivocado. */
INSERT INTO tipo_documento (tido_id_tipo_documento, tido_tipo_documento) VALUES
    (1, 'Solicitud firmada'),
    (2, 'Aceptación de notificaciones'),
    (3, 'Carta bajo protesta'),
    (4, 'Autorización de la publicación'),
    (5, 'CURP'),
    (6, 'Identificación oficial')
ON CONFLICT (tido_id_tipo_documento) DO NOTHING;

INSERT INTO c_estado_pago (espa_id_c_estado_pago, esta_estado_pago) VALUES
    (1, 'Pendiente'),
    (2, 'Completado'),
    (3, 'Declinado')
ON CONFLICT (espa_id_c_estado_pago) DO NOTHING;

/*==============================================================*/
/* 4. Punteros que quedaron colgando                            */
/*==============================================================*/

/* SOLICITUD y CERTIFICACION conservan los identificadores de pagos y
   evaluaciones que ya no existen. Hay que soltarlos antes de volver a poner
   las llaves foráneas, o el ALTER falla al validarlas.

   Las tres columnas admiten nulo, así que no se pierde ningún renglón: lo
   que se pierde es la liga, que de todas formas ya apuntaba al vacío. */
UPDATE solicitud SET soli_id_pago = NULL WHERE soli_id_pago IS NOT NULL;
UPDATE solicitud SET soli_id_evaluacion = NULL WHERE soli_id_evaluacion IS NOT NULL;
UPDATE certificacion SET eval_id_evaluacion = NULL WHERE eval_id_evaluacion IS NOT NULL;

/*==============================================================*/
/* 5. Llaves foráneas                                           */
/*==============================================================*/

/* Se conservan los nombres originales de suif.sql para que el esquema quede
   indistinguible del que había. Los de referencia_bancaria van en
   minúsculas porque así los declaró su propio script. */
DO $$
DECLARE
    llave RECORD;
BEGIN
    FOR llave IN
        SELECT * FROM (VALUES
            ('fk_privileg_privilegi_privileg', 'privilegio_rol', 'ropr_id_privilegio', 'privilegio', 'priv_id_privilegio'),
            ('fk_privileg_privilegi_rol',      'privilegio_rol', 'ropr_id_rol',        'rol',        'rol_id_rol'),
            ('fk_document_relations_tipo_doc', 'documento',      'tido_id_tipo_documento', 'tipo_documento', 'tido_id_tipo_documento'),
            ('fk_estado_p_estado_pa_pago',     'estado_pago',    'espa_id_pago',       'pago',       'pago_id_pago'),
            ('fk_estado_p_estado_pa_c_estado', 'estado_pago',    'espa_id_c_estado_pago', 'c_estado_pago', 'espa_id_c_estado_pago'),
            ('fk_pago_relations_dato_fis',     'pago',           'pago_id_dato_fiscal', 'dato_fiscal', 'dafi_id_dato_fiscal'),
            ('fk_evaluaci_reference_grupo',    'evaluacion',     'grup_id_grupo',      'grupo',      'grup_id_grupo'),
            ('fk_solicitu_relations_pago',     'solicitud',      'soli_id_pago',       'pago',       'pago_id_pago'),
            ('fk_solicitu_relations_evaluaci', 'solicitud',      'soli_id_evaluacion', 'evaluacion', 'eval_id_evaluacion'),
            ('fk_certific_reference_evaluaci', 'certificacion',  'eval_id_evaluacion', 'evaluacion', 'eval_id_evaluacion'),
            ('fk_referencia_bancaria_pago',    'referencia_bancaria', 'reba_id_pago',  'pago',       'pago_id_pago')
        ) AS f(nombre, tabla, columna, destino, columna_destino)
    LOOP
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = llave.nombre) THEN
            EXECUTE format(
                'ALTER TABLE %I ADD CONSTRAINT %I FOREIGN KEY (%I) REFERENCES %I (%I) ON DELETE RESTRICT ON UPDATE RESTRICT',
                llave.tabla, llave.nombre, llave.columna, llave.destino, llave.columna_destino
            );
        END IF;
    END LOOP;
END $$;

/* Una evaluación por grupo: es contra ella que se cuenta el cupo. */
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_evaluacion_grupo') THEN
        ALTER TABLE evaluacion ADD CONSTRAINT uq_evaluacion_grupo UNIQUE (grup_id_grupo);
    END IF;
END $$;

/*==============================================================*/
/* 6. La programación de las sedes                              */
/*==============================================================*/

/* GRUPO sobrevivió con sus fechas y horarios, pero se quedó sin la
   EVALUACION que le da cupo, y sin ella las dos pantallas de sedes no
   funcionan. Se repone una por grupo, que es la correspondencia que exige
   uq_evaluacion_grupo.

   Lo que no vuelve es quién había elegido cada horario: eso vivía en
   SOLICITUD.SOLI_ID_EVALUACION y quedó en nulo arriba. Las personas que ya
   habían seleccionado sede tendrán que elegirla de nuevo. */
INSERT INTO evaluacion (grup_id_grupo, eval_resultado)
SELECT g.grup_id_grupo, NULL
  FROM grupo AS g
 WHERE NOT EXISTS (
     SELECT 1 FROM evaluacion AS e WHERE e.grup_id_grupo = g.grup_id_grupo
 );

/* Una sede ofrece cupo mientras alguna de sus aplicaciones tenga lugares. */
UPDATE sede AS s
SET sede_estado = EXISTS (
    SELECT 1
    FROM grupo AS g
    JOIN evaluacion AS e ON e.grup_id_grupo = g.grup_id_grupo
    WHERE g.sede_id_sede = s.sede_id_sede
      AND s.sede_cupo > (
          SELECT COUNT(*)
          FROM solicitud AS so
          WHERE so.soli_id_evaluacion = e.eval_id_evaluacion
      )
);

/*==============================================================*/
/* 7. Secuencias                                                */
/*==============================================================*/

/* Las tablas nacen vacías o con identificadores explícitos, así que la
   secuencia tiene que quedar donde corresponde o el primer alta choca. */
DO $$
DECLARE
    columna RECORD;
    maximo BIGINT;
BEGIN
    FOR columna IN
        SELECT
            table_name,
            column_name,
            pg_get_serial_sequence(format('%I.%I', table_schema, table_name), column_name) AS secuencia
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name IN (
              'privilegio', 'privilegio_rol', 'tipo_documento', 'c_estado_pago',
              'pago', 'estado_pago', 'evaluacion', 'referencia_bancaria'
          )
          AND column_default LIKE 'nextval(%'
    LOOP
        IF columna.secuencia IS NULL THEN
            CONTINUE;
        END IF;

        EXECUTE format('SELECT COALESCE(MAX(%I), 0) FROM %I', columna.column_name, columna.table_name)
           INTO maximo;

        IF maximo > 0 THEN
            PERFORM setval(columna.secuencia, maximo, true);
        ELSE
            PERFORM setval(columna.secuencia, 1, false);
        END IF;
    END LOOP;
END $$;
