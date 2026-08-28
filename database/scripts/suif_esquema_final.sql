/*==============================================================*/
/* SUIF — Esquema final                                         */
/*                                                              */
/* Reconstrucción del estado ACTUAL de la base en un solo       */
/* script, con todos los complementos ya incorporados: las      */
/* columnas nacen con el tipo y la obligatoriedad que tienen     */
/* hoy, y no queda ni un solo ALTER de migración.                */
/*                                                              */
/* Se diferencia de los otros dos archivos completos:            */
/*                                                              */
/*   suif.sql                      esquema base del 11/08/2026,  */
/*                                 empieza borrando la base.     */
/*   suif_instalacion_completa.sql los nueve scripts pegados en  */
/*                                 orden: el esquema base más    */
/*                                 sus parches, tal cual.        */
/*   suif_esquema_final.sql        ESTE: el resultado de correr  */
/*                                 esos nueve, ya aplanado.      */
/*                                                              */
/* SOLO para una base VACÍA. No hay ningún drop, así que corre   */
/* con ON_ERROR_STOP desde la primera línea:                     */
/*                                                              */
/*   psql -v ON_ERROR_STOP=1 --single-transaction \              */
/*        -h HOST -U suif -d suif -f suif_esquema_final.sql      */
/*                                                              */
/* Sobre una base con datos NO se usa: ahí siguen valiendo los   */
/* scripts numerados del README, que son idempotentes.           */
/*                                                              */
/* Contiene 36 tablas, sus índices, sus llaves foráneas y los    */
/* catálogos con los que el sistema arranca. No incluye          */
/* suif_lleno.sql (datos de prueba de desarrollo).               */
/*==============================================================*/


/*==============================================================*/
/* PRIMERA PARTE — TABLAS E ÍNDICES                             */
/*==============================================================*/

/*==============================================================*/
/* Table: AUTORIZACION_SOLICITUD                                */
/*==============================================================*/
create table AUTORIZACION_SOLICITUD (
   AUSO_ID_AUTORIZACION_SOLICITUD SERIAL               not null,
   AUSO_FECHA_ACEPTACION DATE                 null,
   AUSO_HORA_ACEPTACION TIME                 null,
   constraint PK_AUTORIZACION_SOLICITUD primary key (AUSO_ID_AUTORIZACION_SOLICITUD)
);

create unique index AUTORIZACION_SOLICITUD_PK on AUTORIZACION_SOLICITUD (
AUSO_ID_AUTORIZACION_SOLICITUD
);

/*==============================================================*/
/* Table: CERTIFICACION                                         */
/*==============================================================*/
create table CERTIFICACION (
   CERT_ID_CERTIFICACION SERIAL               not null,
   EVAL_ID_EVALUACION   INT4                 null,
   CERT_FECHA_EMISION   DATE                 not null,
   CERT_HORA_EMISION    TIME                 not null,
   CERT_ESTADO          BOOL                 not null,
   constraint PK_CERTIFICACION primary key (CERT_ID_CERTIFICACION)
);

create unique index CERTIFICACION_PK on CERTIFICACION (
CERT_ID_CERTIFICACION
);

/*==============================================================*/
/* Table: CODIGO_POSTAL                                         */
/*==============================================================*/
create table CODIGO_POSTAL (
   COPO_ID_CODIGO_POSTAL CHAR(5)              not null,
   constraint PK_CODIGO_POSTAL primary key (COPO_ID_CODIGO_POSTAL)
);

create unique index CODIGO_POSTAL_PK on CODIGO_POSTAL (
COPO_ID_CODIGO_POSTAL
);

/*==============================================================*/
/* Table: COMUNICACION                                          */
/*                                                              */
/* Los correos y el teléfono de la persona. El tipo sale de     */
/* TIPO_COMUNICACION: correo principal, alterno y celular.      */
/*==============================================================*/
create table COMUNICACION (
   COMU_ID_COMUNICACION SERIAL               not null,
   COMU_ID_PERSONA      INT4                 not null,
   COMU_ID_TIPO_COMUNICACION INT4                 not null,
   COMU_DESCRIPCION     VARCHAR(65)          not null,
   constraint PK_COMUNICACION primary key (COMU_ID_COMUNICACION)
);

create unique index COMUNICACION_PK on COMUNICACION (
COMU_ID_COMUNICACION
);

create  index COMUNICACION_FK on COMUNICACION (
COMU_ID_PERSONA
);

create  index COMUNICACION2_FK on COMUNICACION (
COMU_ID_TIPO_COMUNICACION
);

/*==============================================================*/
/* Table: CONVOCATORIA                                          */
/*==============================================================*/
create table CONVOCATORIA (
   CONV_ID_CONVOCATORIA SERIAL               not null,
   CONV_NOMBRE          VARCHAR(300)         not null,
   CONV_MONTO_RECUPERACION MONEY                not null,
   CONV_FECHA_INICIO_REGISTRO DATE                 not null,
   CONV_FECHA_FIN_REGISTRO DATE                 not null,
   CONV_FIN_FECHA_ENTREGA_DOCS DATE                 not null,
   CONV_FECHA_INICIO    DATE                 not null,
   CONV_FECHA_FIN       DATE                 not null,
   constraint PK_CONVOCATORIA primary key (CONV_ID_CONVOCATORIA)
);

create unique index CONVOCATORIA_PK on CONVOCATORIA (
CONV_ID_CONVOCATORIA
);

/*==============================================================*/
/* Table: C_ESTADO_CONVOCATORIA                                 */
/*==============================================================*/
create table C_ESTADO_CONVOCATORIA (
   ESCO_ID_C_ESTADO_CONVOCATORIA SERIAL               not null,
   ESCO_ESTADO_CONVOCATORIA VARCHAR(15)          not null,
   constraint PK_C_ESTADO_CONVOCATORIA primary key (ESCO_ID_C_ESTADO_CONVOCATORIA)
);

create unique index C_ESTADO_CONVOCATORIA_PK on C_ESTADO_CONVOCATORIA (
ESCO_ID_C_ESTADO_CONVOCATORIA
);

/*==============================================================*/
/* Table: C_ESTADO_DOCUMENTO                                    */
/*==============================================================*/
create table C_ESTADO_DOCUMENTO (
   ESDO_ID_C_ESTADO_DOCUMENTO SERIAL               not null,
   ESDO_ESTADO_DOCUMENTO VARCHAR(45)          not null,
   constraint PK_C_ESTADO_DOCUMENTO primary key (ESDO_ID_C_ESTADO_DOCUMENTO)
);

create unique index C_ESTADO_DOCUMENTO_PK on C_ESTADO_DOCUMENTO (
ESDO_ID_C_ESTADO_DOCUMENTO
);

