/* ========================================================================= */
/* 1. LLENADO DE CATÁLOGOS BASE (Sin dependencias)                           */
/* ========================================================================= */

-- Entidades Federativas (Claves INEGI 001 - 032 en orden alfabético)
INSERT INTO ENTIDAD_FEDERATIVA (ENFE_CLAVE_INEGI, ENFE_ENTIDAD_FEDERATIVA) VALUES 
('001', 'Aguascalientes'), ('002', 'Baja California'), ('003', 'Baja California Sur'),
('004', 'Campeche'), ('005', 'Coahuila'), ('006', 'Colima'),
('007', 'Chiapas'), ('008', 'Chihuahua'), ('009', 'Ciudad de México'),
('010', 'Durango'), ('011', 'Guanajuato'), ('012', 'Guerrero'),
('013', 'Hidalgo'), ('014', 'Jalisco'), ('015', 'México'),
('016', 'Michoacán'), ('017', 'Morelos'), ('018', 'Nayarit'),
('019', 'Nuevo León'), ('020', 'Oaxaca'), ('021', 'Puebla'),
('022', 'Querétaro'), ('023', 'Quintana Roo'), ('024', 'San Luis Potosí'),
('025', 'Sinaloa'), ('026', 'Sonora'), ('027', 'Tabasco'),
('028', 'Tamaulipas'), ('029', 'Tlaxcala'), ('030', 'Veracruz'),
('031', 'Yucatán'), ('032', 'Zacatecas');

-- Privilegios solicitados
INSERT INTO PRIVILEGIO (PRIV_ID_PRIVILEGIO, PRIV_PRIVILEGIO) VALUES 
(1, 'Validación Registro'),
(2, 'Gestionar Pagos'),
(3, 'Generación Reportes'),
(4, 'Gestionar usuarios');

-- Roles del sistema
INSERT INTO ROL (ROL_ID_ROL, ROL_TIPO_ROL) VALUES 
(1, 'Administrador'),
(2, 'Validador'),
(3, 'Candidato'),
(4, 'Auditor');

-- Asignación de Privilegios a Roles (Diversidad de permisos)
INSERT INTO PRIVILEGIO_ROL (ROPR_ID_PRIVILEGIO, ROPR_ID_ROL) VALUES 
(1, 2), (2, 2), (3, 1), (4, 1), (3, 4), (1, 1), (2, 1);

-- Catálogos de Estados y Tipos
INSERT INTO C_ESTADO_CONVOCATORIA (ESCO_ID_C_ESTADO_CONVOCATORIA, ESCO_ESTADO_CONVOCATORIA) VALUES 
(1, 'Abierta'), (2, 'Cerrada'), (3, 'En Evaluación');

INSERT INTO C_ESTADO_SOLICITUD (ESSO_ID_C_ESTADO_SOLICITUD, ESSO_ESTADO_SOLICITUD) VALUES 
(1, 'Recibida'), (2, 'En Revisión'), (3, 'Aprobada'), (4, 'Rechazada');

INSERT INTO C_ESTADO_DOCUMENTO (ESDO_ID_C_ESTADO_DOCUMENTO, ESDO_ESTADO_DOCUMENTO) VALUES 
(1, 'Cargado'), (2, 'Validado'), (3, 'Rechazado - Ilegible');

INSERT INTO C_ESTADO_PAGO (ESPA_ID_C_ESTADO_PAGO, ESTA_ESTADO_PAGO) VALUES 
(1, 'Pendiente'), (2, 'Completado'), (3, 'Declinado');

INSERT INTO TIPO_COMUNICACION (TICO_ID_TIPO_COMUNICACION, TICO_TIPO_COMUNICACION) VALUES 
(1, 'Correo Electrónico'), (2, 'Teléfono Móvil'), (3, 'Teléfono Fijo');

INSERT INTO TIPO_DOCUMENTO (TIDO_ID_TIPO_DOCUMENTO, TIDO_TIPO_DOCUMENTO) VALUES 
(1, 'Identificación Oficial'), (2, 'Comprobante Domicilio'), (3, 'Título Profesional'), (4, 'CV');

INSERT INTO NIVEL_PROFESIONAL (NIPR_ID_NIVEL_PROFESIONAL, NIPR_NIVEL_PROFESIONAL) VALUES 
(1, 'Licenciatura'), (2, 'Maestría'), (3, 'Doctorado'), (4, 'Especialidad');

INSERT INTO REGIMEN_FISCAL (REFI_ID_REGIMEN_FISCAL, REFI_REGIMEN_FISCAL) VALUES 
(1, '601 - General de Ley P. Morales'), 
(2, '605 - Sueldos y Salarios'), 
(3, '612 - Personas Físicas Act. Emp.'),
(4, '626 - RESICO');

