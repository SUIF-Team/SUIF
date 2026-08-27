-- ==============================================================
-- SUIF — Devuelve el rol 2 a "Administrador"
--
-- NO es parte de la instalación. Se ejecuta UNA vez, y sólo sobre
-- una base a la que se le aplicó suif_roles_administrativos.sql.
-- Puede volver a ejecutarse sin efectos secundarios.
-- ==============================================================

-- El módulo de administradores por área se retiró del código. Aquel script
-- había renombrado el rol 2 de "Administrador" a "Superusuario" y había dado
-- de alta "Admin UIF" y "Admin DEC", porque los permisos se resolvían por
-- privilegio. El código al que se volvió compara otra vez el NOMBRE del rol
-- contra la cadena "Administrador" en los gates de sedes, referencias y
-- reversión, así que sin este cambio la cuenta administrativa entra al
-- sistema pero se queda sin esos módulos.
--
-- Los comentarios van con "--" y no con bloques: PostgreSQL los anida, y un
-- comentario mal cerrado deja el resto del archivo del lado equivocado.

UPDATE rol
   SET rol_tipo_rol = 'Administrador'
 WHERE rol_id_rol = 2
   AND rol_tipo_rol = 'Superusuario';

-- Los privilegios sembrados no estorban: el código restaurado sólo consulta
-- "Gestionar Pagos" y el resto queda inerte. Se conservan porque volver a
-- borrarlos no aporta nada y el catálogo es el que suif_lleno.sql ya definía.

-- Admin UIF y Admin DEC quedan sin uso. No se eliminan: pueden tener usuarios
-- colgando, y USUARIO.USUA_ID_ROL es llave foránea. Si alguna cuenta quedó con
-- uno de esos roles, pásala a Administrador o al rol 1 según le corresponda.
-- Para verlas:
--
--     SELECT p.pers_curp, r.rol_tipo_rol
--       FROM persona p
--       JOIN usuario u ON u.usua_id_usuario = p.pers_id_usuario
--       JOIN rol r ON r.rol_id_rol = u.usua_id_rol
--      WHERE r.rol_tipo_rol IN ('Admin UIF', 'Admin DEC');

-- USUA_ACTIVO se conserva. La columna admite nulo por omisión en TRUE y el
-- código restaurado no la consulta, así que no molesta; quitarla sí obligaría
-- a reescribir la tabla.
