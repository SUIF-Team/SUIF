/*==============================================================*/
/* DBMS name:      PostgreSQL 8                                 */
/* Created on:     31/07/2026 11:13:46 a. m.                    */
/*==============================================================*/


drop index AUTORIZACION_SOLICITUD_PK;

drop table AUTORIZACION_SOLICITUD;

drop index CERTIFICACION_PK;

drop table CERTIFICACION;

drop index CODIGO_POSTAL_PK;

drop table CODIGO_POSTAL;

drop index COMUNICACION2_FK;

drop index COMUNICACION_FK;

drop index COMUNICACION_PK;

drop table COMUNICACION;

drop index CONVOCATORIA_PK;

drop table CONVOCATORIA;

drop index C_ESTADO_CONVOCATORIA_PK;

drop table C_ESTADO_CONVOCATORIA;

drop index C_ESTADO_DOCUMENTO_PK;

drop table C_ESTADO_DOCUMENTO;

drop index C_ESTADO_PAGO_PK;

drop table C_ESTADO_PAGO;

drop index ESTATUS_SOLICITUD_PK;

drop table C_ESTADO_SOLICITUD;

drop index RELATIONSHIP_23_FK;

drop index RELATIONSHIP_22_FK;

drop index DATO_FISCAL_PK;

drop table DATO_FISCAL;

drop index RELATIONSHIP_16_FK;

drop index RELATIONSHIP_15_FK;

drop index DOCUMENTO_PK;

drop table DOCUMENTO;

drop index ENTIDAD_FEDERATIVA_PK;

drop table ENTIDAD_FEDERATIVA;

drop index ESTADO_CONVOCATORIA2_FK;

drop index ESTADO_CONVOCATORIA_FK;

drop index ESTADO_CONVOCATORIA_PK;

drop table ESTADO_CONVOCATORIA;

drop index ESTADO_DOCUMENTO2_FK;

drop index ESTADO_DOCUMENTO_FK;

drop index ESTADO_DOCUMENTO_PK;

drop table ESTADO_DOCUMENTO;

drop index ESTADO_PAGO2_FK;

drop index ESTADO_PAGO_FK;

drop index ESTADO_PAGO_PK;

drop table ESTADO_PAGO;

drop index ESTADO_SOLICITUD2_FK;

drop index ESTADO_SOLICITUD_FK;

drop index ESTADO_SOLICITUD_PK;

drop table ESTADO_SOLICITUD;

drop index RELATIONSHIP_13_FK;

drop index EVALUACION_PK;

drop table EVALUACION;

drop index GRADO_PERSONA2_FK;

drop index GRADO_PERSONA_FK;

drop index GRADO_PERSONA_PK;

drop table GRADO_PERSONA;

drop index NIVEL_PROFESIONAL_PK;

drop table NIVEL_PROFESIONAL;

drop index RELATIONSHIP_20_FK;

drop index PAGO_PK;

drop table PAGO;

drop index RELATIONSHIP_5_FK;

drop index RELATIONSHIP_2_FK;

drop index PERSONA_PK;

drop table PERSONA;

drop index PRIVILEGIO_PK;

drop table PRIVILEGIO;

drop index PRIVILEGIO_ROL2_FK;

drop index PRIVILEGIO_ROL_FK;

drop index PRIVILEGIO_ROL_PK;

drop table PRIVILEGIO_ROL;

drop index REGIMEN_FISCAL_PK;

drop table REGIMEN_FISCAL;

drop index REVOCACION_PK;

drop table REVOCACION;

drop index REVOCACION_CERTIFICADO2_FK;

drop index REVOCACION_CERTIFICADO_FK;

drop index REVOCACION_CERTIFICADO_PK;

drop table REVOCACION_CERTIFICADO;

drop index ROL_PK;

drop table ROL;

drop index SEDE_PK;

drop table SEDE;

drop index RELATIONSHIP_19_FK;

drop index RELATIONSHIP_18_FK;

drop index RELATIONSHIP_14_FK;

drop index RELATIONSHIP_12_FK;

drop index RELATIONSHIP_8_FK;

drop index RELATIONSHIP_7_FK;

drop index SOLICITUD_PK;

drop table SOLICITUD;

drop index TIPO_COMUNICACION_PK;

drop table TIPO_COMUNICACION;