INSERT INTO CODIGO_POSTAL (COPO_ID_CODIGO_POSTAL) VALUES 
('01000'), ('57000'), ('64000'), ('44100'), ('72000'), ('97000'), ('22000'), ('11560');

-- Sedes
INSERT INTO SEDE (SEDE_ID_SEDE, SEDE_NOMBRE, SEDE_DIRECCION, SEDE_CUPO, SEDE_ESTADO) VALUES
(1, 'Sede Central CDMX', 'Sede Central CDMX', 50, true),
(2, 'Sede Norte Monterrey', 'Sede Norte Monterrey', 30, true),
(3, 'Sede Occidente GDL', 'Sede Occidente GDL', 40, true),
(4, 'Sede Sur Mérida', 'Sede Sur Mérida', 25, false),
(5, 'Sede Inactiva', 'Sede Inactiva', 10, false);


/* ========================================================================= */
/* 2. TABLAS TRANSACCIONALES BASE (Usuarios, Personas y Datos Fiscales)      */
/* ========================================================================= */

-- 30 Usuarios (Diversidad de roles)
INSERT INTO USUARIO (USUA_ID_USUARIO, USUA_ID_ROL, USUA_CLAVE_ACCESO) VALUES 
(1, 1, 'AdminPass01'), (2, 2, 'ValPass02'), (3, 4, 'AuditPass03'), (4, 3, 'CandPass04'),
(5, 3, 'CandPass05'), (6, 3, 'CandPass06'), (7, 3, 'CandPass07'), (8, 3, 'CandPass08'),
(9, 3, 'CandPass09'), (10, 3, 'CandPass10'), (11, 3, 'CandPass11'), (12, 3, 'CandPass12'),
(13, 3, 'CandPass13'), (14, 3, 'CandPass14'), (15, 3, 'CandPass15'), (16, 3, 'CandPass16'),
(17, 3, 'CandPass17'), (18, 3, 'CandPass18'), (19, 3, 'CandPass19'), (20, 3, 'CandPass20'),
(21, 3, 'CandPass21'), (22, 3, 'CandPass22'), (23, 3, 'CandPass23'), (24, 3, 'CandPass24'),
(25, 3, 'CandPass25'), (26, 3, 'CandPass26'), (27, 3, 'CandPass27'), (28, 3, 'CandPass28'),
(29, 3, 'CandPass29'), (30, 3, 'CandPass30');

-- 30 Personas (Diversidad de estados y géneros en apellidos)
INSERT INTO PERSONA (PERS_ID_PERSONA, PERS_CLAVE_INEGI, PERS_ID_USUARIO, PERS_CURP, PERS_NOMBRE, PERS_APELLIDO_PATERNO, PERS_APELLIDO_MATERNO, PERS_FECHA_REGISTRO) VALUES 
(1, '009', 1, 'AAAA800101HDFRRN01', 'Carlos', 'García', 'López', '2025-01-10'),
(2, '014', 2, 'BBBB850202MDFRRN02', 'María', 'Pérez', 'Gómez', '2025-01-12'),
(3, '019', 3, 'CCCC900303HDFRRN03', 'Juan', 'Martínez', 'Sánchez', '2025-01-15'),
(4, '015', 4, 'DDDD950404MDFRRN04', 'Ana', 'Hernández', 'Díaz', '2025-02-01'),
(5, '001', 5, 'EEEE880505HDFRRN05', 'Luis', 'González', 'Torres', '2025-02-05'),
(6, '002', 6, 'FFFF920606MDFRRN06', 'Laura', 'Rodríguez', 'Ruiz', '2025-02-10'),
(7, '003', 7, 'GGGG810707HDFRRN07', 'José', 'López', 'Vázquez', '2025-02-15'),
(8, '004', 8, 'HHHH940808MDFRRN08', 'Carmen', 'Flores', 'Ramos', '2025-03-01'),
(9, '005', 9, 'IIII870909HDFRRN09', 'Pedro', 'Gómez', 'Ramírez', '2025-03-10'),
(10, '006', 10, 'JJJJ911010MDFRRN10', 'Sofía', 'Díaz', 'Cruz', '2025-03-20'),
(11, '007', 11, 'KKKK831111HDFRRN11', 'Miguel', 'Cruz', 'Reyes', '2025-04-01'),
(12, '008', 12, 'LLLL961212MDFRRN12', 'Lucía', 'Morales', 'Gutiérrez', '2025-04-05'),
(13, '010', 13, 'MMMM890113HDFRRN13', 'Jorge', 'Ortiz', 'Aguilar', '2025-04-10'),
(14, '011', 14, 'NNNN930214MDFRRN14', 'Elena', 'Silva', 'Mendoza', '2025-04-15'),
(15, '012', 15, 'OOOO840315HDFRRN15', 'Raúl', 'Reyes', 'Castillo', '2025-05-01'),
(16, '013', 16, 'PPPP970416MDFRRN16', 'Marta', 'Aguilar', 'Chávez', '2025-05-10'),
(17, '016', 17, 'QQQQ860517HDFRRN17', 'Roberto', 'Mendoza', 'Romero', '2025-05-20'),
(18, '017', 18, 'RRRR980618MDFRRN18', 'Isabel', 'Castillo', 'Herrera', '2025-06-01'),
(19, '018', 19, 'SSSS820719HDFRRN19', 'Francisco', 'Chávez', 'Medina', '2025-06-05'),
(20, '020', 20, 'TTTT990820MDFRRN20', 'Teresa', 'Romero', 'Castro', '2025-06-10'),
(21, '021', 21, 'UUUU850921HDFRRN21', 'Javier', 'Herrera', 'Salazar', '2025-06-15'),
(22, '022', 22, 'VVVV901022MDFRRN22', 'Patricia', 'Medina', 'Guzmán', '2025-07-01'),
(23, '023', 23, 'WWWW881123HDFRRN23', 'Fernando', 'Castro', 'Peña', '2025-07-10'),
(24, '024', 24, 'XXXX921224MDFRRN24', 'Rosa', 'Salazar', 'Rojas', '2025-07-20'),
(25, '025', 25, 'YYYY810125HDFRRN25', 'Ricardo', 'Guzmán', 'Molina', '2025-08-01'),
(26, '026', 26, 'ZZZZ950226MDFRRN26', 'Silvia', 'Peña', 'Delgado', '2025-08-05'),
(27, '027', 27, 'AABB870327HDFRRN27', 'Hugo', 'Rojas', 'Vega', '2025-08-10'),
(28, '028', 28, 'CCDD910428MDFRRN28', 'Gloria', 'Molina', 'Navarro', '2025-08-15'),
(29, '029', 29, 'EEFF830529HDFRRN29', 'Andrés', 'Delgado', 'Soto', '2025-09-01'),
(30, '030', 30, 'GGHH960630MDFRRN30', 'Diana', 'Vega', 'Ríos', '2025-09-10');

