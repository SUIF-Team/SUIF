/*==============================================================*/
/* SUIF — Ajustes al esquema base                               */
/* Complemento de suif.sql. Ejecutar DESPUÉS del esquema.       */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* Un hash de contraseña de Laravel mide 60 caracteres. */
ALTER TABLE usuario ALTER COLUMN usua_clave_acceso TYPE VARCHAR(255);

/* "Autorización de la publicación" son 30 caracteres. */
ALTER TABLE tipo_documento ALTER COLUMN tido_tipo_documento TYPE VARCHAR(60);

/* Nombre original del archivo que sube la persona. */
ALTER TABLE documento ALTER COLUMN docu_nombre TYPE VARCHAR(150);

/* En 35 caracteres no cabe una explicación de rechazo útil. */
ALTER TABLE estado_solicitud ALTER COLUMN esso_motivo_rechazo TYPE VARCHAR(255);

/* Cargar un documento no requiere comentario del revisor. */
ALTER TABLE estado_documento ALTER COLUMN esdo_comentarios DROP NOT NULL;

/* "Documentación en revisión" no cabe en 15 caracteres. */
ALTER TABLE c_estado_solicitud ALTER COLUMN esso_estatus_solicitud TYPE VARCHAR(40);

/* Nombre visible de la sede. El backfill sólo cubre filas preexistentes. */
ALTER TABLE sede ADD COLUMN IF NOT EXISTS sede_nombre VARCHAR(150);

UPDATE sede
SET sede_nombre = LEFT(sede_direccion, 150)
WHERE sede_nombre IS NULL;

ALTER TABLE sede ALTER COLUMN sede_nombre SET NOT NULL;

/* El resultado se captura después de programar la evaluación. */
ALTER TABLE evaluacion ALTER COLUMN eval_resultado DROP NOT NULL;

/* La primera versión administra una sola programación por sede. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'uq_evaluacion_sede'
    ) THEN
        ALTER TABLE evaluacion
            ADD CONSTRAINT uq_evaluacion_sede UNIQUE (eval_id_sede);
    END IF;
END $$;

/* Estado inicial consistente: las sedes sin programación o llenas no ofrecen cupo. */
UPDATE sede AS s
SET sede_estado = EXISTS (
    SELECT 1
    FROM evaluacion AS e
    WHERE e.eval_id_sede = s.sede_id_sede
      AND s.sede_cupo > (
          SELECT COUNT(*)
          FROM solicitud AS so
          WHERE so.soli_id_evaluacion = e.eval_id_evaluacion
      )
);

/* Los datos históricos pueden usar IDs explícitos; se evita que una alta
   posterior reutilice un identificador que ya existe. */
DO $$
DECLARE
    columna RECORD;
    maximo BIGINT;
BEGIN
    FOR columna IN
        SELECT
            table_schema,
            table_name,
            column_name,
            pg_get_serial_sequence(
                format('%I.%I', table_schema, table_name),
                column_name
            ) AS secuencia
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND column_default LIKE 'nextval(%'
    LOOP
        IF columna.secuencia IS NULL THEN
            CONTINUE;
        END IF;

        EXECUTE format(
            'SELECT COALESCE(MAX(%I), 0) FROM %I.%I',
            columna.column_name,
            columna.table_schema,
            columna.table_name
        ) INTO maximo;

        IF maximo > 0 THEN
            PERFORM setval(columna.secuencia, maximo, true);
        ELSE
            PERFORM setval(columna.secuencia, 1, false);
        END IF;
    END LOOP;
END $$;

/* Un solo documento por tipo en cada solicitud. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'uq_documento_solicitud_tipo'
    ) THEN
        ALTER TABLE documento
            ADD CONSTRAINT uq_documento_solicitud_tipo
            UNIQUE (soli_id_solicitud, tido_id_tipo_documento);
    END IF;
END $$;
