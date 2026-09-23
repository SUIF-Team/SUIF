-- ==============================================================
-- SUIF — Roles administrativos y catálogo de privilegios
-- Complemento de suif.sql. Ejecutar DESPUÉS de suif_catalogos.sql.
-- Puede volver a ejecutarse sin duplicar ni destruir nada.
-- ==============================================================
--
-- OBLIGATORIO antes de publicar el código que autoriza por privilegio.
-- suif.sql crea PRIVILEGIO vacío y suif_catalogos.sql no lo siembra, así
-- que sin este script nadie tendría acceso a ningún módulo.
--
-- Los comentarios van con "--" y no con bloques: PostgreSQL anida los
-- /* */ y un comentario mal cerrado deja el resto del archivo del lado
-- equivocado. Ya rompió suif_evaluacion_grupo.sql y el primer UPDATE de
-- suif_reconstruye_tablas_perdidas.sql.

-- --------------------------------------------------------------
-- 1. Baja lógica del administrador
--
-- Dar de baja a un administrador no lo borra: se le retira el acceso y se
-- conserva el renglón, porque PERSONA y USUARIO son el rastro de quién
-- dictaminó cada expediente. El DEFAULT TRUE deja intactas las cuentas
-- que ya existían.
-- --------------------------------------------------------------
ALTER TABLE usuario ADD COLUMN IF NOT EXISTS usua_activo BOOLEAN NOT NULL DEFAULT TRUE;

-- --------------------------------------------------------------
-- 2. Alinear las secuencias ANTES de insertar
--
-- Las tablas del catálogo se cargan con identificadores explícitos desde
-- los scripts, así que la secuencia puede quedar atrás y un alta sin id
-- chocaría con una llave existente. suif_catalogos.sql ya alinea ROL,
-- pero PRIVILEGIO no lo cubre ningún script: nace vacío y hasta ahora lo
-- llenaba el comando suif:crear-admin.
--
-- El COALESCE y el tercer argumento cubren la tabla vacía: setval es
-- STRICT y con un MAX nulo no haría nada, dejando la secuencia atrás.
-- --------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('rol', 'rol_id_rol'),
              COALESCE((SELECT MAX(rol_id_rol) FROM rol), 1),
              (SELECT COUNT(*) > 0 FROM rol));

SELECT setval(pg_get_serial_sequence('privilegio', 'priv_id_privilegio'),
              COALESCE((SELECT MAX(priv_id_privilegio) FROM privilegio), 1),
              (SELECT COUNT(*) > 0 FROM privilegio));

SELECT setval(pg_get_serial_sequence('privilegio_rol', 'ropr_id_privilegio_rol'),
              COALESCE((SELECT MAX(ropr_id_privilegio_rol) FROM privilegio_rol), 1),
              (SELECT COUNT(*) > 0 FROM privilegio_rol));

-- --------------------------------------------------------------
-- 3. El rol 2 pasa a llamarse Superusuario
--
-- Era el único administrador y tenía todo el catálogo. Con la separación
-- por área pasa a ser el rol sin límites, y su nombre lo dice. Mismo
-- patrón que el refactor Participante -> Persona: el INSERT de
-- suif_catalogos.sql lleva ON CONFLICT DO NOTHING y no corrige las bases
-- ya instaladas.
--
-- La guarda por nombre hace la sentencia idempotente y protege la base de
-- desarrollo de suif_lleno.sql, donde el rol 2 se llama "Validador".
-- --------------------------------------------------------------
UPDATE rol
   SET rol_tipo_rol = 'Superusuario'
 WHERE rol_id_rol = 2
   AND rol_tipo_rol = 'Administrador';

-- --------------------------------------------------------------
-- 4. Los dos roles de área
--
-- Se dan de alta por nombre y sin id explícito: en una base que corrió
-- suif_lleno.sql los ids 3 y 4 ya están ocupados, y dejar que el SERIAL
-- los asigne evita el choque.
--
-- ROL_TIPO_ROL mide 15 caracteres: "Superusuario" son 12 y "Admin UIF" /
-- "Admin DEC" son 9. Por eso los nombres son cortos y la columna no se
-- toca; ampliarla obligaría a reescribir la tabla.
-- --------------------------------------------------------------
INSERT INTO rol (rol_tipo_rol)
SELECT 'Admin UIF'
 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_tipo_rol = 'Admin UIF');

INSERT INTO rol (rol_tipo_rol)
SELECT 'Admin DEC'
 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_tipo_rol = 'Admin DEC');

-- --------------------------------------------------------------
-- 5. Catálogo de privilegios
--
-- Los cuatro primeros ya los nombraba el comando suif:crear-admin. Los
-- dos últimos son nuevos y sustituyen las comparaciones por nombre de rol
-- que hacían los permisos de sedes y de referencias: con más de un tipo
-- de administrador, comparar contra la cadena "Administrador" deja fuera
-- a los demás.
-- --------------------------------------------------------------
INSERT INTO privilegio (priv_privilegio)
SELECT catalogo.nombre
  FROM (VALUES
      ('Validación Registro'),
      ('Gestionar Pagos'),
      ('Generación Reportes'),
      ('Gestionar usuarios'),
      ('Gestionar Referencias'),
      ('Gestionar Sedes')
  ) AS catalogo(nombre)
 WHERE NOT EXISTS (
     SELECT 1 FROM privilegio WHERE priv_privilegio = catalogo.nombre
 );

-- --------------------------------------------------------------
-- 6. Reparto de privilegios por rol
--
--   Superusuario  todo el catálogo
--   Admin UIF     pre-registro y documentación
--   Admin DEC     pagos y el catálogo de referencias, que es la DEC quien
--                 lo emite
--
-- PRIVILEGIO_ROL no tiene índice único sobre el par, así que la guarda
-- contra duplicados va en el WHERE y no en un ON CONFLICT.
--
-- Las columnas de la lista no se llaman "rol" ni "privilegio" para que no
-- se confundan con las tablas del mismo nombre a las que se unen.
-- --------------------------------------------------------------
INSERT INTO privilegio_rol (ropr_id_privilegio, ropr_id_rol)
SELECT p.priv_id_privilegio, r.rol_id_rol
  FROM (VALUES
      ('Superusuario', 'Validación Registro'),
      ('Superusuario', 'Gestionar Pagos'),
      ('Superusuario', 'Generación Reportes'),
      ('Superusuario', 'Gestionar usuarios'),
      ('Superusuario', 'Gestionar Referencias'),
      ('Superusuario', 'Gestionar Sedes'),
      ('Admin UIF',    'Validación Registro'),
      ('Admin DEC',    'Gestionar Pagos'),
      ('Admin DEC',    'Gestionar Referencias')
  ) AS reparto(nombre_rol, nombre_privilegio)
  JOIN rol AS r ON r.rol_tipo_rol = reparto.nombre_rol
  JOIN privilegio AS p ON p.priv_privilegio = reparto.nombre_privilegio
 WHERE NOT EXISTS (
     SELECT 1
       FROM privilegio_rol AS pr
      WHERE pr.ropr_id_rol = r.rol_id_rol
        AND pr.ropr_id_privilegio = p.priv_id_privilegio
 );