drop index TIPO_DOCUMENTO_PK;

drop table TIPO_DOCUMENTO;

drop index TRABAJO_PK;

drop table TRABAJO;

drop index TRABAJO_PERSONA2_FK;

drop index TRABAJO_PERSONA_FK;

drop index TRABAJO_PERSONA_PK;

drop table TRABAJO_PERSONA;

drop index USUARIO_PK;

drop table USUARIO;

/*==============================================================*/
/* Table: AUTORIZACION_SOLICITUD                                */
/*==============================================================*/
create table AUTORIZACION_SOLICITUD (
   AUSO_ID_AUTORIZACION_SOLICITUD SERIAL               not null,
   AUSO_FECHA_ACEPTACION DATE                 null,
   AUSO_HORA_ACEPTACION TIME                 null,
   constraint PK_AUTORIZACION_SOLICITUD primary key (AUSO_ID_AUTORIZACION_SOLICITUD)
);

/*==============================================================*/
/* Index: AUTORIZACION_SOLICITUD_PK                             */
/*==============================================================*/
create unique index AUTORIZACION_SOLICITUD_PK on AUTORIZACION_SOLICITUD (
AUSO_ID_AUTORIZACION_SOLICITUD
);

/*==============================================================*/
/* Table: CERTIFICACION                                         */
/*==============================================================*/
create table CERTIFICACION (
   CERT_ID_CERTIFICACION SERIAL               not null,
   CERT_FECHA_EMISION   DATE                 not null,
   CERT_HORA_EMISION    TIME                 not null,
   CERT_ESTADO          BOOL                 not null,
   constraint PK_CERTIFICACION primary key (CERT_ID_CERTIFICACION)
);

/*==============================================================*/
/* Index: CERTIFICACION_PK                                      */
/*==============================================================*/
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

/*==============================================================*/
/* Index: CODIGO_POSTAL_PK                                      */
/*==============================================================*/
create unique index CODIGO_POSTAL_PK on CODIGO_POSTAL (
COPO_ID_CODIGO_POSTAL
);

/*==============================================================*/
/* Table: COMUNICACION                                          */
/*==============================================================*/
create table COMUNICACION (
   COMU_ID_COMUNICACION SERIAL               not null,
   COMU_ID_PERSONA      INT4                 not null,
   COMU_ID_TIPO_COMUNICACION INT4                 not null,
   COMU_DESCRIPCION     VARCHAR(65)          not null,
   constraint PK_COMUNICACION primary key (COMU_ID_COMUNICACION)
);

/*==============================================================*/
/* Index: COMUNICACION_PK                                       */
/*==============================================================*/
create unique index COMUNICACION_PK on COMUNICACION (
COMU_ID_COMUNICACION
);

/*==============================================================*/
/* Index: COMUNICACION_FK                                       */
/*==============================================================*/
create  index COMUNICACION_FK on COMUNICACION (
COMU_ID_PERSONA
);

/*==============================================================*/
/* Index: COMUNICACION2_FK                                      */
/*==============================================================*/
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

/*==============================================================*/
/* Index: CONVOCATORIA_PK                                       */
/*==============================================================*/
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

/*==============================================================*/
/* Index: C_ESTADO_CONVOCATORIA_PK                              */
/*==============================================================*/
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

/*==============================================================*/
/* Index: C_ESTADO_DOCUMENTO_PK                                 */
/*==============================================================*/
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

/*==============================================================*/
/* Index: C_ESTADO_PAGO_PK                                      */
/*==============================================================*/
create unique index C_ESTADO_PAGO_PK on C_ESTADO_PAGO (
ESPA_ID_C_ESTADO_PAGO
);

/*==============================================================*/
/* Table: C_ESTADO_SOLICITUD                                    */
/*==============================================================*/
create table C_ESTADO_SOLICITUD (
   ESSO_ID_C_ESTADO_SOLICITUD SERIAL               not null,
   ESSO_ESTATUS_SOLICITUD VARCHAR(15)          not null,
   constraint PK_C_ESTADO_SOLICITUD primary key (ESSO_ID_C_ESTADO_SOLICITUD)
);

/*==============================================================*/
/* Index: ESTATUS_SOLICITUD_PK                                  */
/*==============================================================*/
create unique index ESTATUS_SOLICITUD_PK on C_ESTADO_SOLICITUD (
ESSO_ID_C_ESTADO_SOLICITUD
);