-- 30 Datos Fiscales (Relacionados al código postal y régimen)
INSERT INTO DATO_FISCAL (DAFI_ID_DATO_FISCAL, DAFI_ID_REGIMEN_FISCAL, DAFI_ID_CODIGO_POSTAL, DAFI_RAZON_SOCIAL, DAFI_RFC, DAFI_PERSONA_MORAL, DAFI_USO_CFDI) VALUES 
(1, 1, '01000', 'Empresa Alpha SA de CV', 'EAL900101XYZ', true, true),
(2, 2, '57000', 'María Pérez Gómez', 'PEGM850202ABC', false, true),
(3, 3, '64000', 'Juan Martínez Sánchez', 'MASJ900303DEF', false, false),
(4, 4, '44100', 'Ana Hernández Díaz', 'HEDA950404GHI', false, true),
(5, 2, '72000', 'Luis González Torres', 'GOTL880505JKL', false, false),
(6, 2, '97000', 'Laura Rodríguez Ruiz', 'RORL920606MNO', false, true),
(7, 3, '22000', 'José López Vázquez', 'LOVJ810707PQR', false, true),
(8, 4, '11560', 'Carmen Flores Ramos', 'FLRC940808STU', false, false),
(9, 2, '01000', 'Pedro Gómez Ramírez', 'GORP870909VWX', false, true),
(10, 2, '57000', 'Sofía Díaz Cruz', 'DICS911010YZA', false, true),
(11, 3, '64000', 'Miguel Cruz Reyes', 'CURM831111BCD', false, false),
(12, 4, '44100', 'Lucía Morales Gutiérrez', 'MOGL961212EFG', false, true),
(13, 2, '72000', 'Jorge Ortiz Aguilar', 'OAAJ890113HIJ', false, true),
(14, 2, '97000', 'Elena Silva Mendoza', 'SIME930214KLM', false, false),
(15, 3, '22000', 'Raúl Reyes Castillo', 'RECR840315NOP', false, true),
(16, 4, '11560', 'Marta Aguilar Chávez', 'AGCM970416QRS', false, true),
(17, 2, '01000', 'Roberto Mendoza Romero', 'MERR860517TUV', false, false),
(18, 2, '57000', 'Isabel Castillo Herrera', 'CAHI980618WXY', false, true),
(19, 3, '64000', 'Francisco Chávez Medina', 'CHMF820719ZAB', false, true),
(20, 4, '44100', 'Teresa Romero Castro', 'ROCT990820CDE', false, false),
(21, 2, '72000', 'Javier Herrera Salazar', 'HESJ850921FGH', false, true),
(22, 2, '97000', 'Patricia Medina Guzmán', 'MEGP901022IJK', false, true),
(23, 3, '22000', 'Fernando Castro Peña', 'CAPF881123LMN', false, false),
(24, 4, '11560', 'Rosa Salazar Rojas', 'SARR921224OPQ', false, true),
(25, 2, '01000', 'Ricardo Guzmán Molina', 'GUMR810125RST', false, true),
(26, 2, '57000', 'Silvia Peña Delgado', 'PEDS950226UVW', false, false),
(27, 3, '64000', 'Hugo Rojas Vega', 'ROVH870327XYZ', false, true),
(28, 4, '44100', 'Gloria Molina Navarro', 'MONG910428ABC', false, true),
(29, 2, '72000', 'Andrés Delgado Soto', 'DESA830529DEF', false, false),
(30, 2, '97000', 'Diana Vega Ríos', 'VERD960630GHI', false, true);

