-- ==============================================================
-- SUIF — Módulo de convocatorias
-- Complemento de suif.sql. Ejecutar DESPUÉS de suif_catalogos.sql
-- y de suif_roles_administrativos.sql.
-- Puede volver a ejecutarse sin duplicar ni destruir nada.
-- ==============================================================
--
-- OBLIGATORIO antes de publicar el módulo administrativo de
-- convocatorias. En producción C_ESTADO_CONVOCATORIA nace vacía:
-- suif.sql la crea y suif_catalogos.sql —que sí siembra
-- c_estado_documento y c_estado_solicitud— la salta. Sin esto la
-- bitácora de estados no puede registrar nada y nadie tiene el
-- privilegio que abre la pantalla.
--
-- Los comentarios van con "--" y no con bloques: PostgreSQL anida
-- los /* */ y un comentario mal cerrado deja el resto del archivo
-- del lado equivocado. Ya rompió suif_evaluacion_grupo.sql y el
-- primer UPDATE de suif_reconstruye_tablas_perdidas.sql.

-- --------------------------------------------------------------
-- 1. Alinear las secuencias ANTES de insertar
--
-- Las dos tablas de estado nacen vacías y las de privilegios se
-- cargan con identificadores explícitos desde otros scripts, así
-- que la secuencia puede quedar atrás y un alta sin id chocaría
-- con una llave existente.
--
-- El COALESCE y el tercer argumento cubren la tabla vacía: setval
-- es STRICT y con un MAX nulo no haría nada, dejando la secuencia
-- atrás.
-- --------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('c_estado_convocatoria', 'esco_id_c_estado_convocatoria'),
              COALESCE((SELECT MAX(esco_id_c_estado_convocatoria) FROM c_estado_convocatoria), 1),
              (SELECT COUNT(*) > 0 FROM c_estado_convocatoria));

SELECT setval(pg_get_serial_sequence('estado_convocatoria', 'esco_id_estado_convocatoria'),
              COALESCE((SELECT MAX(esco_id_estado_convocatoria) FROM estado_convocatoria), 1),
              (SELECT COUNT(*) > 0 FROM estado_convocatoria));

SELECT setval(pg_get_serial_sequence('privilegio', 'priv_id_privilegio'),
              COALESCE((SELECT MAX(priv_id_privilegio) FROM privilegio), 1),
              (SELECT COUNT(*) > 0 FROM privilegio));

SELECT setval(pg_get_serial_sequence('privilegio_rol', 'ropr_id_privilegio_rol'),
              COALESCE((SELECT MAX(ropr_id_privilegio_rol) FROM privilegio_rol), 1),
              (SELECT COUNT(*) > 0 FROM privilegio_rol));

-- --------------------------------------------------------------
-- 2. Catálogo de estados de la convocatoria
--
-- "Vigente" es la que admite registro, "Cerrada" la que terminó su
-- ciclo y "Interrumpida" la que se detuvo antes de tiempo. Las tres
-- caben en ESCO_ESTADO_CONVOCATORIA, que mide 15 caracteres
-- ("Interrumpida" son 12), así que la columna no se toca: ampliarla
-- obligaría a reescribir la tabla.
--
-- Se dan de alta por nombre y sin id explícito, para que el SERIAL
-- los asigne y no choquen en una base que ya traiga renglones. Ese
-- caso es real: suif_lleno.sql, el archivo de datos de prueba, ya
-- sembró (1,'Abierta'), (2,'Cerrada'), (3,'En Evaluación') con ids
-- explícitos y dejó la secuencia atrás. Por eso el setval del paso 1
-- va antes, y por eso "Cerrada" se reutiliza en vez de duplicarse.
--
-- En esa base de desarrollo quedan "Abierta" y "En Evaluación", que
-- ningún código consulta. No se borran: son renglones de otro script
-- y quitarlos no arregla nada.
-- --------------------------------------------------------------
INSERT INTO c_estado_convocatoria (esco_estado_convocatoria)
SELECT catalogo.nombre
  FROM (VALUES
      ('Vigente'),
      ('Cerrada'),
      ('Interrumpida')
  ) AS catalogo(nombre)
 WHERE NOT EXISTS (
     SELECT 1 FROM c_estado_convocatoria
      WHERE esco_estado_convocatoria = catalogo.nombre
 );

-- --------------------------------------------------------------
-- 3. El privilegio que abre el módulo
--
-- Por ahora sólo lo tiene el Superusuario. El día que la gestión de
-- convocatorias le toque a otra área, basta con un renglón más en
-- PRIVILEGIO_ROL: el código autoriza contra el privilegio y nunca
-- contra el nombre del rol.
--
-- PRIV_PRIVILEGIO mide 35 caracteres y "Gestionar Convocatorias" son
-- 23.
-- --------------------------------------------------------------
INSERT INTO privilegio (priv_privilegio)
SELECT 'Gestionar Convocatorias'
 WHERE NOT EXISTS (
     SELECT 1 FROM privilegio WHERE priv_privilegio = 'Gestionar Convocatorias'
 );