/*==============================================================*/
/* Table: DATO_FISCAL                                           */
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

/*==============================================================*/
/* Index: DATO_FISCAL_PK                                        */
/*==============================================================*/
create unique index DATO_FISCAL_PK on DATO_FISCAL (
DAFI_ID_DATO_FISCAL
);

/*==============================================================*/
/* Index: RELATIONSHIP_22_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_22_FK on DATO_FISCAL (
DAFI_ID_REGIMEN_FISCAL
);

/*==============================================================*/
/* Index: RELATIONSHIP_23_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_23_FK on DATO_FISCAL (
DAFI_ID_CODIGO_POSTAL
);

/*==============================================================*/
/* Table: DOCUMENTO                                             */
/*==============================================================*/
create table DOCUMENTO (
   DOCU_ID_DOCUMENTO    SERIAL               not null,
   TIDO_ID_TIPO_DOCUMENTO INT4                 not null,
   SOLI_ID_SOLICITUD    INT4                 not null,
   DOCU_NOMBRE          VARCHAR(30)          not null,
   DOCU_PATH            VARCHAR(250)         not null,
   DOCU_FECHA_CARGA     DATE                 not null,
   DOCU_HORA_CARGA      TIME                 not null,
   DOCU_FECHA_AUTORIZACION DATE                 null,
   DOCU_HORA_AUTORIZACION TIME                 null,
   constraint PK_DOCUMENTO primary key (DOCU_ID_DOCUMENTO)
);

/*==============================================================*/
/* Index: DOCUMENTO_PK                                          */
/*==============================================================*/
create unique index DOCUMENTO_PK on DOCUMENTO (
DOCU_ID_DOCUMENTO
);

/*==============================================================*/
/* Index: RELATIONSHIP_15_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_15_FK on DOCUMENTO (
TIDO_ID_TIPO_DOCUMENTO
);

/*==============================================================*/
/* Index: RELATIONSHIP_16_FK                                    */
/*==============================================================*/
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

/*==============================================================*/
/* Index: ENTIDAD_FEDERATIVA_PK                                 */
/*==============================================================*/
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

/*==============================================================*/
/* Index: ESTADO_CONVOCATORIA_PK                                */
/*==============================================================*/
create unique index ESTADO_CONVOCATORIA_PK on ESTADO_CONVOCATORIA (
ESCO_ID_ESTADO_CONVOCATORIA
);

/*==============================================================*/
/* Index: ESTADO_CONVOCATORIA_FK                                */
/*==============================================================*/
create  index ESTADO_CONVOCATORIA_FK on ESTADO_CONVOCATORIA (
ESCO_ID_C_ESTADO_CONVOCATORIA
);

/*==============================================================*/
/* Index: ESTADO_CONVOCATORIA2_FK                               */
/*==============================================================*/
create  index ESTADO_CONVOCATORIA2_FK on ESTADO_CONVOCATORIA (
ESCO_ID_CONVOCATORIA
);

/*==============================================================*/
/* Table: ESTADO_DOCUMENTO                                      */
/*==============================================================*/
create table ESTADO_DOCUMENTO (
   ESDO_ID_ESTADO_DOCUMENTO SERIAL               not null,
   ESDO_ID_C_ESTADO_DOCUMENTO INT4                 not null,
   ESDO_ID_DOCUMENTO    INT4                 not null,
   ESDO_COMENTARIOS     TEXT                 not null,
   ESDO_FECHA           DATE                 not null,
   ESDO_HORA            TIME                 not null,
   constraint PK_ESTADO_DOCUMENTO primary key (ESDO_ID_ESTADO_DOCUMENTO)
);

/*==============================================================*/
/* Index: ESTADO_DOCUMENTO_PK                                   */
/*==============================================================*/
create unique index ESTADO_DOCUMENTO_PK on ESTADO_DOCUMENTO (
ESDO_ID_ESTADO_DOCUMENTO
);

/*==============================================================*/
/* Index: ESTADO_DOCUMENTO_FK                                   */
/*==============================================================*/
create  index ESTADO_DOCUMENTO_FK on ESTADO_DOCUMENTO (
ESDO_ID_C_ESTADO_DOCUMENTO
);