/*==============================================================*/
/* Table: C_ESTADO_PAGO                                         */
/*==============================================================*/
create table C_ESTADO_PAGO (
   ESPA_ID_C_ESTADO_PAGO SERIAL               not null,
   ESTA_ESTADO_PAGO     VARCHAR(15)          not null,
   constraint PK_C_ESTADO_PAGO primary key (ESPA_ID_C_ESTADO_PAGO)
);

create unique index C_ESTADO_PAGO_PK on C_ESTADO_PAGO (
ESPA_ID_C_ESTADO_PAGO
);

/*==============================================================*/
/* Table: C_ESTADO_SOLICITUD                                    */
/*                                                              */
/* ESSO_ESTADO_SOLICITUD mide 45: en 15 no cabía               */
/* "Documentación en revisión". Antes del esquema del           */
/* 11/08/2026 la columna se llamaba ESSO_ESTATUS_SOLICITUD.     */
/*==============================================================*/
create table C_ESTADO_SOLICITUD (
   ESSO_ID_C_ESTADO_SOLICITUD SERIAL               not null,
   ESSO_ESTADO_SOLICITUD VARCHAR(45)          not null,
   constraint PK_C_ESTADO_SOLICITUD primary key (ESSO_ID_C_ESTADO_SOLICITUD)
);

create unique index ESTATUS_SOLICITUD_PK on C_ESTADO_SOLICITUD (
ESSO_ID_C_ESTADO_SOLICITUD
);

/*==============================================================*/
/* Table: DATO_FISCAL                                           */
/*                                                              */
/* Describe la facturación y cuelga de PAGO. El RFC de la       */
/* persona NO vive aquí, sino en PERSONA.PERS_RFC.              */
/*==============================================================*/
create table DATO_FISCAL (
   DAFI_ID_DATO_FISCAL  SERIAL               not null,
   DAFI_ID_REGIMEN_FISCAL INT4                 not null,
   DAFI_ID_CODIGO_POSTAL CHAR(5)              not null,
   DAFI_RAZON_SOCIAL    VARCHAR(35)          not null,
   DAFI_RFC             VARCHAR(13)          not null,
   DAFI_PERSONA_MORAL   BOOL                 not null,
   DAFI_USO_CFDI        BOOL                 not null,
   constraint PK_DATO_FISCAL primary key (DAFI_ID_DATO_FISCAL)
);

create unique index DATO_FISCAL_PK on DATO_FISCAL (
DAFI_ID_DATO_FISCAL
);

create  index RELATIONSHIP_22_FK on DATO_FISCAL (
DAFI_ID_REGIMEN_FISCAL
);

create  index RELATIONSHIP_23_FK on DATO_FISCAL (
DAFI_ID_CODIGO_POSTAL
);

/*==============================================================*/
/* Table: DOCUMENTO                                             */
/*                                                              */
/* DOCU_NOMBRE mide 150: es el nombre original del archivo que  */
/* sube la persona. uq_documento_solicitud_tipo deja un solo    */
/* documento por tipo en cada solicitud.                        */
/*==============================================================*/
create table DOCUMENTO (
   DOCU_ID_DOCUMENTO    SERIAL               not null,
   TIDO_ID_TIPO_DOCUMENTO INT4                 not null,
   SOLI_ID_SOLICITUD    INT4                 not null,
   DOCU_NOMBRE          VARCHAR(150)         not null,
   DOCU_PATH            VARCHAR(250)         not null,
   DOCU_FECHA_CARGA     DATE                 not null,
   DOCU_HORA_CARGA      TIME                 not null,
   DOCU_FECHA_AUTORIZACION DATE                 null,
   DOCU_HORA_AUTORIZACION TIME                 null,
   constraint PK_DOCUMENTO primary key (DOCU_ID_DOCUMENTO),
   constraint uq_documento_solicitud_tipo unique (SOLI_ID_SOLICITUD, TIDO_ID_TIPO_DOCUMENTO)
);

create unique index DOCUMENTO_PK on DOCUMENTO (
DOCU_ID_DOCUMENTO
);

create  index RELATIONSHIP_15_FK on DOCUMENTO (
TIDO_ID_TIPO_DOCUMENTO
);

create  index RELATIONSHIP_16_FK on DOCUMENTO (
SOLI_ID_SOLICITUD
);

/*==============================================================*/
/* Table: ENTIDAD_FEDERATIVA                                    */
/*==============================================================*/
create table ENTIDAD_FEDERATIVA (
   ENFE_CLAVE_INEGI     CHAR(3)              not null,
   ENFE_ENTIDAD_FEDERATIVA VARCHAR(20)          not null,
   constraint PK_ENTIDAD_FEDERATIVA primary key (ENFE_CLAVE_INEGI)
);

create unique index ENTIDAD_FEDERATIVA_PK on ENTIDAD_FEDERATIVA (
ENFE_CLAVE_INEGI
);

/*==============================================================*/
/* Table: ESTADO_CONVOCATORIA                                   */
/*==============================================================*/
create table ESTADO_CONVOCATORIA (
   ESCO_ID_ESTADO_CONVOCATORIA SERIAL               not null,
   ESCO_ID_C_ESTADO_CONVOCATORIA INT4                 not null,
   ESCO_ID_CONVOCATORIA INT4                 not null,
   ESCO_FECHA           DATE                 null,
   ESCO_HORA            TIME                 null,
   constraint PK_ESTADO_CONVOCATORIA primary key (ESCO_ID_ESTADO_CONVOCATORIA)
);

create unique index ESTADO_CONVOCATORIA_PK on ESTADO_CONVOCATORIA (
ESCO_ID_ESTADO_CONVOCATORIA
);

create  index ESTADO_CONVOCATORIA_FK on ESTADO_CONVOCATORIA (
ESCO_ID_C_ESTADO_CONVOCATORIA
);

create  index ESTADO_CONVOCATORIA2_FK on ESTADO_CONVOCATORIA (
ESCO_ID_CONVOCATORIA
);

/*==============================================================*/
/* Table: ESTADO_DOCUMENTO                                      */
/*                                                              */
/* ESDO_COMENTARIOS es opcional: cargar un documento no exige   */
/* comentario del revisor; rechazarlo sí lo lleva.              */
/*==============================================================*/
create table ESTADO_DOCUMENTO (
   ESDO_ID_ESTADO_DOCUMENTO SERIAL               not null,
   ESDO_ID_C_ESTADO_DOCUMENTO INT4                 not null,
   ESDO_ID_DOCUMENTO    INT4                 not null,
   ESDO_COMENTARIOS     TEXT                 null,
   ESDO_FECHA           DATE                 not null,
   ESDO_HORA            TIME                 not null,
   constraint PK_ESTADO_DOCUMENTO primary key (ESDO_ID_ESTADO_DOCUMENTO)
);