-- PRIVILEGIO_ROL no tiene índice único sobre el par, así que la
-- guarda contra duplicados va en el WHERE y no en un ON CONFLICT.
INSERT INTO privilegio_rol (ropr_id_privilegio, ropr_id_rol)
SELECT p.priv_id_privilegio, r.rol_id_rol
  FROM rol AS r
  JOIN privilegio AS p ON p.priv_privilegio = 'Gestionar Convocatorias'
 WHERE r.rol_tipo_rol = 'Superusuario'
   AND NOT EXISTS (
     SELECT 1
       FROM privilegio_rol AS pr
      WHERE pr.ropr_id_rol = r.rol_id_rol
        AND pr.ropr_id_privilegio = p.priv_id_privilegio
 );

-- --------------------------------------------------------------
-- 4. Estado inicial de las convocatorias que ya existían
--
-- ESTADO_CONVOCATORIA nunca se llenó, así que la convocatoria que
-- siembra suif_catalogos.sql no tiene estado. Sin este relleno el
-- pre-registro dejaría de encontrarla en cuanto el código empiece a
-- exigir "Vigente", y nadie podría registrarse.
--
-- Sólo se toca a las que no tengan ningún renglón: una convocatoria
-- que ya se cerró desde la pantalla no se reabre por volver a correr
-- el script.
--
-- El reparto respeta la regla del módulo —una sola vigente a la vez—:
-- la más reciente de las que están sin estado queda "Vigente" y las
-- demás "Cerrada". Y ni siquiera esa se marca vigente si en la base
-- ya hay otra convocatoria vigente; entonces todas caen en el paso
-- 4.2 y quedan cerradas.
--
-- La fecha y la hora salen de CURRENT_DATE y LOCALTIME, que es el reloj
-- local de la sesión de psql, y no de now() en UTC. Puede no coincidir al
-- minuto con lo que escribe el código —PHP usa America/Mexico_City— si el
-- servidor tiene otra zona configurada; es un sello de instalación de una
-- sola vez y no una resolución que alguien haya dictado.
-- --------------------------------------------------------------

-- 4.1 La más reciente sin estado pasa a "Vigente".
INSERT INTO estado_convocatoria (
    esco_id_c_estado_convocatoria,
    esco_id_convocatoria,
    esco_fecha,
    esco_hora
)
SELECT cec.esco_id_c_estado_convocatoria,
       c.conv_id_convocatoria,
       CURRENT_DATE,
       LOCALTIME
  FROM convocatoria AS c
  JOIN c_estado_convocatoria AS cec
    ON cec.esco_estado_convocatoria = 'Vigente'
 WHERE c.conv_id_convocatoria = (
     SELECT MAX(sin_estado.conv_id_convocatoria)
       FROM convocatoria AS sin_estado
      WHERE NOT EXISTS (
          SELECT 1
            FROM estado_convocatoria AS ec
           WHERE ec.esco_id_convocatoria = sin_estado.conv_id_convocatoria
      )
 )
   AND NOT EXISTS (
     SELECT 1
       FROM estado_convocatoria AS ec
       JOIN c_estado_convocatoria AS actual
         ON actual.esco_id_c_estado_convocatoria = ec.esco_id_c_estado_convocatoria
      WHERE actual.esco_estado_convocatoria = 'Vigente'
        AND ec.esco_id_estado_convocatoria = (
            SELECT MAX(ultimo.esco_id_estado_convocatoria)
              FROM estado_convocatoria AS ultimo
             WHERE ultimo.esco_id_convocatoria = ec.esco_id_convocatoria
        )
 );

-- 4.2 Las que sigan sin estado quedan "Cerrada": son convocatorias de
--     ejercicios anteriores y no deben volver a admitir registro.
INSERT INTO estado_convocatoria (
    esco_id_c_estado_convocatoria,
    esco_id_convocatoria,
    esco_fecha,
    esco_hora
)
SELECT cec.esco_id_c_estado_convocatoria,
       c.conv_id_convocatoria,
       CURRENT_DATE,
       LOCALTIME
  FROM convocatoria AS c
  JOIN c_estado_convocatoria AS cec
    ON cec.esco_estado_convocatoria = 'Cerrada'
 WHERE NOT EXISTS (
     SELECT 1
       FROM estado_convocatoria AS ec
      WHERE ec.esco_id_convocatoria = c.conv_id_convocatoria
 );