/*==============================================================*/
/* Index: ESTADO_DOCUMENTO2_FK                                  */
/*==============================================================*/
create  index ESTADO_DOCUMENTO2_FK on ESTADO_DOCUMENTO (
ESDO_ID_DOCUMENTO
);

/*==============================================================*/
/* Table: ESTADO_PAGO                                           */
/*==============================================================*/
create table ESTADO_PAGO (
   ESPA_ID_ESTADO_PAGO  SERIAL               not null,
   ESPA_ID_PAGO  INT4                 not null,
   ESPA_ID_C_ESTADO_PAGO INT4                 not null,
   ESPA_FECHA           DATE                 null,
   ESPA_HORA            TIME                 not null,
   constraint PK_ESTADO_PAGO primary key (ESPA_ID_ESTADO_PAGO)
);

/*==============================================================*/
/* Index: ESTADO_PAGO_PK                                        */
/*==============================================================*/
create unique index ESTADO_PAGO_PK on ESTADO_PAGO (
ESPA_ID_ESTADO_PAGO
);

/*==============================================================*/
/* Index: ESTADO_PAGO_FK                                        */
/*==============================================================*/
create  index ESTADO_PAGO_FK on ESTADO_PAGO (
ESPA_ID_PAGO
);

/*==============================================================*/
/* Index: ESTADO_PAGO2_FK                                       */
/*==============================================================*/
create  index ESTADO_PAGO2_FK on ESTADO_PAGO (
ESPA_ID_C_ESTADO_PAGO
);

/*==============================================================*/
/* Table: ESTADO_SOLICITUD                                      */
/*==============================================================*/
create table ESTADO_SOLICITUD (
   ESSO_ID_ESTADO_SOLICITUD SERIAL               not null,
   ESSO_ID_C_ESTADO_SOLICITUD INT4                 not null,
   ESSO_ID_SOLICITUD    INT4                 not null,
   ESSO_FECHA           DATE                 not null,
   ESSO_HORA            TIME                 not null,
   ESSO_MOTIVO_RECHAZO  VARCHAR(35)          null,
   constraint PK_ESTADO_SOLICITUD primary key (ESSO_ID_ESTADO_SOLICITUD)
);

/*==============================================================*/
/* Index: ESTADO_SOLICITUD_PK                                   */
/*==============================================================*/
create unique index ESTADO_SOLICITUD_PK on ESTADO_SOLICITUD (
ESSO_ID_ESTADO_SOLICITUD
);

/*==============================================================*/
/* Index: ESTADO_SOLICITUD_FK                                   */
/*==============================================================*/
create  index ESTADO_SOLICITUD_FK on ESTADO_SOLICITUD (
ESSO_ID_C_ESTADO_SOLICITUD
);

/*==============================================================*/
/* Index: ESTADO_SOLICITUD2_FK                                  */
/*==============================================================*/
create  index ESTADO_SOLICITUD2_FK on ESTADO_SOLICITUD (
ESSO_ID_SOLICITUD
);

/*==============================================================*/
/* Table: EVALUACION                                            */
/*==============================================================*/
create table EVALUACION (
   EVAL_ID_EVALUACION   SERIAL               not null,
   EVAL_ID_SEDE         INT4                 not null,
   EVAL_FECHA_INICIO    DATE                 not null,
   EVAL_HORA_INICIO     TIME                 not null,
   EVAL_FECHA_FIN       DATE                 not null,
   EVAL_HORA_FIN        TIME                 not null,
   EVAL_RESULTADO       INT4                 not null,
   constraint PK_EVALUACION primary key (EVAL_ID_EVALUACION)
);

/*==============================================================*/
/* Index: EVALUACION_PK                                         */
/*==============================================================*/
create unique index EVALUACION_PK on EVALUACION (
EVAL_ID_EVALUACION
);

/*==============================================================*/
/* Index: RELATIONSHIP_13_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_13_FK on EVALUACION (
EVAL_ID_SEDE
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

/*==============================================================*/
/* Index: GRADO_PERSONA_PK                                      */
/*==============================================================*/
create unique index GRADO_PERSONA_PK on GRADO_PERSONA (
GRPE_ID_GRADO_PERSONA
);