/* ========================================================================= */
/* 3. CONVOCATORIAS, EVALUACIONES Y CERTIFICACIONES                          */
/* ========================================================================= */

INSERT INTO CONVOCATORIA (CONV_ID_CONVOCATORIA, CONV_NOMBRE, CONV_MONTO_RECUPERACION, CONV_FECHA_INICIO_REGISTRO, CONV_FECHA_FIN_REGISTRO, CONV_FIN_FECHA_ENTREGA_DOCS, CONV_FECHA_INICIO, CONV_FECHA_FIN) VALUES 
(1, 'Certificación Nacional de Auditores 2025', 1500.00, '2025-01-01', '2025-01-31', '2025-02-15', '2025-03-01', '2025-06-30'),
(2, 'Especialidad en Gestión Pública 2025', 2000.00, '2025-02-01', '2025-02-28', '2025-03-15', '2025-04-01', '2025-07-30'),
(3, 'Certificación de Procesos 2026', 1800.00, '2026-01-01', '2026-01-31', '2026-02-15', '2026-03-01', '2026-06-30');

INSERT INTO GRUPO (GRUP_ID_GRUPO, SEDE_ID_SEDE, GRUP_FECHA_INICIO, GRUP_HORA_INICIO, GRUP_FECHA_FIN, GRUP_HORA_FIN) VALUES 
(1, 1, '2025-05-10', '09:00:00', '2025-05-10', '13:00:00'),
(2, 2, '2025-05-11', '10:00:00', '2025-05-11', '14:00:00'),
(3, 3, '2025-05-12', '08:00:00', '2025-05-12', '12:00:00');

INSERT INTO EVALUACION (EVAL_ID_EVALUACION, GRUP_ID_GRUPO, EVAL_RESULTADO) VALUES 
(1, 1, 85),
(2, 2, 92),
(3, 3, 70);

INSERT INTO CERTIFICACION (CERT_ID_CERTIFICACION, EVAL_ID_EVALUACION, CERT_FECHA_EMISION, CERT_HORA_EMISION, CERT_ESTADO) VALUES 
(1, 1, '2025-07-15', '10:00:00', true),
(2, 2, '2025-07-16', '11:30:00', true),
(3, 3, '2025-08-01', '09:15:00', false);

INSERT INTO AUTORIZACION_SOLICITUD (AUSO_ID_AUTORIZACION_SOLICITUD, AUSO_FECHA_ACEPTACION, AUSO_HORA_ACEPTACION) VALUES 
(1, '2025-02-10', '14:20:00'), (2, '2025-02-11', '10:05:00'), (3, '2025-02-12', '16:45:00');

/* ========================================================================= */
/* 4. PAGOS Y SOLICITUDES (Al menos 30 registros variados)                   */
/* ========================================================================= */

