/*==============================================================*/
/* SUIF — Catálogos base del sistema                            */
/* Complemento de suif.sql. Ejecutar DESPUÉS del esquema.       */
/* Puede volver a ejecutarse sin duplicar información.          */
/*==============================================================*/

/* Entidades federativas — claves oficiales INEGI */
INSERT INTO entidad_federativa (enfe_clave_inegi, enfe_entidad_federativa) VALUES
    ('001', 'Aguascalientes'),
    ('002', 'Baja California'),
    ('003', 'Baja California Sur'),
    ('004', 'Campeche'),
    ('005', 'Coahuila'),
    ('006', 'Colima'),
    ('007', 'Chiapas'),
    ('008', 'Chihuahua'),
    ('009', 'Ciudad de México'),
    ('010', 'Durango'),
    ('011', 'Guanajuato'),
    ('012', 'Guerrero'),
    ('013', 'Hidalgo'),
    ('014', 'Jalisco'),
    ('015', 'Estado de México'),
    ('016', 'Michoacán'),
    ('017', 'Morelos'),
    ('018', 'Nayarit'),
    ('019', 'Nuevo León'),
    ('020', 'Oaxaca'),
    ('021', 'Puebla'),
    ('022', 'Querétaro'),
    ('023', 'Quintana Roo'),
    ('024', 'San Luis Potosí'),
    ('025', 'Sinaloa'),
    ('026', 'Sonora'),
    ('027', 'Tabasco'),
    ('028', 'Tamaulipas'),
    ('029', 'Tlaxcala'),
    ('030', 'Veracruz'),
    ('031', 'Yucatán'),
    ('032', 'Zacatecas')
ON CONFLICT (enfe_clave_inegi) DO NOTHING;

/* Roles del sistema */
INSERT INTO rol (rol_id_rol, rol_tipo_rol) VALUES
    (1, 'Participante'),
    (2, 'Administrador')
ON CONFLICT (rol_id_rol) DO NOTHING;

SELECT setval(pg_get_serial_sequence('rol', 'rol_id_rol'),
              (SELECT MAX(rol_id_rol) FROM rol));

/* Tipos de comunicación — correos y teléfono del participante */
INSERT INTO tipo_comunicacion (tico_id_tipo_comunicacion, tico_tipo_comunicacion) VALUES
    (1, 'Correo principal'),
    (2, 'Correo alterno'),
    (3, 'Teléfono celular')
ON CONFLICT (tico_id_tipo_comunicacion) DO NOTHING;

SELECT setval(pg_get_serial_sequence('tipo_comunicacion', 'tico_id_tipo_comunicacion'),
              (SELECT MAX(tico_id_tipo_comunicacion) FROM tipo_comunicacion));

/* Niveles profesionales — último grado de estudios */
INSERT INTO nivel_profesional (nipr_id_nivel_profesional, nipr_nivel_profesional) VALUES
    (1, 'Bachillerato'),
    (2, 'Licenciatura'),
    (3, 'Especialidad'),
    (4, 'Maestría'),
    (5, 'Doctorado')
ON CONFLICT (nipr_id_nivel_profesional) DO NOTHING;

SELECT setval(pg_get_serial_sequence('nivel_profesional', 'nipr_id_nivel_profesional'),
              (SELECT MAX(nipr_id_nivel_profesional) FROM nivel_profesional));

/* Tipos de documento del pre-registro */
INSERT INTO tipo_documento (tido_id_tipo_documento, tido_tipo_documento) VALUES
    (1, 'Solicitud firmada'),
    (2, 'Aceptación de notificaciones'),
    (3, 'Carta bajo protesta'),
    (4, 'Autorización de la publicación'),
    (5, 'CURP'),
    (6, 'Identificación oficial')
ON CONFLICT (tido_id_tipo_documento) DO NOTHING;

SELECT setval(pg_get_serial_sequence('tipo_documento', 'tido_id_tipo_documento'),
              (SELECT MAX(tido_id_tipo_documento) FROM tipo_documento));

/* Estados posibles de cada documento */
INSERT INTO c_estado_documento (esdo_id_c_estado_documento, esdo_estado_documento) VALUES
    (1, 'Pendiente'),
    (2, 'Cargado'),
    (3, 'En revisión'),
    (4, 'Aprobado'),
    (5, 'Rechazado')
ON CONFLICT (esdo_id_c_estado_documento) DO NOTHING;

SELECT setval(pg_get_serial_sequence('c_estado_documento', 'esdo_id_c_estado_documento'),
              (SELECT MAX(esdo_id_c_estado_documento) FROM c_estado_documento));

/* Estados posibles de la solicitud */
INSERT INTO c_estado_solicitud (esso_id_c_estado_solicitud, esso_estatus_solicitud) VALUES
    (1, 'Pre-registro'),
    (2, 'Documentación'),
    (3, 'En revisión'),
    (4, 'Aprobada'),
    (5, 'Rechazada'),
    (6, 'Cancelada')
ON CONFLICT (esso_id_c_estado_solicitud) DO NOTHING;

SELECT setval(pg_get_serial_sequence('c_estado_solicitud', 'esso_id_c_estado_solicitud'),
              (SELECT MAX(esso_id_c_estado_solicitud) FROM c_estado_solicitud));

/* Convocatoria vigente */
INSERT INTO convocatoria (
    conv_id_convocatoria,
    conv_nombre,
    conv_monto_recuperacion,
    conv_fecha_inicio_registro,
    conv_fecha_fin_registro,
    conv_fin_fecha_entrega_docs,
    conv_fecha_inicio,
    conv_fecha_fin
) VALUES (
    1,
    'Certificación 2026 en materia de prevención de operaciones con recursos de procedencia ilícita',
    7000.00::money,
    '2026-01-01',
    '2026-12-31',
    '2026-12-31',
    '2026-01-01',
    '2026-12-31'
)
ON CONFLICT (conv_id_convocatoria) DO NOTHING;

SELECT setval(pg_get_serial_sequence('convocatoria', 'conv_id_convocatoria'),
              (SELECT MAX(conv_id_convocatoria) FROM convocatoria));