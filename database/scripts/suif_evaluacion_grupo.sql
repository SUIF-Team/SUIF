/*==============================================================*/
/* SUIF — EVALUACION apunta a GRUPO                             */
/* Complemento de suif_ajustes_esquema.sql. Ejecutar DESPUÉS.   */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* El esquema del 11/08/2026 movió la programación del examen (sede, fechas y
   horas) de EVALUACION a GRUPO. suif.sql ya crea EVALUACION con
   GRUP_ID_GRUPO, y suif_ajustes_esquema.sql se limita a añadir la restricción
   uq_evaluacion_grupo dando por hecho que la columna existe.

   Queda sin cubrir el caso intermedio: una base cuyo GRUPO ya se creó pero
   cuyo EVALUACION conservó EVAL_ID_SEDE junto con EVAL_FECHA_INICIO,
   EVAL_HORA_INICIO, EVAL_FECHA_FIN y EVAL_HORA_FIN. Ahí
   uq_evaluacion_grupo aborta por columna inexistente y toda la gestión de
   sedes —la del administrador y la del participante— responde 500, porque
   ambas recorren sede -> grupo -> evaluacion.

   Este script traspasa esa programación a GRUPO y deja EVALUACION como la
   espera la aplicación. No borra columnas ni renglones: las columnas
   anteriores sólo dejan de ser obligatorias para que las altas nuevas, que
   ya no las llenan, sean válidas. */

/* Las altas de este script usan las secuencias; si la base se cargó con IDs
   explícitos pueden haber quedado atrás y provocar llave duplicada. */
SELECT setval(
    pg_get_serial_sequence('grupo', 'grup_id_grupo'),
    COALESCE((SELECT MAX(grup_id_grupo) FROM grupo), 0) + 1,
    false
);

SELECT setval(
    pg_get_serial_sequence('evaluacion', 'eval_id_evaluacion'),
    COALESCE((SELECT MAX(eval_id_evaluacion) FROM evaluacion), 0) + 1,
    false
);

/* La columna que el esquema del 11/08/2026 espera en EVALUACION. */
ALTER TABLE evaluacion ADD COLUMN IF NOT EXISTS grup_id_grupo INT4;

/* uq_grupo_sede limitaba cada sede a una sola programación. Se retira antes
   del traspaso porque una sede con varias evaluaciones históricas necesita un
   GRUPO por cada una. Es la misma línea de suif_grupos_multiples.sql. */
ALTER TABLE grupo DROP CONSTRAINT IF EXISTS uq_grupo_sede;

/* Traspaso. Cada EVALUACION que todavía guarda su propia programación recibe
   el GRUPO equivalente: se reutiliza uno que coincida en sede, fechas y horas
   y que aún no tenga evaluación, y si no existe se crea. La correspondencia
   queda uno a uno, que es lo que exige uq_evaluacion_grupo. */
DO $$
DECLARE
    fila     RECORD;
    id_grupo INT4;
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'evaluacion'
          AND column_name = 'eval_id_sede'
    ) THEN
        RAISE NOTICE 'EVALUACION ya no guarda la programación: no hay nada que traspasar.';
        RETURN;
    END IF;

    FOR fila IN
        EXECUTE 'SELECT eval_id_evaluacion,
                        eval_id_sede,
                        eval_fecha_inicio,
                        eval_hora_inicio,
                        eval_fecha_fin,
                        eval_hora_fin
                   FROM evaluacion
                  WHERE grup_id_grupo IS NULL
                    AND eval_id_sede IS NOT NULL
                  ORDER BY eval_id_evaluacion'
    LOOP
        id_grupo := NULL;

        SELECT g.grup_id_grupo
          INTO id_grupo
          FROM grupo AS g
         WHERE g.sede_id_sede      = fila.eval_id_sede
           AND g.grup_fecha_inicio = fila.eval_fecha_inicio
           AND g.grup_hora_inicio  = fila.eval_hora_inicio
           AND g.grup_fecha_fin    = fila.eval_fecha_fin
           AND g.grup_hora_fin     = fila.eval_hora_fin
           AND NOT EXISTS (
               SELECT 1
               FROM evaluacion AS e
               WHERE e.grup_id_grupo = g.grup_id_grupo
           )
         LIMIT 1;

        IF id_grupo IS NULL THEN
            INSERT INTO grupo (
                sede_id_sede,
                grup_fecha_inicio,
                grup_hora_inicio,
                grup_fecha_fin,
                grup_hora_fin
            ) VALUES (
                fila.eval_id_sede,
                fila.eval_fecha_inicio,
                fila.eval_hora_inicio,
                fila.eval_fecha_fin,
                fila.eval_hora_fin
            )
            RETURNING grup_id_grupo INTO id_grupo;
        END IF;

        UPDATE evaluacion
           SET grup_id_grupo = id_grupo
         WHERE eval_id_evaluacion = fila.eval_id_evaluacion;
    END LOOP;