-- 30 Pagos vinculados al dato fiscal
INSERT INTO PAGO (PAGO_ID_PAGO, PAGO_ID_DATO_FISCAL, PAGO_REFERENCIA_BANCARIA, PAGO_REFERENCIA_BANCARIA_PATH, PAGO_MONTO_PAGADO, PAGO_FECHA_PAGO, PAGO_HORA_PAGO, PAGO_COMPROBANTE_PATH, PAGO_USO_CFDI, PAGO_NO_EMPLEADO) VALUES 
(1, 1, 'REF2025001', '/docs/pagos/ref001.pdf', 1500.00, '2025-02-01', '10:00:00', '/docs/pagos/comp001.pdf', 'G03', NULL),
(2, 2, 'REF2025002', '/docs/pagos/ref002.pdf', 1500.00, '2025-02-02', '11:15:00', '/docs/pagos/comp002.pdf', 'G03', NULL),
(3, 3, 'REF2025003', '/docs/pagos/ref003.pdf', 2000.00, '2025-02-03', '09:30:00', '/docs/pagos/comp003.pdf', 'G03', 105),
(4, 4, 'REF2025004', '/docs/pagos/ref004.pdf', 1500.00, '2025-02-04', '14:20:00', '/docs/pagos/comp004.pdf', 'G03', NULL),
(5, 5, 'REF2025005', '/docs/pagos/ref005.pdf', 1500.00, '2025-02-05', '16:45:00', '/docs/pagos/comp005.pdf', 'P01', NULL),
(6, 6, 'REF2025006', '/docs/pagos/ref006.pdf', 2000.00, '2025-02-06', '08:10:00', '/docs/pagos/comp006.pdf', 'G03', NULL),
(7, 7, 'REF2025007', '/docs/pagos/ref007.pdf', 1500.00, '2025-02-07', '12:00:00', '/docs/pagos/comp007.pdf', 'G03', 201),
(8, 8, 'REF2025008', '/docs/pagos/ref008.pdf', 1500.00, '2025-02-08', '13:30:00', '/docs/pagos/comp008.pdf', 'P01', NULL),
(9, 9, 'REF2025009', '/docs/pagos/ref009.pdf', 1500.00, '2025-02-09', '15:25:00', '/docs/pagos/comp009.pdf', 'G03', NULL),
(10, 10, 'REF2025010', '/docs/pagos/ref010.pdf', 2000.00, '2025-02-10', '09:05:00', '/docs/pagos/comp010.pdf', 'G03', NULL),
(11, 11, 'REF2025011', '/docs/pagos/ref011.pdf', 1500.00, '2025-02-11', '10:40:00', '/docs/pagos/comp011.pdf', 'G03', 305),
(12, 12, 'REF2025012', '/docs/pagos/ref012.pdf', 1500.00, '2025-02-12', '11:55:00', '/docs/pagos/comp012.pdf', 'P01', NULL),
(13, 13, 'REF2025013', '/docs/pagos/ref013.pdf', 2000.00, '2025-02-13', '14:15:00', '/docs/pagos/comp013.pdf', 'G03', NULL),
(14, 14, 'REF2025014', '/docs/pagos/ref014.pdf', 1500.00, '2025-02-14', '16:30:00', '/docs/pagos/comp014.pdf', 'G03', NULL),
(15, 15, 'REF2025015', '/docs/pagos/ref015.pdf', 1500.00, '2025-02-15', '08:50:00', '/docs/pagos/comp015.pdf', 'G03', 412),
(16, 16, 'REF2025016', '/docs/pagos/ref016.pdf', 2000.00, '2025-02-16', '10:10:00', '/docs/pagos/comp016.pdf', 'P01', NULL),
(17, 17, 'REF2025017', '/docs/pagos/ref017.pdf', 1500.00, '2025-02-17', '12:25:00', '/docs/pagos/comp017.pdf', 'G03', NULL),
(18, 18, 'REF2025018', '/docs/pagos/ref018.pdf', 1500.00, '2025-02-18', '14:40:00', '/docs/pagos/comp018.pdf', 'G03', NULL),
(19, 19, 'REF2025019', '/docs/pagos/ref019.pdf', 1500.00, '2025-02-19', '16:55:00', '/docs/pagos/comp019.pdf', 'G03', 503),
(20, 20, 'REF2025020', '/docs/pagos/ref020.pdf', 2000.00, '2025-02-20', '09:20:00', '/docs/pagos/comp020.pdf', 'P01', NULL),
(21, 21, 'REF2025021', '/docs/pagos/ref021.pdf', 1500.00, '2025-02-21', '11:35:00', '/docs/pagos/comp021.pdf', 'G03', NULL),
(22, 22, 'REF2025022', '/docs/pagos/ref022.pdf', 1500.00, '2025-02-22', '13:50:00', '/docs/pagos/comp022.pdf', 'G03', NULL),
(23, 23, 'REF2025023', '/docs/pagos/ref023.pdf', 2000.00, '2025-02-23', '15:05:00', '/docs/pagos/comp023.pdf', 'G03', 615),
(24, 24, 'REF2025024', '/docs/pagos/ref024.pdf', 1500.00, '2025-02-24', '17:20:00', '/docs/pagos/comp024.pdf', 'P01', NULL),
(25, 25, 'REF2025025', '/docs/pagos/ref025.pdf', 1500.00, '2025-02-25', '08:45:00', '/docs/pagos/comp025.pdf', 'G03', NULL),
(26, 26, 'REF2025026', '/docs/pagos/ref026.pdf', 2000.00, '2025-02-26', '10:00:00', '/docs/pagos/comp026.pdf', 'G03', NULL),
(27, 27, 'REF2025027', '/docs/pagos/ref027.pdf', 1500.00, '2025-02-27', '12:15:00', '/docs/pagos/comp027.pdf', 'G03', 720),
(28, 28, 'REF2025028', '/docs/pagos/ref028.pdf', 1500.00, '2025-02-28', '14:30:00', '/docs/pagos/comp028.pdf', 'P01', NULL),
(29, 29, 'REF2025029', '/docs/pagos/ref029.pdf', 1500.00, '2025-03-01', '16:45:00', '/docs/pagos/comp029.pdf', 'G03', NULL),
(30, 30, 'REF2025030', '/docs/pagos/ref030.pdf', 2000.00, '2025-03-02', '09:10:00', '/docs/pagos/comp030.pdf', 'G03', NULL);