create unique index ESTADO_DOCUMENTO_PK on ESTADO_DOCUMENTO (
ESDO_ID_ESTADO_DOCUMENTO
);

create  index ESTADO_DOCUMENTO_FK on ESTADO_DOCUMENTO (
ESDO_ID_C_ESTADO_DOCUMENTO
);

create  index ESTADO_DOCUMENTO2_FK on ESTADO_DOCUMENTO (
ESDO_ID_DOCUMENTO
);

/*==============================================================*/
/* Table: ESTADO_PAGO                                           */
/*==============================================================*/
create table ESTADO_PAGO (
   ESPA_ID_ESTADO_PAGO  SERIAL               not null,
   ESPA_ID_PAGO         INT4                 not null,
   ESPA_ID_C_ESTADO_PAGO INT4                 not null,
   ESPA_FECHA           DATE                 null,
   ESPA_HORA            TIME                 not null,
   ESPA_COMENTARIO      TEXT                 null,
   constraint PK_ESTADO_PAGO primary key (ESPA_ID_ESTADO_PAGO)
);

create unique index ESTADO_PAGO_PK on ESTADO_PAGO (
ESPA_ID_ESTADO_PAGO
);

create  index ESTADO_PAGO_FK on ESTADO_PAGO (
ESPA_ID_PAGO
);

create  index ESTADO_PAGO2_FK on ESTADO_PAGO (
ESPA_ID_C_ESTADO_PAGO
);

/*==============================================================*/
/* Table: ESTADO_SOLICITUD                                      */
/*                                                              */
/* ESSO_MOTIVO_RECHAZO mide 255: en 35 no cabía una             */
/* explicación de rechazo útil para la persona.                 */
/*==============================================================*/
create table ESTADO_SOLICITUD (
   ESSO_ID_ESTADO_SOLICITUD SERIAL               not null,
   ESSO_ID_C_ESTADO_SOLICITUD INT4                 not null,
   ESSO_ID_SOLICITUD    INT4                 not null,
   ESSO_FECHA           DATE                 not null,
   ESSO_HORA            TIME                 not null,
   ESSO_MOTIVO_RECHAZO  VARCHAR(255)         null,
   constraint PK_ESTADO_SOLICITUD primary key (ESSO_ID_ESTADO_SOLICITUD)
);

create unique index ESTADO_SOLICITUD_PK on ESTADO_SOLICITUD (
ESSO_ID_ESTADO_SOLICITUD
);

create  index ESTADO_SOLICITUD_FK on ESTADO_SOLICITUD (
ESSO_ID_C_ESTADO_SOLICITUD
);

create  index ESTADO_SOLICITUD2_FK on ESTADO_SOLICITUD (
ESSO_ID_SOLICITUD
);

/*==============================================================*/
/* Table: EVALUACION                                            */
/*                                                              */
/* Desde el esquema del 11/08/2026 la programación del examen   */
/* NO vive aquí: la cadena es                                   */
/*                                                              */
/*   SEDE --< GRUPO (sede, fechas y horas) --< EVALUACION       */
/*                                                              */
/* y aquí sólo queda el resultado, que se captura después.      */
/* uq_evaluacion_grupo deja una evaluación por grupo: es contra */
/* ella que se cuenta el cupo de la sede.                       */
/*==============================================================*/
create table EVALUACION (
   EVAL_ID_EVALUACION   SERIAL               not null,
   GRUP_ID_GRUPO        INT4                 not null,
   EVAL_RESULTADO       INT4                 null,
   constraint PK_EVALUACION primary key (EVAL_ID_EVALUACION),
   constraint uq_evaluacion_grupo unique (GRUP_ID_GRUPO)
);

create unique index EVALUACION_PK on EVALUACION (
EVAL_ID_EVALUACION
);

/*==============================================================*/
/* Table: GRADO_PERSONA                                         */
/*==============================================================*/
create table GRADO_PERSONA (
   GRPE_ID_GRADO_PERSONA SERIAL               not null,
   GRPE_ID_NIVEL_PROFESIONAL INT4                 not null,
   GRPE_ID_PERSONA      INT4                 not null,
   constraint PK_GRADO_PERSONA primary key (GRPE_ID_GRADO_PERSONA)
);

create unique index GRADO_PERSONA_PK on GRADO_PERSONA (
GRPE_ID_GRADO_PERSONA
);

create  index GRADO_PERSONA_FK on GRADO_PERSONA (
GRPE_ID_NIVEL_PROFESIONAL
);

create  index GRADO_PERSONA2_FK on GRADO_PERSONA (
GRPE_ID_PERSONA
);

/*==============================================================*/
/* Table: GRUPO                                                 */
/*                                                              */
/* Cada aplicación del examen en una sede, con su horario. Una  */
/* sede aplica el examen UNA O MÁS veces: no hay restricción    */
/* única sobre SEDE_ID_SEDE. SEDE_CUPO es el aforo de cada      */
/* aplicación, no el total de la sede.                          */
/*==============================================================*/
create table GRUPO (
   GRUP_ID_GRUPO        SERIAL               not null,
   SEDE_ID_SEDE         INT4                 not null,
   GRUP_FECHA_INICIO    DATE                 not null,
   GRUP_FECHA_FIN       DATE                 not null,
   GRUP_HORA_INICIO     TIME                 not null,
   GRUP_HORA_FIN        TIME                 not null,
   constraint PK_GRUPO primary key (GRUP_ID_GRUPO)
);

/* Las consultas del catálogo recorren los grupos de cada sede
   ordenados por fecha y hora de inicio. */
create index idx_grupo_sede_inicio on GRUPO (
SEDE_ID_SEDE, GRUP_FECHA_INICIO, GRUP_HORA_INICIO
);

/*==============================================================*/
/* Table: NIVEL_PROFESIONAL                                     */
/*==============================================================*/
create table NIVEL_PROFESIONAL (
   NIPR_ID_NIVEL_PROFESIONAL SERIAL               not null,
   NIPR_NIVEL_PROFESIONAL VARCHAR(25)          not null,
   constraint PK_NIVEL_PROFESIONAL primary key (NIPR_ID_NIVEL_PROFESIONAL)
);

create unique index NIVEL_PROFESIONAL_PK on NIVEL_PROFESIONAL (
NIPR_ID_NIVEL_PROFESIONAL
);