END $$;

/* Las altas nuevas sólo llenan GRUP_ID_GRUPO y EVAL_RESULTADO. Las columnas
   del esquema anterior se conservan con sus datos, pero dejan de ser
   obligatorias para no rechazar esas altas. */
DO $$
DECLARE
    columna TEXT;
BEGIN
    FOREACH columna IN ARRAY ARRAY[
        'eval_id_sede',
        'eval_fecha_inicio',
        'eval_hora_inicio',
        'eval_fecha_fin',
        'eval_hora_fin'
    ]
    LOOP
        IF EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'evaluacion'
              AND column_name = columna
        ) THEN
            EXECUTE format('ALTER TABLE evaluacion ALTER COLUMN %I DROP NOT NULL', columna);
        END IF;
    END LOOP;
END $$;

/* El resultado se captura después de programar la evaluación. Lo repite
   suif_ajustes_esquema.sql, que en esta base no llegó a terminar. */
ALTER TABLE evaluacion ALTER COLUMN eval_resultado DROP NOT NULL;

/* Cada evaluación pertenece a un grupo existente. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_evaluacion_grupo'
    ) THEN
        ALTER TABLE evaluacion
            ADD CONSTRAINT fk_evaluacion_grupo
            FOREIGN KEY (grup_id_grupo) REFERENCES grupo (grup_id_grupo);
    END IF;
END $$;

/* Una evaluación por grupo: el cupo de la sede se cuenta contra ella. Es el
   bloque en el que abortó suif_ajustes_esquema.sql. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'uq_evaluacion_grupo'
    ) THEN
        ALTER TABLE evaluacion
            ADD CONSTRAINT uq_evaluacion_grupo UNIQUE (grup_id_grupo);
    END IF;
END $$;

/* Sólo se exige el grupo cuando ninguna evaluación quedó sin traspasar: una
   fila huérfana debe revisarse a mano, no bloquear el despliegue. */
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM evaluacion WHERE grup_id_grupo IS NULL) THEN
        ALTER TABLE evaluacion ALTER COLUMN grup_id_grupo SET NOT NULL;
    ELSE
        RAISE NOTICE 'Hay evaluaciones sin grupo: GRUP_ID_GRUPO se deja opcional.';
    END IF;
END $$;

/* Las consultas del catálogo recorren los grupos de cada sede ordenados por
   fecha y hora de inicio. Lo repite suif_grupos_multiples.sql. */
CREATE INDEX IF NOT EXISTS idx_grupo_sede_inicio
    ON grupo (sede_id_sede, grup_fecha_inicio, grup_hora_inicio);

/* Estado inicial consistente: las sedes sin programación o llenas no ofrecen
   cupo. SEDE_CUPO es el aforo de cada aplicación, no el total de la sede. */
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

/* Comprobación: evaluaciones_sin_grupo debe quedar en 0. */
SELECT
    (SELECT COUNT(*) FROM grupo)                                      AS grupos,
    (SELECT COUNT(*) FROM evaluacion)                                 AS evaluaciones,
    (SELECT COUNT(*) FROM evaluacion WHERE grup_id_grupo IS NULL)     AS evaluaciones_sin_grupo,
    (SELECT COUNT(*) FROM sede)                                       AS sedes;