-- 30 Solicitudes (Mezclando convocatorias 1 y 2, personas y pagos)
INSERT INTO SOLICITUD (SOLI_ID_SOLICITUD, SOLI_ID_EVALUACION, SOLI_ID_AUTORIZACION_SOLICITUD, SOLI_ID_PERSONA, SOLI_ID_CONVOCATORIA, SOLI_ID_PAGO) VALUES 
(1, 1, 1, 4, 1, 1), (2, 2, 2, 5, 2, 2), (3, 3, 3, 6, 1, 3), 
(4, NULL, NULL, 7, 2, 4), (5, NULL, NULL, 8, 1, 5), (6, NULL, NULL, 9, 2, 6),
(7, NULL, NULL, 10, 1, 7), (8, NULL, NULL, 11, 2, 8), (9, NULL, NULL, 12, 1, 9),
(10, NULL, NULL, 13, 2, 10), (11, NULL, NULL, 14, 1, 11), (12, NULL, NULL, 15, 2, 12),
(13, NULL, NULL, 16, 1, 13), (14, NULL, NULL, 17, 2, 14), (15, NULL, NULL, 18, 1, 15),
(16, NULL, NULL, 19, 2, 16), (17, NULL, NULL, 20, 1, 17), (18, NULL, NULL, 21, 2, 18),
(19, NULL, NULL, 22, 1, 19), (20, NULL, NULL, 23, 2, 20), (21, NULL, NULL, 24, 1, 21),
(22, NULL, NULL, 25, 2, 22), (23, NULL, NULL, 26, 1, 23), (24, NULL, NULL, 27, 2, 24),
(25, NULL, NULL, 28, 1, 25), (26, NULL, NULL, 29, 2, 26), (27, NULL, NULL, 30, 1, 27),
(28, NULL, NULL, 1, 2, 28), (29, NULL, NULL, 2, 1, 29), (30, NULL, NULL, 3, 2, 30);

/* ========================================================================= */
/* 5. TABLAS RELACIONALES DE ESTATUS E HISTÓRICOS                            */
/* ========================================================================= */

-- Histórico Estado Pagos (Relacionado al pago mediante la FK definida en tu DDL ESPA_ID_PAGO -> PAGO_ID_PAGO)
INSERT INTO ESTADO_PAGO (ESPA_ID_PAGO, ESPA_ID_C_ESTADO_PAGO, ESPA_FECHA, ESPA_HORA) VALUES 
(1, 2, '2025-02-02', '12:00:00'), (2, 2, '2025-02-03', '12:00:00'), (3, 2, '2025-02-04', '12:00:00'),
(4, 2, '2025-02-05', '12:00:00'), (5, 2, '2025-02-06', '12:00:00'), (6, 3, '2025-02-07', '12:00:00'),
(7, 2, '2025-02-08', '12:00:00'), (8, 2, '2025-02-09', '12:00:00'), (9, 1, '2025-02-10', '12:00:00'),
(10, 2, '2025-02-11', '12:00:00'), (11, 2, '2025-02-12', '12:00:00'), (12, 1, '2025-02-13', '12:00:00'),
(13, 2, '2025-02-14', '12:00:00'), (14, 2, '2025-02-15', '12:00:00'), (15, 2, '2025-02-16', '12:00:00'),
(16, 3, '2025-02-17', '12:00:00'), (17, 2, '2025-02-18', '12:00:00'), (18, 2, '2025-02-19', '12:00:00'),
(19, 2, '2025-02-20', '12:00:00'), (20, 2, '2025-02-21', '12:00:00'), (21, 1, '2025-02-22', '12:00:00'),
(22, 2, '2025-02-23', '12:00:00'), (23, 2, '2025-02-24', '12:00:00'), (24, 1, '2025-02-25', '12:00:00'),
(25, 2, '2025-02-26', '12:00:00'), (26, 2, '2025-02-27', '12:00:00'), (27, 2, '2025-02-28', '12:00:00'),
(28, 3, '2025-03-01', '12:00:00'), (29, 2, '2025-03-02', '12:00:00'), (30, 2, '2025-03-03', '12:00:00');

