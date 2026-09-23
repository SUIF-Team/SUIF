/*==============================================================*/
/* suif_limpia_datos.sql                                        */
/*                                                              */
/* Vacía los datos capturados y deja en pie los catálogos, las  */
/* convocatorias y las cuentas administrativas.                 */
/*                                                              */
/* NO va en el orden de instalación de README.md. Es una        */
/* herramienta de operación, como                               */
/* suif_reconstruye_tablas_perdidas.sql, y se corre a mano      */
/* cuando se decide reiniciar el padrón.                        */
/*                                                              */
/* ESTE SCRIPT BORRA DATOS Y NO SE PUEDE DESHACER.              */
/* Respaldar primero, sin excepción:                            */
/*                                                              */
/*   pg_dump -h HOST -U suif -d suif -Fc -f respaldo_AAAA-MM-DD.dump */
/*                                                              */
/* Y ensayarlo sobre una base temporal restaurada de ese dump   */
/* antes de tocar producción (ver deploy/README.md).            */
/*                                                              */
/*   psql -v ON_ERROR_STOP=1 -h HOST -U suif -d suif \          */
/*        -f suif_limpia_datos.sql                              */
/*                                                              */
/* SIN --single-transaction: este script trae su propio BEGIN/  */
/* COMMIT. La bandera abriría una transacción de más y psql     */
/* avisaría dos veces («already a transaction in progress» y    */
/* «no transaction in progress»). Con ON_ERROR_STOP basta: un   */
/* error corta la sesión antes del COMMIT y todo se revierte.   */
/*                                                              */
/* Qué SOBREVIVE:                                               */
/*  - Los doce catálogos que siembran los scripts idempotentes: */
/*    ENTIDAD_FEDERATIVA, ROL, PRIVILEGIO, PRIVILEGIO_ROL,      */
/*    TIPO_COMUNICACION, NIVEL_PROFESIONAL, TIPO_DOCUMENTO,     */
/*    C_ESTADO_DOCUMENTO, C_ESTADO_SOLICITUD, C_ESTADO_PAGO,    */
/*    REGIMEN_FISCAL y C_ESTADO_CONVOCATORIA.                   */
/*  - CONVOCATORIA y su bitácora ESTADO_CONVOCATORIA. Sin ellas */
/*    el pre-registro no encuentra convocatoria vigente y deja  */
/*    de funcionar en silencio.                                 */
/*  - CODIGO_POSTAL, que el sistema llena solo conforme factura;*/
/*    vaciarlo no gana nada.                                    */
/*  - Las cuentas administrativas (todo rol distinto de         */
/*    'Persona') con su PERSONA y sus COMUNICACION, para no     */
/*    tener que volver a correr `php artisan suif:crear-admin`. */
/*                                                              */
/* SEDE y REFERENCIA_BANCARIA SÍ se borran: las sedes se        */
/* recapturan y las referencias se recargan del CSV de la DEC.  */
/*                                                              */
/* Lo que NO hace: borrar los archivos subidos. Quedan          */
/* huérfanos en storage/app/private/{documentos,comprobantes,   */
/* referencias,certificados,facturas,legacy},                   */
/* storage/app/preregistro y storage/app/referencias. Si se     */
/* quieren limpiar, es un paso manual aparte.                   */
/*==============================================================*/

BEGIN;

/*==============================================================*/
/* 1. Tablas que se vacían completas                            */
/*                                                              */
/* Van todas en un solo TRUNCATE: así Postgres resuelve entre   */
/* ellas las llaves foráneas ON DELETE RESTRICT sin necesidad   */
/* de CASCADE. Ninguna tabla de fuera de esta lista las         */
/* referencia, así que el borrado no se propaga.                */
/*                                                              */
/* NO agregar CASCADE: arrastraría PERSONA y USUARIO y se       */
/* llevaría por delante a los administradores.                  */
/*==============================================================*/

TRUNCATE TABLE
    estado_documento,
    estado_solicitud,
    estado_pago,
    revocacion_certificado,
    documento,
    certificacion,
    solicitud,
    referencia_bancaria,
    evaluacion,
    autorizacion_solicitud,
    pago,
    grupo,
    sede,
    dato_fiscal,
    revocacion
RESTART IDENTITY;

/*==============================================================*/
/* 2. Personas participantes                                    */
/*                                                              */
/* Aquí no se puede truncar: PERSONA y USUARIO guardan también  */
/* a los administradores. Se borra por rol, y por el nombre del */
/* rol y no por su id, que es como autoriza el resto del        */
/* sistema.                                                     */
/*                                                              */
/* El orden es el de las llaves foráneas: primero lo que cuelga */
/* de PERSONA, luego PERSONA, y hasta el final USUARIO, al que  */
/* PERSONA apunta.                                              */
/*                                                              */
/* Las secuencias de PERSONA y USUARIO no se reinician: los     */
/* administradores conservan sus identificadores.               */
/*==============================================================*/