/*==============================================================*/
/* Table: PAGO                                                  */
/*                                                              */
/* El renglón nace cuando se le ASIGNA la referencia bancaria a */
/* la persona, no cuando paga. En ese momento todavía no hay    */
/* datos fiscales, ni fecha ni hora de pago, ni comprobante:    */
/* esas cuatro columnas son opcionales y se llenan más adelante */
/* en el trámite. Lo único obligatorio al nacer es la           */
/* referencia y la ruta de su PDF.                              */
/*==============================================================*/
create table PAGO (
   PAGO_ID_PAGO         SERIAL               not null,
   PAGO_ID_DATO_FISCAL  INT4                 null,
   PAGO_REFERENCIA_BANCARIA VARCHAR(20)          not null,
   PAGO_REFERENCIA_BANCARIA_PATH VARCHAR(200)         not null,
   PAGO_MONTO_PAGADO    DECIMAL(10,4)        not null,
   PAGO_FECHA_PAGO      DATE                 null,
   PAGO_HORA_PAGO       TIME                 null,
   PAGO_COMPROBANTE_PATH VARCHAR(200)         null,
   PAGO_USO_CFDI        VARCHAR(25)          null,
   PAGO_NO_EMPLEADO     INT4                 null,
   constraint PK_PAGO primary key (PAGO_ID_PAGO)
);

create unique index PAGO_PK on PAGO (
PAGO_ID_PAGO
);

create  index RELATIONSHIP_20_FK on PAGO (
PAGO_ID_DATO_FISCAL
);

/*==============================================================*/
/* Table: PERSONA                                               */
/*                                                              */
/* PERS_RFC identifica a la persona igual que la CURP y se pide */
/* desde el pre-registro. DAFI_RFC, en DATO_FISCAL, describe la */
/* facturación y se conserva aparte.                            */
/*                                                              */
/* El DEFAULT '' viene de cuando la columna se agregó sobre una */
/* tabla con renglones: los anteriores al cambio quedaron sin   */
/* RFC y los nuevos siempre lo traen, porque la aplicación lo   */
/* exige.                                                       */
/*==============================================================*/
create table PERSONA (
   PERS_ID_PERSONA      SERIAL               not null,
   PERS_CLAVE_INEGI     CHAR(3)              not null,
   PERS_ID_USUARIO      INT4                 not null,
   PERS_CURP            VARCHAR(18)          not null,
   PERS_NOMBRE          VARCHAR(45)          not null,
   PERS_APELLIDO_PATERNO VARCHAR(45)          null,
   PERS_APELLIDO_MATERNO VARCHAR(45)          not null,
   PERS_FECHA_REGISTRO  DATE                 not null,
   PERS_RFC             VARCHAR(13)          not null default '',
   constraint PK_PERSONA primary key (PERS_ID_PERSONA)
);

create unique index PERSONA_PK on PERSONA (
PERS_ID_PERSONA
);

create  index RELATIONSHIP_2_FK on PERSONA (
PERS_CLAVE_INEGI
);

create  index RELATIONSHIP_5_FK on PERSONA (
PERS_ID_USUARIO
);

/* Dos personas no comparten RFC. El índice es parcial a propósito:
   un UNIQUE normal fallaría con más de un renglón heredado, porque
   todos comparten la cadena vacía. */
create unique index uq_persona_rfc on PERSONA (
PERS_RFC
) where PERS_RFC <> '';

/*==============================================================*/
/* Table: PRIVILEGIO                                            */
/*                                                              */
/* De aquí salen los permisos: la aplicación autoriza por       */
/* privilegio, no comparando el nombre del rol.                 */
/*==============================================================*/
create table PRIVILEGIO (
   PRIV_ID_PRIVILEGIO   SERIAL               not null,
   PRIV_PRIVILEGIO      VARCHAR(35)          not null,
   constraint PK_PRIVILEGIO primary key (PRIV_ID_PRIVILEGIO)
);

create unique index PRIVILEGIO_PK on PRIVILEGIO (
PRIV_ID_PRIVILEGIO
);

/*==============================================================*/
/* Table: PRIVILEGIO_ROL                                        */
/*==============================================================*/
create table PRIVILEGIO_ROL (
   ROPR_ID_PRIVILEGIO_ROL SERIAL               not null,
   ROPR_ID_PRIVILEGIO   INT4                 not null,
   ROPR_ID_ROL          INT4                 not null,
   constraint PK_PRIVILEGIO_ROL primary key (ROPR_ID_PRIVILEGIO_ROL)
);

create unique index PRIVILEGIO_ROL_PK on PRIVILEGIO_ROL (
ROPR_ID_PRIVILEGIO_ROL
);

create  index PRIVILEGIO_ROL_FK on PRIVILEGIO_ROL (
ROPR_ID_PRIVILEGIO
);

create  index PRIVILEGIO_ROL2_FK on PRIVILEGIO_ROL (
ROPR_ID_ROL
);

/*==============================================================*/
/* Table: REFERENCIA_BANCARIA                                   */
/*                                                              */
/* Catálogo de referencias que el administrador carga por CSV.  */
/* Cada renglón se entrega a una sola persona: al asignarse se  */
/* copia a PAGO (PAGO_REFERENCIA_BANCARIA y su PATH) y aquí se  */
/* guarda a qué PAGO quedó ligada. REBA_ID_PAGO es único, así   */
/* que la base misma impide que la misma referencia se reparta  */
/* dos veces aunque dos personas la pidan al mismo tiempo.      */
/*                                                              */
/* El archivo de la DEC trae dos fechas por referencia:         */
/* REBA_FECHA_EMISION es cuándo la emitió el banco y            */
/* REBA_VIGENCIA hasta cuándo vale. La emisión es opcional      */
/* porque los renglones cargados antes de ese cambio no la      */
/* tienen; que venga en el archivo lo exige el importador, no   */
/* el esquema.                                                  */
/*==============================================================*/
create table REFERENCIA_BANCARIA (
   REBA_ID_REFERENCIA_BANCARIA SERIAL               not null,
   REBA_ID_PAGO         INT4                 null,
   REBA_REFERENCIA      VARCHAR(20)          not null,
   REBA_PATH            VARCHAR(200)         null,
   REBA_MONTO           DECIMAL(10,4)        null,
   REBA_VIGENCIA        DATE                 null,
   REBA_FECHA_CARGA     DATE                 not null,
   REBA_HORA_CARGA      TIME                 not null,
   REBA_FECHA_ASIGNACION DATE                 null,
   REBA_HORA_ASIGNACION TIME                 null,
   REBA_FECHA_EMISION   DATE                 null,
   constraint PK_REFERENCIA_BANCARIA primary key (REBA_ID_REFERENCIA_BANCARIA)
);

create unique index REFERENCIA_BANCARIA_PK on REFERENCIA_BANCARIA (
REBA_ID_REFERENCIA_BANCARIA
);

/* El número de referencia no se repite dentro del catálogo:
   volver a cargar el mismo CSV actualiza, no duplica. */