-- Histórico Estado Solicitudes (Mostrando transiciones)
INSERT INTO ESTADO_SOLICITUD (ESSO_ID_C_ESTADO_SOLICITUD, ESSO_ID_SOLICITUD, ESSO_FECHA, ESSO_HORA, ESSO_MOTIVO_RECHAZO) VALUES 
(1, 1, '2025-02-05', '09:00:00', NULL), (3, 1, '2025-02-10', '10:00:00', NULL),
(1, 2, '2025-02-06', '09:00:00', NULL), (3, 2, '2025-02-11', '10:00:00', NULL),
(1, 3, '2025-02-07', '09:00:00', NULL), (4, 3, '2025-02-12', '10:00:00', 'Documentación Incompleta'),
(1, 4, '2025-02-08', '09:00:00', NULL), (2, 4, '2025-02-13', '10:00:00', NULL),
(1, 5, '2025-02-09', '09:00:00', NULL), (2, 5, '2025-02-14', '10:00:00', NULL),
(1, 6, '2025-02-10', '09:00:00', NULL), (4, 6, '2025-02-15', '10:00:00', 'Pago declinado'),
(1, 7, '2025-02-11', '09:00:00', NULL), (2, 7, '2025-02-16', '10:00:00', NULL),
(1, 8, '2025-02-12', '09:00:00', NULL), (2, 8, '2025-02-17', '10:00:00', NULL),
(1, 9, '2025-02-13', '09:00:00', NULL), (2, 9, '2025-02-18', '10:00:00', NULL),
(1, 10, '2025-02-14', '09:00:00', NULL), (2, 10, '2025-02-19', '10:00:00', NULL),
(1, 11, '2025-02-15', '09:00:00', NULL), (2, 11, '2025-02-20', '10:00:00', NULL),
(1, 12, '2025-02-16', '09:00:00', NULL), (2, 12, '2025-02-21', '10:00:00', NULL),
(1, 13, '2025-02-17', '09:00:00', NULL), (2, 13, '2025-02-22', '10:00:00', NULL),
(1, 14, '2025-02-18', '09:00:00', NULL), (2, 14, '2025-02-23', '10:00:00', NULL),
(1, 15, '2025-02-19', '09:00:00', NULL), (2, 15, '2025-02-24', '10:00:00', NULL),
(1, 16, '2025-02-20', '09:00:00', NULL), (4, 16, '2025-02-25', '10:00:00', 'Pago declinado'),
(1, 17, '2025-02-21', '09:00:00', NULL), (2, 17, '2025-02-26', '10:00:00', NULL),
(1, 18, '2025-02-22', '09:00:00', NULL), (2, 18, '2025-02-27', '10:00:00', NULL),
(1, 19, '2025-02-23', '09:00:00', NULL), (2, 19, '2025-02-28', '10:00:00', NULL),
(1, 20, '2025-02-24', '09:00:00', NULL), (2, 20, '2025-03-01', '10:00:00', NULL),
(1, 21, '2025-02-25', '09:00:00', NULL), (2, 21, '2025-03-02', '10:00:00', NULL),
(1, 22, '2025-02-26', '09:00:00', NULL), (2, 22, '2025-03-03', '10:00:00', NULL),
(1, 23, '2025-02-27', '09:00:00', NULL), (2, 23, '2025-03-04', '10:00:00', NULL),
(1, 24, '2025-02-28', '09:00:00', NULL), (2, 24, '2025-03-05', '10:00:00', NULL),
(1, 25, '2025-03-01', '09:00:00', NULL), (2, 25, '2025-03-06', '10:00:00', NULL),
(1, 26, '2025-03-02', '09:00:00', NULL), (2, 26, '2025-03-07', '10:00:00', NULL),
(1, 27, '2025-03-03', '09:00:00', NULL), (2, 27, '2025-03-08', '10:00:00', NULL),
(1, 28, '2025-03-04', '09:00:00', NULL), (4, 28, '2025-03-09', '10:00:00', 'Pago declinado'),
(1, 29, '2025-03-05', '09:00:00', NULL), (2, 29, '2025-03-10', '10:00:00', NULL),
(1, 30, '2025-03-06', '09:00:00', NULL), (2, 30, '2025-03-11', '10:00:00', NULL);

