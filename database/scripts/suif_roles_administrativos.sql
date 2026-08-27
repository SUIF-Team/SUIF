/*==============================================================*/
/* SUIF — Roles administrativos y catálogo de privilegios       */
/* Complemento de suif.sql. Ejecutar DESPUÉS de los catálogos.  */
/* Puede volver a ejecutarse sin duplicar ni destruir nada.     */
/*==============================================================*/

/* OBLIGATORIO antes de publicar el código que autoriza por
   privilegio. suif_catalogos.sql crea PRIVILEGIO vacío, así que en
   una base recién instalada nadie tendría acceso a nada hasta que
   este script siembre el catálogo y lo reparta entre los roles. */

/*--------------------------------------------------------------*/
/* Dar de baja a un administrador no lo borra: se le retira el   */
/* acceso y se conserva el renglón, porque PERSONA y USUARIO son */
/* el rastro de quién dictaminó cada expediente.                 */
/*--------------------------------------------------------------*/
ALTER TABLE usuario ADD COLUMN IF NOT EXISTS usua_activo BOOLEAN NOT NULL DEFAULT TRUE;

/*--------------------------------------------------------------*/
/* El rol 2 era el único administrador y tenía todo el catálogo.  */
/* Con la separación por área pasa a ser el rol sin límites, y su */
/* nombre lo dice. Mismo patrón que el refactor Participante ->   */
/* Persona: el INSERT de suif_catalogos.sql lleva ON CONFLICT DO  */
/* NOTHING y no corrige las bases ya instaladas.                  */
/*                                                                */
/* ROL_TIPO_ROL mide 15 caracteres: "Superusuario" son 12 y       */
/* "Admin UIF" / "Admin DEC" son 9. Por eso los nombres son       */
/* cortos y la columna no se toca.                                */
/*--------------------------------------------------------------*/
UPDATE rol SET rol_tipo_rol = 'Superusuario'
 WHERE rol_id_rol = 2 AND rol_tipo_rol = 'Administrador';

/*--------------------------------------------------------------*/
/* Los roles nuevos se dan de alta por nombre y sin id explícito. */
/* En una base de desarrollo que corrió suif_lleno.sql los ids 3  */
/* y 4 ya están ocupados; dejar que el SERIAL los asigne evita el */
/* choque. Esa misma base tiene el rol 2 llamado "Validador" y no */
/* "Administrador", así que el UPDATE de arriba no le aplica: los */
/* ambientes de prueba se reconstruyen con el orden del README.   */
/*--------------------------------------------------------------*/
INSERT INTO rol (rol_tipo_rol)
SELECT 'Admin UIF'
 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_tipo_rol = 'Admin UIF');

INSERT INTO rol (rol_tipo_rol)
SELECT 'Admin DEC'
 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE rol_tipo_rol = 'Admin DEC');

/* La secuencia se alinea con el máximo real. El COALESCE y el tercer
   argumento cubren la tabla vacía: setval es STRICT y con un MAX nulo no
   haría nada, dejando la secuencia atrás y provocando llave duplicada en
   el primer alta. */
SELECT setval(pg_get_serial_sequence('rol', 'rol_id_rol'),
              COALESCE((SELECT MAX(rol_id_rol) FROM rol), 1),
              (SELECT COUNT(*) > 0 FROM rol));

/*--------------------------------------------------------------*/
/* Catálogo de privilegios. Los cuatro primeros ya los nombraban  */
/* suif_lleno.sql y el comando suif:crear-admin; los dos últimos  */
/* sustituyen a las comparaciones por nombre de rol que hacían    */
/* los permisos de sedes y de referencias.                        */
/*--------------------------------------------------------------*/
INSERT INTO privilegio (priv_privilegio)
SELECT nombre
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

SELECT setval(pg_get_serial_sequence('privilegio', 'priv_id_privilegio'),
              COALESCE((SELECT MAX(priv_id_privilegio) FROM privilegio), 1),
              (SELECT COUNT(*) > 0 FROM privilegio));

/*--------------------------------------------------------------*/
/* Reparto de privilegios por rol.                                */
/*                                                                */
/*   Superusuario  todo el catálogo                               */
/*   Admin UIF     pre-registro y documentación                   */
/*   Admin DEC     pagos y el catálogo de referencias, que es la  */
/*                 DEC quien lo emite                             */
/*                                                                */
/* PRIVILEGIO_ROL no tiene índice único sobre el par, así que la  */
/* guarda contra duplicados va en el WHERE y no en un ON CONFLICT.*/
/*--------------------------------------------------------------*/
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
  /* Las columnas no se llaman "rol" ni "privilegio" para que no se confundan
     con las tablas del mismo nombre a las que se unen. */
  ) AS reparto(nombre_rol, nombre_privilegio)
  JOIN rol AS r ON r.rol_tipo_rol = reparto.nombre_rol
  JOIN privilegio AS p ON p.priv_privilegio = reparto.nombre_privilegio
 WHERE NOT EXISTS (
     SELECT 1
       FROM privilegio_rol AS pr
      WHERE pr.ropr_id_rol = r.rol_id_rol
        AND pr.ropr_id_privilegio = p.priv_id_privilegio
 );

SELECT setval(pg_get_serial_sequence('privilegio_rol', 'ropr_id_privilegio_rol'),
              COALESCE((SELECT MAX(ropr_id_privilegio_rol) FROM privilegio_rol), 1),
              (SELECT COUNT(*) > 0 FROM privilegio_rol));