create unique index REFERENCIA_BANCARIA_AK on REFERENCIA_BANCARIA (
REBA_REFERENCIA
);

/* Una referencia pertenece a un solo pago. */
create unique index REFERENCIA_BANCARIA_PAGO_AK on REFERENCIA_BANCARIA (
REBA_ID_PAGO
);

/*==============================================================*/
/* Table: REGIMEN_FISCAL                                        */
/*==============================================================*/
create table REGIMEN_FISCAL (
   REFI_ID_REGIMEN_FISCAL SERIAL               not null,
   REFI_REGIMEN_FISCAL  VARCHAR(35)          not null,
   constraint PK_REGIMEN_FISCAL primary key (REFI_ID_REGIMEN_FISCAL)
);

create unique index REGIMEN_FISCAL_PK on REGIMEN_FISCAL (
REFI_ID_REGIMEN_FISCAL
);

/*==============================================================*/
/* Table: REVOCACION                                            */
/*==============================================================*/
create table REVOCACION (
   REVO_ID_REVOCACION   SERIAL               not null,
   REVO_MOTIVO          VARCHAR(45)          not null,
   constraint PK_REVOCACION primary key (REVO_ID_REVOCACION)
);

create unique index REVOCACION_PK on REVOCACION (
REVO_ID_REVOCACION
);

/*==============================================================*/
/* Table: REVOCACION_CERTIFICADO                                */
/*==============================================================*/
create table REVOCACION_CERTIFICADO (
   RECE_ID_REVOCACION_CERTIFICADO SERIAL               not null,
   RECE_ID_REVOCACION   INT4                 not null,
   RECE_ID_CERTIFICACION INT4                 null,
   RECE_FECHA           DATE                 not null,
   RECE_HORA            TIME                 not null,
   RECE_NOMBRE_RESPONSABLE VARCHAR(25)          null,
   constraint PK_REVOCACION_CERTIFICADO primary key (RECE_ID_REVOCACION_CERTIFICADO)
);

create unique index REVOCACION_CERTIFICADO_PK on REVOCACION_CERTIFICADO (
RECE_ID_REVOCACION_CERTIFICADO
);

create  index REVOCACION_CERTIFICADO_FK on REVOCACION_CERTIFICADO (
RECE_ID_REVOCACION
);

create  index REVOCACION_CERTIFICADO2_FK on REVOCACION_CERTIFICADO (
RECE_ID_CERTIFICACION
);

/*==============================================================*/
/* Table: ROL                                                   */
/*                                                              */
/* ROL_TIPO_ROL mide 15 caracteres. Por eso los administradores */
/* de área se llaman "Admin UIF" y "Admin DEC": ampliar la      */
/* columna obligaría a reescribir la tabla.                     */
/*==============================================================*/
create table ROL (
   ROL_ID_ROL           SERIAL               not null,
   ROL_TIPO_ROL         VARCHAR(15)          not null,
   constraint PK_ROL primary key (ROL_ID_ROL)
);

create unique index ROL_PK on ROL (
ROL_ID_ROL
);

/*==============================================================*/
/* Table: SEDE                                                  */
/*                                                              */
/* SEDE_NOMBRE mide 150, que es lo que admite el formulario.    */
/* SEDE_CUPO es el aforo de CADA aplicación (cada GRUPO), no el */
/* total de la sede: la sala admite el mismo número de personas */
/* en cada sesión. SEDE_ESTADO dice si la sede ofrece cupo.     */
/*==============================================================*/
create table SEDE (
   SEDE_ID_SEDE         SERIAL               not null,
   SEDE_NOMBRE          VARCHAR(150)         not null,
   SEDE_DIRECCION       TEXT                 not null,
   SEDE_CUPO            INT4                 not null,
   SEDE_ESTADO          BOOL                 not null,
   constraint PK_SEDE primary key (SEDE_ID_SEDE)
);

create unique index SEDE_PK on SEDE (
SEDE_ID_SEDE
);

/*==============================================================*/
/* Table: SOLICITUD                                             */
/*==============================================================*/
create table SOLICITUD (
   SOLI_ID_SOLICITUD    SERIAL               not null,
   SOLI_ID_EVALUACION   INT4                 null,
   SOLI_ID_AUTORIZACION_SOLICITUD INT4                 null,
   SOLI_ID_PERSONA      INT4                 not null,
   SOLI_ID_CONVOCATORIA INT4                 not null,
   SOLI_ID_PAGO         INT4                 null,
   constraint PK_SOLICITUD primary key (SOLI_ID_SOLICITUD)
);

create unique index SOLICITUD_PK on SOLICITUD (
SOLI_ID_SOLICITUD
);

create  index RELATIONSHIP_7_FK on SOLICITUD (
SOLI_ID_PERSONA
);

create  index RELATIONSHIP_8_FK on SOLICITUD (
SOLI_ID_AUTORIZACION_SOLICITUD
);

create  index RELATIONSHIP_12_FK on SOLICITUD (
SOLI_ID_EVALUACION
);

create  index RELATIONSHIP_14_FK on SOLICITUD (
SOLI_ID_CONVOCATORIA
);

create  index RELATIONSHIP_18_FK on SOLICITUD (
SOLI_ID_PAGO
);

/*==============================================================*/
/* Table: TIPO_COMUNICACION                                     */
/*==============================================================*/
create table TIPO_COMUNICACION (
   TICO_ID_TIPO_COMUNICACION SERIAL               not null,
   TICO_TIPO_COMUNICACION VARCHAR(25)          not null,
   constraint PK_TIPO_COMUNICACION primary key (TICO_ID_TIPO_COMUNICACION)
);

create unique index TIPO_COMUNICACION_PK on TIPO_COMUNICACION (
TICO_ID_TIPO_COMUNICACION
);

/*==============================================================*/
/* Table: TIPO_DOCUMENTO                                        */
/*                                                              */
/* TIDO_TIPO_DOCUMENTO mide 60: "Autorización de la             */
/* publicación" son 30 caracteres.                              */
/*==============================================================*/
create table TIPO_DOCUMENTO (
   TIDO_ID_TIPO_DOCUMENTO SERIAL               not null,
   TIDO_TIPO_DOCUMENTO  VARCHAR(60)          not null,
   constraint PK_TIPO_DOCUMENTO primary key (TIDO_ID_TIPO_DOCUMENTO)
);

create unique index TIPO_DOCUMENTO_PK on TIPO_DOCUMENTO (
TIDO_ID_TIPO_DOCUMENTO
);

/*==============================================================*/
/* Table: TRABAJO                                               */
/*==============================================================*/
create table TRABAJO (
   TRAB_ID_TRABAJO      SERIAL               not null,
   TRAB_ACTIVIDAD_VULNERABLE BOOL                 not null,
   TRAB_RESPONSABLE     BOOL                 not null,
   constraint PK_TRABAJO primary key (TRAB_ID_TRABAJO)
);