/*==============================================================*/
/* Index: GRADO_PERSONA_FK                                      */
/*==============================================================*/
create  index GRADO_PERSONA_FK on GRADO_PERSONA (
GRPE_ID_NIVEL_PROFESIONAL
);

/*==============================================================*/
/* Index: GRADO_PERSONA2_FK                                     */
/*==============================================================*/
create  index GRADO_PERSONA2_FK on GRADO_PERSONA (
GRPE_ID_PERSONA
);

/*==============================================================*/
/* Table: NIVEL_PROFESIONAL                                     */
/*==============================================================*/
create table NIVEL_PROFESIONAL (
   NIPR_ID_NIVEL_PROFESIONAL SERIAL               not null,
   NIPR_NIVEL_PROFESIONAL VARCHAR(25)          not null,
   constraint PK_NIVEL_PROFESIONAL primary key (NIPR_ID_NIVEL_PROFESIONAL)
);

/*==============================================================*/
/* Index: NIVEL_PROFESIONAL_PK                                  */
/*==============================================================*/
create unique index NIVEL_PROFESIONAL_PK on NIVEL_PROFESIONAL (
NIPR_ID_NIVEL_PROFESIONAL
);

/*==============================================================*/
/* Table: PAGO                                                  */
/*==============================================================*/
create table PAGO (
   PAGO_ID_PAGO         SERIAL               not null,
   PAGO_ID_DATO_FISCAL  INT4                 not null,
   PAGO_REFERENCIA_BANCARIA VARCHAR(20)          not null,
   PAGO_REFERENCIA_BANCARIA_PATH VARCHAR(200)         not null,
   PAGO_MONTO_PAGADO    DECIMAL(10,4)        not null,
   PAGO_FECHA_PAGO      DATE                 not null,
   PAGO_HORA_PAGO       TIME                 not null,
   PAGO_COMPROBANTE_PATH VARCHAR(200)         not null,
   PAGO_USO_CFDI        VARCHAR(25)          null,
   PAGO_NO_EMPLEADO     INT4                 null,
   constraint PK_PAGO primary key (PAGO_ID_PAGO)
);

/*==============================================================*/
/* Index: PAGO_PK                                               */
/*==============================================================*/
create unique index PAGO_PK on PAGO (
PAGO_ID_PAGO
);

/*==============================================================*/
/* Index: RELATIONSHIP_20_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_20_FK on PAGO (
PAGO_ID_DATO_FISCAL
);

/*==============================================================*/
/* Table: PERSONA                                               */
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
   constraint PK_PERSONA primary key (PERS_ID_PERSONA)
);

/*==============================================================*/
/* Index: PERSONA_PK                                            */
/*==============================================================*/
create unique index PERSONA_PK on PERSONA (
PERS_ID_PERSONA
);

/*==============================================================*/
/* Index: RELATIONSHIP_2_FK                                     */
/*==============================================================*/
create  index RELATIONSHIP_2_FK on PERSONA (
PERS_CLAVE_INEGI
);

/*==============================================================*/
/* Index: RELATIONSHIP_5_FK                                     */
/*==============================================================*/
create  index RELATIONSHIP_5_FK on PERSONA (
PERS_ID_USUARIO
);

/*==============================================================*/
/* Table: PRIVILEGIO                                            */
/*==============================================================*/
create table PRIVILEGIO (
   PRIV_ID_PRIVILEGIO   SERIAL               not null,
   PRIV_PRIVILEGIO      VARCHAR(35)          not null,
   constraint PK_PRIVILEGIO primary key (PRIV_ID_PRIVILEGIO)
);

/*==============================================================*/
/* Index: PRIVILEGIO_PK                                         */
/*==============================================================*/
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

/*==============================================================*/
/* Index: PRIVILEGIO_ROL_PK                                     */
/*==============================================================*/
create unique index PRIVILEGIO_ROL_PK on PRIVILEGIO_ROL (
ROPR_ID_PRIVILEGIO_ROL
);

/*==============================================================*/
/* Index: PRIVILEGIO_ROL_FK                                     */
/*==============================================================*/
create  index PRIVILEGIO_ROL_FK on PRIVILEGIO_ROL (
ROPR_ID_PRIVILEGIO
);