-- Documentos (Para 15 de las solicitudes, 2 documentos c/u para generar 30 registros)
INSERT INTO DOCUMENTO (DOCU_ID_DOCUMENTO, TIDO_ID_TIPO_DOCUMENTO, SOLI_ID_SOLICITUD, DOCU_NOMBRE, DOCU_PATH, DOCU_FECHA_CARGA, DOCU_HORA_CARGA, DOCU_FECHA_AUTORIZACION, DOCU_HORA_AUTORIZACION) VALUES 
(1, 1, 1, 'INE Frente', '/docs/ine_f1.pdf', '2025-02-05', '10:00:00', '2025-02-10', '12:00:00'),
(2, 2, 1, 'Comprobante', '/docs/comp1.pdf', '2025-02-05', '10:05:00', '2025-02-10', '12:05:00'),
(3, 1, 2, 'INE Frente', '/docs/ine_f2.pdf', '2025-02-06', '10:00:00', '2025-02-11', '12:00:00'),
(4, 3, 2, 'Titulo', '/docs/tit2.pdf', '2025-02-06', '10:05:00', '2025-02-11', '12:05:00'),
(5, 1, 3, 'INE Frente', '/docs/ine_f3.pdf', '2025-02-07', '10:00:00', NULL, NULL),
(6, 4, 3, 'CV', '/docs/cv3.pdf', '2025-02-07', '10:05:00', NULL, NULL),
(7, 1, 4, 'INE Frente', '/docs/ine_f4.pdf', '2025-02-08', '10:00:00', NULL, NULL),
(8, 2, 4, 'Comprobante', '/docs/comp4.pdf', '2025-02-08', '10:05:00', NULL, NULL),
(9, 1, 5, 'INE Frente', '/docs/ine_f5.pdf', '2025-02-09', '10:00:00', NULL, NULL),
(10, 3, 5, 'Titulo', '/docs/tit5.pdf', '2025-02-09', '10:05:00', NULL, NULL),
(11, 1, 6, 'INE Frente', '/docs/ine_f6.pdf', '2025-02-10', '10:00:00', NULL, NULL),
(12, 4, 6, 'CV', '/docs/cv6.pdf', '2025-02-10', '10:05:00', NULL, NULL),
(13, 1, 7, 'INE Frente', '/docs/ine_f7.pdf', '2025-02-11', '10:00:00', NULL, NULL),
(14, 2, 7, 'Comprobante', '/docs/comp7.pdf', '2025-02-11', '10:05:00', NULL, NULL),
(15, 1, 8, 'INE Frente', '/docs/ine_f8.pdf', '2025-02-12', '10:00:00', NULL, NULL),
(16, 3, 8, 'Titulo', '/docs/tit8.pdf', '2025-02-12', '10:05:00', NULL, NULL),
(17, 1, 9, 'INE Frente', '/docs/ine_f9.pdf', '2025-02-13', '10:00:00', NULL, NULL),
(18, 4, 9, 'CV', '/docs/cv9.pdf', '2025-02-13', '10:05:00', NULL, NULL),
(19, 1, 10, 'INE Frente', '/docs/ine_f10.pdf', '2025-02-14', '10:00:00', NULL, NULL),
(20, 2, 10, 'Comprobante', '/docs/comp10.pdf', '2025-02-14', '10:05:00', NULL, NULL),
(21, 1, 11, 'INE Frente', '/docs/ine_f11.pdf', '2025-02-15', '10:00:00', NULL, NULL),
(22, 3, 11, 'Titulo', '/docs/tit11.pdf', '2025-02-15', '10:05:00', NULL, NULL),
(23, 1, 12, 'INE Frente', '/docs/ine_f12.pdf', '2025-02-16', '10:00:00', NULL, NULL),
(24, 4, 12, 'CV', '/docs/cv12.pdf', '2025-02-16', '10:05:00', NULL, NULL),
(25, 1, 13, 'INE Frente', '/docs/ine_f13.pdf', '2025-02-17', '10:00:00', NULL, NULL),
(26, 2, 13, 'Comprobante', '/docs/comp13.pdf', '2025-02-17', '10:05:00', NULL, NULL),
(27, 1, 14, 'INE Frente', '/docs/ine_f14.pdf', '2025-02-18', '10:00:00', NULL, NULL),
(28, 3, 14, 'Titulo', '/docs/tit14.pdf', '2025-02-18', '10:05:00', NULL, NULL),
(29, 1, 15, 'INE Frente', '/docs/ine_f15.pdf', '2025-02-19', '10:00:00', NULL, NULL),
(30, 4, 15, 'CV', '/docs/cv15.pdf', '2025-02-19', '10:05:00', NULL, NULL);

-- Trabajos y Comunicaciones para complementar variedad en perfiles
INSERT INTO TRABAJO (TRAB_ID_TRABAJO, TRAB_ACTIVIDAD_VULNERABLE, TRAB_RESPONSABLE) VALUES 
(1, true, true), (2, false, false), (3, false, true);

INSERT INTO TRABAJO_PERSONA (TRPE_ID_TRABAJO, TRPE_ID_PERSONA) VALUES 
(1, 1), (2, 2), (3, 3), (1, 4), (2, 5), (3, 6);

INSERT INTO COMUNICACION (COMU_ID_PERSONA, COMU_ID_TIPO_COMUNICACION, COMU_DESCRIPCION) VALUES
(1, 1, 'carlos.garcia@mail.com'), (1, 2, '5551234567'),
(2, 1, 'maria.perez@mail.com'), (2, 3, '5559876543'),
(3, 1, 'juan.martinez@mail.com');

/* Los INSERT anteriores asignan identificadores explícitos. Se sincronizan
   todas las secuencias SERIAL para que las altas posteriores no reutilicen IDs. */
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