create unique index TRABAJO_PK on TRABAJO (
TRAB_ID_TRABAJO
);

/*==============================================================*/
/* Table: TRABAJO_PERSONA                                       */
/*==============================================================*/
create table TRABAJO_PERSONA (
   TRPE_ID_TRABAJO_PERSONA SERIAL               not null,
   TRPE_ID_TRABAJO      INT4                 not null,
   TRPE_ID_PERSONA      INT4                 not null,
   constraint PK_TRABAJO_PERSONA primary key (TRPE_ID_TRABAJO_PERSONA)
);

create unique index TRABAJO_PERSONA_PK on TRABAJO_PERSONA (
TRPE_ID_TRABAJO_PERSONA
);

create  index TRABAJO_PERSONA_FK on TRABAJO_PERSONA (
TRPE_ID_TRABAJO
);

create  index TRABAJO_PERSONA2_FK on TRABAJO_PERSONA (
TRPE_ID_PERSONA
);

/*==============================================================*/
/* Table: USUARIO                                               */
/*                                                              */
/* USUA_CLAVE_ACCESO mide 255 porque guarda el hash de Laravel. */
/*                                                              */
/* USUA_ACTIVO es la baja lógica: dar de baja a un              */
/* administrador no borra su renglón, le retira el acceso.      */
/* PERSONA y USUARIO son el rastro de quién dictaminó cada      */
/* expediente.                                                  */
/*==============================================================*/
create table USUARIO (
   USUA_ID_USUARIO      SERIAL               not null,
   USUA_ID_ROL          INT4                 not null,
   USUA_CLAVE_ACCESO    VARCHAR(255)         not null,
   USUA_ACTIVO          BOOLEAN              not null default TRUE,
   constraint PK_USUARIO primary key (USUA_ID_USUARIO)
);

create unique index USUARIO_PK on USUARIO (
USUA_ID_USUARIO
);


/*==============================================================*/
/* SEGUNDA PARTE — LLAVES FORÁNEAS                              */
/*                                                              */
/* Nota para quien compare contra una base ya instalada: las    */
/* bases que llegaron aquí por parches cargan además una llave  */
/* redundante llamada fk_evaluacion_grupo, sobre la misma       */
/* columna que FK_EVALUACI_REFERENCE_GRUPO. La creó             */
/* suif_evaluacion_grupo.sql, que sólo preguntaba por su propio */
/* nombre. Es inofensiva y aquí no se reproduce.                */
/*==============================================================*/

alter table CERTIFICACION
   add constraint FK_CERTIFIC_REFERENCE_EVALUACI foreign key (EVAL_ID_EVALUACION)
      references EVALUACION (EVAL_ID_EVALUACION)
      on delete restrict on update restrict;