/*==============================================================*/
/* Index: PRIVILEGIO_ROL2_FK                                    */
/*==============================================================*/
create  index PRIVILEGIO_ROL2_FK on PRIVILEGIO_ROL (
ROPR_ID_ROL
);

/*==============================================================*/
/* Table: REGIMEN_FISCAL                                        */
/*==============================================================*/
create table REGIMEN_FISCAL (
   REFI_ID_REGIMEN_FISCAL SERIAL               not null,
   REFI_REGIMEN_FISCAL  VARCHAR(35)          not null,
   constraint PK_REGIMEN_FISCAL primary key (REFI_ID_REGIMEN_FISCAL)
);

/*==============================================================*/
/* Index: REGIMEN_FISCAL_PK                                     */
/*==============================================================*/
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

/*==============================================================*/
/* Index: REVOCACION_PK                                         */
/*==============================================================*/
create unique index REVOCACION_PK on REVOCACION (
REVO_ID_REVOCACION
);

/*==============================================================*/
/* Table: REVOCACION_CERTIFICADO                                */
/*==============================================================*/
create table REVOCACION_CERTIFICADO (
   RECE_ID_REVOCACION_CERTIFICADO SERIAL               not null,
   RECE_ID_REVOCACION   INT4                 not null,
   RECE_ID_CERTIFICACION INT4                 not null,
   RECE_FECHA           DATE                 not null,
   RECE_HORA            TIME                 not null,
   RECE_NOMBRE_RESPONSABLE VARCHAR(25)          null,
   constraint PK_REVOCACION_CERTIFICADO primary key (RECE_ID_REVOCACION_CERTIFICADO)
);

/*==============================================================*/
/* Index: REVOCACION_CERTIFICADO_PK                             */
/*==============================================================*/
create unique index REVOCACION_CERTIFICADO_PK on REVOCACION_CERTIFICADO (
RECE_ID_REVOCACION_CERTIFICADO
);

/*==============================================================*/
/* Index: REVOCACION_CERTIFICADO_FK                             */
/*==============================================================*/
create  index REVOCACION_CERTIFICADO_FK on REVOCACION_CERTIFICADO (
RECE_ID_REVOCACION
);

/*==============================================================*/
/* Index: REVOCACION_CERTIFICADO2_FK                            */
/*==============================================================*/
create  index REVOCACION_CERTIFICADO2_FK on REVOCACION_CERTIFICADO (
RECE_ID_CERTIFICACION
);

/*==============================================================*/
/* Table: ROL                                                   */
/*==============================================================*/
create table ROL (
   ROL_ID_ROL           SERIAL               not null,
   ROL_TIPO_ROL         VARCHAR(15)          not null,
   constraint PK_ROL primary key (ROL_ID_ROL)
);

/*==============================================================*/
/* Index: ROL_PK                                                */
/*==============================================================*/
create unique index ROL_PK on ROL (
ROL_ID_ROL
);

/*==============================================================*/
/* Table: SEDE                                                  */
/*==============================================================*/
create table SEDE (
   SEDE_ID_SEDE         SERIAL               not null,
   SEDE_DIRECCION       TEXT                 not null,
   SEDE_CUPO            INT4                 not null,
   SEDE_ESTADO          BOOL                 not null,
   constraint PK_SEDE primary key (SEDE_ID_SEDE)
);

/*==============================================================*/
/* Index: SEDE_PK                                               */
/*==============================================================*/
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
   SOLI_ID_PERSONA      INT4                 null,
   SOLI_ID_CONVOCATORIA INT4                 null,
   SOLI_ID_PAGO         INT4                 null,
   SOLI_ID_CERTIFICACION INT4                 null,
   constraint PK_SOLICITUD primary key (SOLI_ID_SOLICITUD)
);

/*==============================================================*/
/* Index: SOLICITUD_PK                                          */
/*==============================================================*/
create unique index SOLICITUD_PK on SOLICITUD (
SOLI_ID_SOLICITUD
);

/*==============================================================*/
/* Index: RELATIONSHIP_7_FK                                     */
/*==============================================================*/
create  index RELATIONSHIP_7_FK on SOLICITUD (
SOLI_ID_PERSONA
);

/*==============================================================*/
/* Index: RELATIONSHIP_8_FK                                     */
/*==============================================================*/
create  index RELATIONSHIP_8_FK on SOLICITUD (
SOLI_ID_AUTORIZACION_SOLICITUD
);

