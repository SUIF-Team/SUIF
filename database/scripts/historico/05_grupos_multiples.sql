/*==============================================================*/
/* SUIF — Varias aplicaciones de examen por sede                */
/* Complemento de suif_ajustes_esquema.sql. Ejecutar DESPUÉS.   */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* La primera versión limitaba cada sede a una sola programación con la
   restricción uq_grupo_sede. Una sede aplica el examen una o más veces, y
   cada aplicación es un GRUPO con su propio horario, así que la restricción
   se retira. uq_evaluacion_grupo se conserva: cada aplicación sigue teniendo
   exactamente una evaluación, y el cupo de la sede se cuenta contra ella. */
ALTER TABLE grupo DROP CONSTRAINT IF EXISTS uq_grupo_sede;

/* Las consultas del catálogo recorren los grupos de cada sede ordenados por
   fecha y hora de inicio. */
CREATE INDEX IF NOT EXISTS idx_grupo_sede_inicio
    ON grupo (sede_id_sede, grup_fecha_inicio, grup_hora_inicio);

/* SEDE_CUPO es el aforo de cada aplicación, no el total de la sede: la sala
   admite el mismo número de personas en cada sesión. Una sede ofrece cupo
   mientras al menos una de sus aplicaciones tenga lugares libres. */
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