alter table COMUNICACION
   add constraint FK_COMUNICA_COMUNICAC_PERSONA foreign key (COMU_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table COMUNICACION
   add constraint FK_COMUNICA_COMUNICAC_TIPO_COM foreign key (COMU_ID_TIPO_COMUNICACION)
      references TIPO_COMUNICACION (TICO_ID_TIPO_COMUNICACION)
      on delete restrict on update restrict;

alter table DATO_FISCAL
   add constraint FK_DATO_FIS_RELATIONS_REGIMEN_ foreign key (DAFI_ID_REGIMEN_FISCAL)
      references REGIMEN_FISCAL (REFI_ID_REGIMEN_FISCAL)
      on delete restrict on update restrict;

alter table DATO_FISCAL
   add constraint FK_DATO_FIS_RELATIONS_CODIGO_P foreign key (DAFI_ID_CODIGO_POSTAL)
      references CODIGO_POSTAL (COPO_ID_CODIGO_POSTAL)
      on delete restrict on update restrict;

alter table DOCUMENTO
   add constraint FK_DOCUMENT_RELATIONS_TIPO_DOC foreign key (TIDO_ID_TIPO_DOCUMENTO)
      references TIPO_DOCUMENTO (TIDO_ID_TIPO_DOCUMENTO)
      on delete restrict on update restrict;

alter table DOCUMENTO
   add constraint FK_DOCUMENT_RELATIONS_SOLICITU foreign key (SOLI_ID_SOLICITUD)
      references SOLICITUD (SOLI_ID_SOLICITUD)
      on delete restrict on update restrict;

alter table ESTADO_CONVOCATORIA
   add constraint FK_ESTADO_C_ESTADO_CO_C_ESTADO foreign key (ESCO_ID_C_ESTADO_CONVOCATORIA)
      references C_ESTADO_CONVOCATORIA (ESCO_ID_C_ESTADO_CONVOCATORIA)
      on delete restrict on update restrict;

alter table ESTADO_CONVOCATORIA
   add constraint FK_ESTADO_C_ESTADO_CO_CONVOCAT foreign key (ESCO_ID_CONVOCATORIA)
      references CONVOCATORIA (CONV_ID_CONVOCATORIA)
      on delete restrict on update restrict;

alter table ESTADO_DOCUMENTO
   add constraint FK_ESTADO_D_ESTADO_DO_C_ESTADO foreign key (ESDO_ID_C_ESTADO_DOCUMENTO)
      references C_ESTADO_DOCUMENTO (ESDO_ID_C_ESTADO_DOCUMENTO)
      on delete restrict on update restrict;

alter table ESTADO_DOCUMENTO
   add constraint FK_ESTADO_D_ESTADO_DO_DOCUMENT foreign key (ESDO_ID_DOCUMENTO)
      references DOCUMENTO (DOCU_ID_DOCUMENTO)
      on delete restrict on update restrict;

alter table ESTADO_PAGO
   add constraint FK_ESTADO_P_ESTADO_PA_PAGO foreign key (ESPA_ID_PAGO)
      references PAGO (PAGO_ID_PAGO)
      on delete restrict on update restrict;

alter table ESTADO_PAGO
   add constraint FK_ESTADO_P_ESTADO_PA_C_ESTADO foreign key (ESPA_ID_C_ESTADO_PAGO)
      references C_ESTADO_PAGO (ESPA_ID_C_ESTADO_PAGO)
      on delete restrict on update restrict;

alter table ESTADO_SOLICITUD
   add constraint FK_ESTADO_S_ESTADO_SO_C_ESTADO foreign key (ESSO_ID_C_ESTADO_SOLICITUD)
      references C_ESTADO_SOLICITUD (ESSO_ID_C_ESTADO_SOLICITUD)
      on delete restrict on update restrict;

alter table ESTADO_SOLICITUD
   add constraint FK_ESTADO_S_ESTADO_SO_SOLICITU foreign key (ESSO_ID_SOLICITUD)
      references SOLICITUD (SOLI_ID_SOLICITUD)
      on delete restrict on update restrict;

alter table EVALUACION
   add constraint FK_EVALUACI_REFERENCE_GRUPO foreign key (GRUP_ID_GRUPO)
      references GRUPO (GRUP_ID_GRUPO)
      on delete restrict on update restrict;

alter table GRADO_PERSONA
   add constraint FK_GRADO_PE_GRADO_PER_NIVEL_PR foreign key (GRPE_ID_NIVEL_PROFESIONAL)
      references NIVEL_PROFESIONAL (NIPR_ID_NIVEL_PROFESIONAL)
      on delete restrict on update restrict;

alter table GRADO_PERSONA
   add constraint FK_GRADO_PE_GRADO_PER_PERSONA foreign key (GRPE_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table GRUPO
   add constraint FK_GRUPO_REFERENCE_SEDE foreign key (SEDE_ID_SEDE)
      references SEDE (SEDE_ID_SEDE)
      on delete restrict on update restrict;

alter table PAGO
   add constraint FK_PAGO_RELATIONS_DATO_FIS foreign key (PAGO_ID_DATO_FISCAL)
      references DATO_FISCAL (DAFI_ID_DATO_FISCAL)
      on delete restrict on update restrict;

alter table PERSONA
   add constraint FK_PERSONA_RELATIONS_ENTIDAD_ foreign key (PERS_CLAVE_INEGI)
      references ENTIDAD_FEDERATIVA (ENFE_CLAVE_INEGI)
      on delete restrict on update restrict;

alter table PERSONA
   add constraint FK_PERSONA_RELATIONS_USUARIO foreign key (PERS_ID_USUARIO)
      references USUARIO (USUA_ID_USUARIO)
      on delete restrict on update restrict;

alter table PRIVILEGIO_ROL
   add constraint FK_PRIVILEG_PRIVILEGI_PRIVILEG foreign key (ROPR_ID_PRIVILEGIO)
      references PRIVILEGIO (PRIV_ID_PRIVILEGIO)
      on delete restrict on update restrict;

alter table PRIVILEGIO_ROL
   add constraint FK_PRIVILEG_PRIVILEGI_ROL foreign key (ROPR_ID_ROL)
      references ROL (ROL_ID_ROL)
      on delete restrict on update restrict;

alter table REFERENCIA_BANCARIA
   add constraint fk_referencia_bancaria_pago foreign key (REBA_ID_PAGO)
      references PAGO (PAGO_ID_PAGO)
      on delete restrict on update restrict;

alter table REVOCACION_CERTIFICADO
   add constraint FK_REVOCACI_REVOCACIO_REVOCACI foreign key (RECE_ID_REVOCACION)
      references REVOCACION (REVO_ID_REVOCACION)
      on delete restrict on update restrict;

alter table REVOCACION_CERTIFICADO
   add constraint FK_REVOCACI_REVOCACIO_CERTIFIC foreign key (RECE_ID_CERTIFICACION)
      references CERTIFICACION (CERT_ID_CERTIFICACION)
      on delete restrict on update restrict;

alter table SOLICITUD
   add constraint FK_SOLICITU_RELATIONS_EVALUACI foreign key (SOLI_ID_EVALUACION)
      references EVALUACION (EVAL_ID_EVALUACION)
      on delete restrict on update restrict;

alter table SOLICITUD
   add constraint FK_SOLICITU_RELATIONS_CONVOCAT foreign key (SOLI_ID_CONVOCATORIA)
      references CONVOCATORIA (CONV_ID_CONVOCATORIA)
      on delete restrict on update restrict;

alter table SOLICITUD
   add constraint FK_SOLICITU_RELATIONS_PAGO foreign key (SOLI_ID_PAGO)
      references PAGO (PAGO_ID_PAGO)
      on delete restrict on update restrict;

alter table SOLICITUD
   add constraint FK_SOLICITU_RELATIONS_PERSONA foreign key (SOLI_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table SOLICITUD
   add constraint FK_SOLICITU_RELATIONS_AUTORIZA foreign key (SOLI_ID_AUTORIZACION_SOLICITUD)
      references AUTORIZACION_SOLICITUD (AUSO_ID_AUTORIZACION_SOLICITUD)
      on delete restrict on update restrict;

alter table TRABAJO_PERSONA
   add constraint FK_TRABAJO__TRABAJO_P_TRABAJO foreign key (TRPE_ID_TRABAJO)
      references TRABAJO (TRAB_ID_TRABAJO)
      on delete restrict on update restrict;

alter table TRABAJO_PERSONA
   add constraint FK_TRABAJO__TRABAJO_P_PERSONA foreign key (TRPE_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA)
      on delete restrict on update restrict;

alter table USUARIO
   add constraint FK_USUARIO_REFERENCE_ROL foreign key (USUA_ID_ROL)
      references ROL (ROL_ID_ROL)
      on delete restrict on update restrict;


/*==============================================================*/
/* TERCERA PARTE — CATÁLOGOS                                    */
/*                                                              */
/* Los identificadores van explícitos porque la aplicación y    */
/* los demás scripts los dan por fijos. Después de cada bloque  */
/* se alinea la secuencia, para que un alta posterior sin id no */
/* choque con una llave ya existente.                           */
/*==============================================================*/

/*--------------------------------------------------------------*/
/* Entidades federativas — claves oficiales INEGI                */
/*--------------------------------------------------------------*/
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
    ('032', 'Zacatecas');

/*--------------------------------------------------------------*/
/* Roles                                                         */
/*                                                              */
/* El rol 1 se llamó "Participante" y hoy es "Persona". El rol  */
/* 2 se llamó "Administrador" y hoy es "Superusuario": era el   */
/* único administrador y tenía todo el catálogo, así que con la */
/* separación por área pasó a ser el rol sin límites.           */
/*--------------------------------------------------------------*/
INSERT INTO rol (rol_id_rol, rol_tipo_rol) VALUES
    (1, 'Persona'),
    (2, 'Superusuario'),
    (3, 'Admin UIF'),
    (4, 'Admin DEC');

SELECT setval(pg_get_serial_sequence('rol', 'rol_id_rol'),
              (SELECT MAX(rol_id_rol) FROM rol));

/*--------------------------------------------------------------*/
/* Catálogo de privilegios                                       */
/*                                                              */
/* Sin estos renglones nadie tiene acceso a nada: la            */
/* aplicación autoriza cada módulo por privilegio.              */
/*--------------------------------------------------------------*/
INSERT INTO privilegio (priv_id_privilegio, priv_privilegio) VALUES
    (1, 'Validación Registro'),
    (2, 'Gestionar Pagos'),
    (3, 'Generación Reportes'),
    (4, 'Gestionar usuarios'),
    (5, 'Gestionar Referencias'),
    (6, 'Gestionar Sedes');

SELECT setval(pg_get_serial_sequence('privilegio', 'priv_id_privilegio'),
              (SELECT MAX(priv_id_privilegio) FROM privilegio));

/*--------------------------------------------------------------*/
/* Reparto de privilegios por rol                                */
/*                                                              */
/*   Superusuario  todo el catálogo                              */
/*   Admin UIF     pre-registro y documentación                  */
/*   Admin DEC     pagos y el catálogo de referencias, que es    */
/*                 la DEC quien lo emite                         */
/*--------------------------------------------------------------*/
INSERT INTO privilegio_rol (ropr_id_privilegio_rol, ropr_id_rol, ropr_id_privilegio) VALUES
    (1, 2, 1),   /* Superusuario — Validación Registro   */
    (2, 2, 2),   /* Superusuario — Gestionar Pagos       */
    (3, 2, 3),   /* Superusuario — Generación Reportes   */
    (4, 2, 4),   /* Superusuario — Gestionar usuarios    */
    (5, 2, 5),   /* Superusuario — Gestionar Referencias */
    (6, 2, 6),   /* Superusuario — Gestionar Sedes       */
    (7, 3, 1),   /* Admin UIF    — Validación Registro   */
    (8, 4, 2),   /* Admin DEC    — Gestionar Pagos       */
    (9, 4, 5);   /* Admin DEC    — Gestionar Referencias */

SELECT setval(pg_get_serial_sequence('privilegio_rol', 'ropr_id_privilegio_rol'),
              (SELECT MAX(ropr_id_privilegio_rol) FROM privilegio_rol));

/*--------------------------------------------------------------*/
/* Tipos de comunicación — correos y teléfono de la persona      */
/*--------------------------------------------------------------*/
INSERT INTO tipo_comunicacion (tico_id_tipo_comunicacion, tico_tipo_comunicacion) VALUES
    (1, 'Correo principal'),
    (2, 'Correo alterno'),
    (3, 'Teléfono celular');

SELECT setval(pg_get_serial_sequence('tipo_comunicacion', 'tico_id_tipo_comunicacion'),
              (SELECT MAX(tico_id_tipo_comunicacion) FROM tipo_comunicacion));

/*--------------------------------------------------------------*/
/* Niveles profesionales — último grado de estudios              */
/*--------------------------------------------------------------*/
INSERT INTO nivel_profesional (nipr_id_nivel_profesional, nipr_nivel_profesional) VALUES
    (1, 'Bachillerato'),
    (2, 'Licenciatura'),
    (3, 'Especialidad'),
    (4, 'Maestría'),
    (5, 'Doctorado');

SELECT setval(pg_get_serial_sequence('nivel_profesional', 'nipr_id_nivel_profesional'),
              (SELECT MAX(nipr_id_nivel_profesional) FROM nivel_profesional));

/*--------------------------------------------------------------*/
/* Tipos de documento del pre-registro                           */
/*--------------------------------------------------------------*/
INSERT INTO tipo_documento (tido_id_tipo_documento, tido_tipo_documento) VALUES
    (1, 'Solicitud firmada'),
    (2, 'Aceptación de notificaciones'),
    (3, 'Carta bajo protesta'),
    (4, 'Autorización de la publicación'),
    (5, 'CURP'),
    (6, 'Identificación oficial');

SELECT setval(pg_get_serial_sequence('tipo_documento', 'tido_id_tipo_documento'),
              (SELECT MAX(tido_id_tipo_documento) FROM tipo_documento));

/*--------------------------------------------------------------*/
/* Estados posibles de cada documento                            */
/*--------------------------------------------------------------*/
INSERT INTO c_estado_documento (esdo_id_c_estado_documento, esdo_estado_documento) VALUES
    (1, 'Pendiente'),
    (2, 'Cargado'),
    (3, 'En revisión'),
    (4, 'Aprobado'),
    (5, 'Rechazado');

SELECT setval(pg_get_serial_sequence('c_estado_documento', 'esdo_id_c_estado_documento'),
              (SELECT MAX(esdo_id_c_estado_documento) FROM c_estado_documento));

/*--------------------------------------------------------------*/
/* Estados posibles de la solicitud                              */
/*--------------------------------------------------------------*/
INSERT INTO c_estado_solicitud (esso_id_c_estado_solicitud, esso_estado_solicitud) VALUES
    (1, 'Pre-registro'),
    (2, 'Documentación'),
    (3, 'En revisión'),
    (4, 'Aprobada'),
    (5, 'Rechazada'),
    (6, 'Cancelada');

SELECT setval(pg_get_serial_sequence('c_estado_solicitud', 'esso_id_c_estado_solicitud'),
              (SELECT MAX(esso_id_c_estado_solicitud) FROM c_estado_solicitud));

/*--------------------------------------------------------------*/
/* Estados posibles del pago                                     */
/*                                                              */
/* Sin estos tres renglones la revisión del comprobante no      */
/* puede registrar nada.                                        */
/*--------------------------------------------------------------*/
INSERT INTO c_estado_pago (espa_id_c_estado_pago, esta_estado_pago) VALUES
    (1, 'Pendiente'),
    (2, 'Completado'),
    (3, 'Declinado');

SELECT setval(pg_get_serial_sequence('c_estado_pago', 'espa_id_c_estado_pago'),
              (SELECT MAX(espa_id_c_estado_pago) FROM c_estado_pago));

/*--------------------------------------------------------------*/
/* Convocatoria vigente                                          */
/*--------------------------------------------------------------*/
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
);

SELECT setval(pg_get_serial_sequence('convocatoria', 'conv_id_convocatoria'),
              (SELECT MAX(conv_id_convocatoria) FROM convocatoria));


/*==============================================================*/
/* COMPROBACIÓN                                                 */
/*                                                              */
/* tablas debe dar 36; el resto son los catálogos sembrados.    */
/*==============================================================*/
SELECT
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = current_schema()
        AND table_type = 'BASE TABLE')   AS tablas,
    (SELECT COUNT(*) FROM entidad_federativa) AS entidades,
    (SELECT COUNT(*) FROM rol)                AS roles,
    (SELECT COUNT(*) FROM privilegio)         AS privilegios,
    (SELECT COUNT(*) FROM privilegio_rol)     AS privilegios_por_rol,
    (SELECT COUNT(*) FROM tipo_documento)     AS tipos_documento,
    (SELECT COUNT(*) FROM c_estado_pago)      AS estados_pago,
    (SELECT COUNT(*) FROM convocatoria)       AS convocatorias;