CREATE TEMP TABLE participantes ON COMMIT DROP AS
SELECT p.pers_id_persona, p.pers_id_usuario
  FROM persona p
  JOIN usuario u ON u.usua_id_usuario = p.pers_id_usuario
  JOIN rol     r ON r.rol_id_rol      = u.usua_id_rol
 WHERE r.rol_tipo_rol = 'Persona';

DELETE FROM comunicacion
 WHERE comu_id_persona IN (SELECT pers_id_persona FROM participantes);

DELETE FROM grado_persona
 WHERE grpe_id_persona IN (SELECT pers_id_persona FROM participantes);

DELETE FROM trabajo_persona
 WHERE trpe_id_persona IN (SELECT pers_id_persona FROM participantes);

/* TRABAJO no apunta a PERSONA: la liga es TRABAJO_PERSONA. Al  */
/* borrar esos renglones el trabajo se queda sin dueño.         */
DELETE FROM trabajo t
 WHERE NOT EXISTS (
       SELECT 1 FROM trabajo_persona tp
        WHERE tp.trpe_id_trabajo = t.trab_id_trabajo
 );

DELETE FROM persona
 WHERE pers_id_persona IN (SELECT pers_id_persona FROM participantes);

DELETE FROM usuario
 WHERE usua_id_usuario IN (SELECT pers_id_usuario FROM participantes);

COMMIT;

/*==============================================================*/
/* 3. Verificación                                              */
/*                                                              */
/* Lo marcado BORRADO debe quedar en cero; lo marcado CONSERVA  */
/* debe traer renglones.                                        */
/*==============================================================*/

  SELECT 'BORRADO  solicitud'            AS tabla, count(*) FROM solicitud
UNION ALL SELECT 'BORRADO  documento',            count(*) FROM documento
UNION ALL SELECT 'BORRADO  pago',                 count(*) FROM pago
UNION ALL SELECT 'BORRADO  dato_fiscal',          count(*) FROM dato_fiscal
UNION ALL SELECT 'BORRADO  evaluacion',           count(*) FROM evaluacion
UNION ALL SELECT 'BORRADO  grupo',                count(*) FROM grupo
UNION ALL SELECT 'BORRADO  sede',                 count(*) FROM sede
UNION ALL SELECT 'BORRADO  referencia_bancaria',  count(*) FROM referencia_bancaria
UNION ALL SELECT 'BORRADO  persona (Persona)',    count(*)
       FROM persona p
       JOIN usuario u ON u.usua_id_usuario = p.pers_id_usuario
       JOIN rol     r ON r.rol_id_rol      = u.usua_id_rol
      WHERE r.rol_tipo_rol = 'Persona'
UNION ALL SELECT 'CONSERVA persona (admins)',     count(*)
       FROM persona p
       JOIN usuario u ON u.usua_id_usuario = p.pers_id_usuario
       JOIN rol     r ON r.rol_id_rol      = u.usua_id_rol
      WHERE r.rol_tipo_rol <> 'Persona'
UNION ALL SELECT 'CONSERVA convocatoria',         count(*) FROM convocatoria
UNION ALL SELECT 'CONSERVA estado_convocatoria',  count(*) FROM estado_convocatoria
UNION ALL SELECT 'CONSERVA rol',                  count(*) FROM rol
UNION ALL SELECT 'CONSERVA privilegio',           count(*) FROM privilegio
UNION ALL SELECT 'CONSERVA privilegio_rol',       count(*) FROM privilegio_rol
UNION ALL SELECT 'CONSERVA entidad_federativa',   count(*) FROM entidad_federativa
UNION ALL SELECT 'CONSERVA tipo_documento',       count(*) FROM tipo_documento
UNION ALL SELECT 'CONSERVA tipo_comunicacion',    count(*) FROM tipo_comunicacion
UNION ALL SELECT 'CONSERVA nivel_profesional',    count(*) FROM nivel_profesional
UNION ALL SELECT 'CONSERVA regimen_fiscal',       count(*) FROM regimen_fiscal
UNION ALL SELECT 'CONSERVA c_estado_documento',   count(*) FROM c_estado_documento
UNION ALL SELECT 'CONSERVA c_estado_solicitud',   count(*) FROM c_estado_solicitud
UNION ALL SELECT 'CONSERVA c_estado_pago',        count(*) FROM c_estado_pago
UNION ALL SELECT 'CONSERVA c_estado_convocatoria',count(*) FROM c_estado_convocatoria;