/*==============================================================*/
/* Index: RELATIONSHIP_12_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_12_FK on SOLICITUD (
SOLI_ID_EVALUACION
);

/*==============================================================*/
/* Index: RELATIONSHIP_14_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_14_FK on SOLICITUD (
SOLI_ID_CONVOCATORIA
);

/*==============================================================*/
/* Index: RELATIONSHIP_18_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_18_FK on SOLICITUD (
SOLI_ID_PAGO
);

/*==============================================================*/
/* Index: RELATIONSHIP_19_FK                                    */
/*==============================================================*/
create  index RELATIONSHIP_19_FK on SOLICITUD (
SOLI_ID_CERTIFICACION
);

/*==============================================================*/
/* Table: TIPO_COMUNICACION                                     */
/*==============================================================*/
create table TIPO_COMUNICACION (
   TICO_ID_TIPO_COMUNICACION SERIAL               not null,
   TICO_TIPO_COMUNICACION VARCHAR(25)          not null,
   constraint PK_TIPO_COMUNICACION primary key (TICO_ID_TIPO_COMUNICACION)
);

/*==============================================================*/
/* Index: TIPO_COMUNICACION_PK                                  */
/*==============================================================*/
create unique index TIPO_COMUNICACION_PK on TIPO_COMUNICACION (
TICO_ID_TIPO_COMUNICACION
);

/*==============================================================*/
/* Table: TIPO_DOCUMENTO                                        */
/*==============================================================*/
create table TIPO_DOCUMENTO (
   TIDO_ID_TIPO_DOCUMENTO SERIAL               not null,
   TIDO_TIPO_DOCUMENTO  VARCHAR(25)          not null,
   constraint PK_TIPO_DOCUMENTO primary key (TIDO_ID_TIPO_DOCUMENTO)
);

/*==============================================================*/
/* Index: TIPO_DOCUMENTO_PK                                     */
/*==============================================================*/
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

/*==============================================================*/
/* Index: TRABAJO_PK                                            */
/*==============================================================*/
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

/*==============================================================*/
/* Index: TRABAJO_PERSONA_PK                                    */
/*==============================================================*/
create unique index TRABAJO_PERSONA_PK on TRABAJO_PERSONA (
TRPE_ID_TRABAJO_PERSONA
);

/*==============================================================*/
/* Index: TRABAJO_PERSONA_FK                                    */
/*==============================================================*/
create  index TRABAJO_PERSONA_FK on TRABAJO_PERSONA (
TRPE_ID_TRABAJO
);

/*==============================================================*/
/* Index: TRABAJO_PERSONA2_FK                                   */
/*==============================================================*/
create  index TRABAJO_PERSONA2_FK on TRABAJO_PERSONA (
TRPE_ID_PERSONA
);

/*==============================================================*/
/* Table: USUARIO                                               */
/*==============================================================*/
create table USUARIO (
   USUA_ID_USUARIO      SERIAL               not null,
   USUA_ID_ROL          INT4                 not null,
   USUA_CLAVE_ACCESO    VARCHAR(20)          not null,
   constraint PK_USUARIO primary key (USUA_ID_USUARIO)
);

/*==============================================================*/
/* Index: USUARIO_PK                                            */
/*==============================================================*/
create unique index USUARIO_PK on USUARIO (
USUA_ID_USUARIO
);

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
   add constraint FK_EVALUACI_RELATIONS_SEDE foreign key (EVAL_ID_SEDE)
      references SEDE (SEDE_ID_SEDE)
      on delete restrict on update restrict;

alter table GRADO_PERSONA
   add constraint FK_GRADO_PE_GRADO_PER_NIVEL_PR foreign key (GRPE_ID_NIVEL_PROFESIONAL)
      references NIVEL_PROFESIONAL (NIPR_ID_NIVEL_PROFESIONAL)
      on delete restrict on update restrict;

alter table GRADO_PERSONA
   add constraint FK_GRADO_PE_GRADO_PER_PERSONA foreign key (GRPE_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA)
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
   add constraint FK_SOLICITU_RELATIONS_CERTIFIC foreign key (SOLI_ID_CERTIFICACION)
      references CERTIFICACION (CERT_ID_CERTIFICACION)
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

