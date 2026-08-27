/*==============================================================*/
/* SUIF — Instalación completa del esquema                      */
/*                                                              */
/* Equivale a correr, en su orden, los nueve scripts numerados  */
/* del README: el esquema base más todos sus complementos, en   */
/* un solo archivo. Generado por concatenación de esos scripts; */
/* si el responsable de la base actualiza alguno, este archivo  */
/* se regenera, no se edita a mano.                             */
/*                                                              */
/* SOLO para una base VACÍA, en una instalación desde cero.     */
/* A diferencia de suif.sql, aquí no hay ningún drop, así que   */
/* SÍ se corre con ON_ERROR_STOP:                               */
/*                                                              */
/*   psql -v ON_ERROR_STOP=1 -h HOST -U suif -d suif \          */
/*        -f suif_instalacion_completa.sql                      */
/*                                                              */
/* No incluye suif_lleno.sql (datos de prueba de desarrollo).   */
/*==============================================================*/


/*==============================================================*/
/* >>> suif.sql                                                 */
/*==============================================================*/

/*==============================================================*/
/* DBMS name:      PostgreSQL 8                                 */
/* Created on:     11/08/2026 10:37:58 p. m.                    */
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
   EVAL_ID_EVALUACION   INT4                 null,
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
   ESSO_ESTADO_SOLICITUD VARCHAR(45)          not null,
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
   DOCU_NOMBRE          VARCHAR(150)         not null,
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
   ESDO_COMENTARIOS     TEXT                 null,
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
   ESPA_ID_PAGO         INT4                 not null,
   ESPA_ID_C_ESTADO_PAGO INT4                 not null,
   ESPA_FECHA           DATE                 null,
   ESPA_HORA            TIME                 not null,
   ESPA_COMENTARIO      TEXT                 null,
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
   ESSO_MOTIVO_RECHAZO  VARCHAR(255)         null,
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
   GRUP_ID_GRUPO        INT4                 not null,
   EVAL_RESULTADO       INT4                 null,
   constraint PK_EVALUACION primary key (EVAL_ID_EVALUACION)
);

/*==============================================================*/
/* Index: EVALUACION_PK                                         */
/*==============================================================*/
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
/* Table: GRUPO                                                 */
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
   RECE_ID_CERTIFICACION INT4                 null,
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
   SEDE_NOMBRE          VARCHAR(50)          not null,
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
   SOLI_ID_PERSONA      INT4                 not null,
   SOLI_ID_CONVOCATORIA INT4                 not null,
   SOLI_ID_PAGO         INT4                 null,
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
   TIDO_TIPO_DOCUMENTO  VARCHAR(60)          not null,
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
   USUA_CLAVE_ACCESO    VARCHAR(255)         not null,
   constraint PK_USUARIO primary key (USUA_ID_USUARIO)
);

/*==============================================================*/
/* Index: USUARIO_PK                                            */
/*==============================================================*/
create unique index USUARIO_PK on USUARIO (
USUA_ID_USUARIO
);

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
/* >>> suif_evaluacion_grupo.sql                                */
/*==============================================================*/

/*==============================================================*/
/* SUIF — EVALUACION apunta a GRUPO                             */
/* Complemento de suif_ajustes_esquema.sql. Ejecutar DESPUÉS.   */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* El esquema del 11/08/2026 movió la programación del examen (sede, fechas y
   horas) de EVALUACION a GRUPO. suif.sql ya crea EVALUACION con
   GRUP_ID_GRUPO, y suif_ajustes_esquema.sql se limita a añadir la restricción
   uq_evaluacion_grupo dando por hecho que la columna existe.

   Queda sin cubrir el caso intermedio: una base cuyo GRUPO ya se creó pero
   cuyo EVALUACION conservó EVAL_ID_SEDE junto con EVAL_FECHA_INICIO,
   EVAL_HORA_INICIO, EVAL_FECHA_FIN y EVAL_HORA_FIN. Ahí
   uq_evaluacion_grupo aborta por columna inexistente y toda la gestión de
   sedes —la del administrador y la del participante— responde 500, porque
   ambas recorren sede -> grupo -> evaluacion.

   Este script traspasa esa programación a GRUPO y deja EVALUACION como la
   espera la aplicación. No borra columnas ni renglones: las columnas
   anteriores sólo dejan de ser obligatorias para que las altas nuevas, que
   ya no las llenan, sean válidas. */

/* Las altas de este script usan las secuencias; si la base se cargó con IDs
   explícitos pueden haber quedado atrás y provocar llave duplicada. */
SELECT setval(
    pg_get_serial_sequence('grupo', 'grup_id_grupo'),
    COALESCE((SELECT MAX(grup_id_grupo) FROM grupo), 0) + 1,
    false
);

SELECT setval(
    pg_get_serial_sequence('evaluacion', 'eval_id_evaluacion'),
    COALESCE((SELECT MAX(eval_id_evaluacion) FROM evaluacion), 0) + 1,
    false
);

/* La columna que el esquema del 11/08/2026 espera en EVALUACION. */
ALTER TABLE evaluacion ADD COLUMN IF NOT EXISTS grup_id_grupo INT4;

/* uq_grupo_sede limitaba cada sede a una sola programación. Se retira antes
   del traspaso porque una sede con varias evaluaciones históricas necesita un
   GRUPO por cada una. Es la misma línea de suif_grupos_multiples.sql. */
ALTER TABLE grupo DROP CONSTRAINT IF EXISTS uq_grupo_sede;

/* Traspaso. Cada EVALUACION que todavía guarda su propia programación recibe
   el GRUPO equivalente: se reutiliza uno que coincida en sede, fechas y horas
   y que aún no tenga evaluación, y si no existe se crea. La correspondencia
   queda uno a uno, que es lo que exige uq_evaluacion_grupo. */
DO $$
DECLARE
    fila     RECORD;
    id_grupo INT4;
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'evaluacion'
          AND column_name = 'eval_id_sede'
    ) THEN
        RAISE NOTICE 'EVALUACION ya no guarda la programación: no hay nada que traspasar.';
        RETURN;
    END IF;

    FOR fila IN
        EXECUTE 'SELECT eval_id_evaluacion,
                        eval_id_sede,
                        eval_fecha_inicio,
                        eval_hora_inicio,
                        eval_fecha_fin,
                        eval_hora_fin
                   FROM evaluacion
                  WHERE grup_id_grupo IS NULL
                    AND eval_id_sede IS NOT NULL
                  ORDER BY eval_id_evaluacion'
    LOOP
        id_grupo := NULL;

        SELECT g.grup_id_grupo
          INTO id_grupo
          FROM grupo AS g
         WHERE g.sede_id_sede      = fila.eval_id_sede
           AND g.grup_fecha_inicio = fila.eval_fecha_inicio
           AND g.grup_hora_inicio  = fila.eval_hora_inicio
           AND g.grup_fecha_fin    = fila.eval_fecha_fin
           AND g.grup_hora_fin     = fila.eval_hora_fin
           AND NOT EXISTS (
               SELECT 1
               FROM evaluacion AS e
               WHERE e.grup_id_grupo = g.grup_id_grupo
           )
         LIMIT 1;

        IF id_grupo IS NULL THEN
            INSERT INTO grupo (
                sede_id_sede,
                grup_fecha_inicio,
                grup_hora_inicio,
                grup_fecha_fin,
                grup_hora_fin
            ) VALUES (
                fila.eval_id_sede,
                fila.eval_fecha_inicio,
                fila.eval_hora_inicio,
                fila.eval_fecha_fin,
                fila.eval_hora_fin
            )
            RETURNING grup_id_grupo INTO id_grupo;
        END IF;

        UPDATE evaluacion
           SET grup_id_grupo = id_grupo
         WHERE eval_id_evaluacion = fila.eval_id_evaluacion;
    END LOOP;
END $$;

/* Las altas nuevas sólo llenan GRUP_ID_GRUPO y EVAL_RESULTADO. Las columnas
   del esquema anterior se conservan con sus datos, pero dejan de ser
   obligatorias para no rechazar esas altas. */
DO $$
DECLARE
    columna TEXT;
BEGIN
    FOREACH columna IN ARRAY ARRAY[
        'eval_id_sede',
        'eval_fecha_inicio',
        'eval_hora_inicio',
        'eval_fecha_fin',
        'eval_hora_fin'
    ]
    LOOP
        IF EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'evaluacion'
              AND column_name = columna
        ) THEN
            EXECUTE format('ALTER TABLE evaluacion ALTER COLUMN %I DROP NOT NULL', columna);
        END IF;
    END LOOP;
END $$;

/* El resultado se captura después de programar la evaluación. Lo repite
   suif_ajustes_esquema.sql, que en esta base no llegó a terminar. */
ALTER TABLE evaluacion ALTER COLUMN eval_resultado DROP NOT NULL;

/* Cada evaluación pertenece a un grupo existente. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_evaluacion_grupo'
    ) THEN
        ALTER TABLE evaluacion
            ADD CONSTRAINT fk_evaluacion_grupo
            FOREIGN KEY (grup_id_grupo) REFERENCES grupo (grup_id_grupo);
    END IF;
END $$;

/* Una evaluación por grupo: el cupo de la sede se cuenta contra ella. Es el
   bloque en el que abortó suif_ajustes_esquema.sql. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'uq_evaluacion_grupo'
    ) THEN
        ALTER TABLE evaluacion
            ADD CONSTRAINT uq_evaluacion_grupo UNIQUE (grup_id_grupo);
    END IF;
END $$;

/* Sólo se exige el grupo cuando ninguna evaluación quedó sin traspasar: una
   fila huérfana debe revisarse a mano, no bloquear el despliegue. */
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM evaluacion WHERE grup_id_grupo IS NULL) THEN
        ALTER TABLE evaluacion ALTER COLUMN grup_id_grupo SET NOT NULL;
    ELSE
        RAISE NOTICE 'Hay evaluaciones sin grupo: GRUP_ID_GRUPO se deja opcional.';
    END IF;
END $$;

/* Las consultas del catálogo recorren los grupos de cada sede ordenados por
   fecha y hora de inicio. Lo repite suif_grupos_multiples.sql. */
CREATE INDEX IF NOT EXISTS idx_grupo_sede_inicio
    ON grupo (sede_id_sede, grup_fecha_inicio, grup_hora_inicio);

/* Estado inicial consistente: las sedes sin programación o llenas no ofrecen
   cupo. SEDE_CUPO es el aforo de cada aplicación, no el total de la sede. */
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

/* Comprobación: evaluaciones_sin_grupo debe quedar en 0. */
SELECT
    (SELECT COUNT(*) FROM grupo)                                      AS grupos,
    (SELECT COUNT(*) FROM evaluacion)                                 AS evaluaciones,
    (SELECT COUNT(*) FROM evaluacion WHERE grup_id_grupo IS NULL)     AS evaluaciones_sin_grupo,
    (SELECT COUNT(*) FROM sede)                                       AS sedes;

/*==============================================================*/
/* >>> suif_ajustes_esquema.sql                                 */
/*==============================================================*/

/*==============================================================*/
/* SUIF — Ajustes al esquema base                               */
/* Complemento de suif.sql. Ejecutar DESPUÉS del esquema.       */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* Un hash de contraseña de Laravel mide 60 caracteres. */
ALTER TABLE usuario ALTER COLUMN usua_clave_acceso TYPE VARCHAR(255);

/* "Autorización de la publicación" son 30 caracteres. */
ALTER TABLE tipo_documento ALTER COLUMN tido_tipo_documento TYPE VARCHAR(60);

/* Nombre original del archivo que sube la persona. */
ALTER TABLE documento ALTER COLUMN docu_nombre TYPE VARCHAR(150);

/* En 35 caracteres no cabe una explicación de rechazo útil. */
ALTER TABLE estado_solicitud ALTER COLUMN esso_motivo_rechazo TYPE VARCHAR(255);

/* Cargar un documento no requiere comentario del revisor. */
ALTER TABLE estado_documento ALTER COLUMN esdo_comentarios DROP NOT NULL;

/* La columna se llamaba esso_estatus_solicitud antes del esquema del
   11/08/2026. Se renombra para que las bases anteriores queden alineadas
   con suif.sql y con las consultas de la aplicación. */
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'c_estado_solicitud'
          AND column_name = 'esso_estatus_solicitud'
    ) THEN
        ALTER TABLE c_estado_solicitud
            RENAME COLUMN esso_estatus_solicitud TO esso_estado_solicitud;
    END IF;
END $$;

/* "Documentación en revisión" no cabe en 15 caracteres. */
ALTER TABLE c_estado_solicitud ALTER COLUMN esso_estado_solicitud TYPE VARCHAR(45);

/* Nombre visible de la sede. El backfill sólo cubre filas preexistentes. */
ALTER TABLE sede ADD COLUMN IF NOT EXISTS sede_nombre VARCHAR(150);

UPDATE sede
SET sede_nombre = LEFT(sede_direccion, 150)
WHERE sede_nombre IS NULL;

/* suif.sql la declara en 50; el formulario admite 150. */
ALTER TABLE sede ALTER COLUMN sede_nombre TYPE VARCHAR(150);

ALTER TABLE sede ALTER COLUMN sede_nombre SET NOT NULL;

/* El resultado se captura después de programar la evaluación. */
ALTER TABLE evaluacion ALTER COLUMN eval_resultado DROP NOT NULL;

/* La primera versión administra una sola programación por sede, y la
   programación vive en GRUPO desde el esquema del 11/08/2026. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'uq_grupo_sede'
    ) THEN
        ALTER TABLE grupo
            ADD CONSTRAINT uq_grupo_sede UNIQUE (sede_id_sede);
    END IF;
END $$;

/* Una evaluación por grupo: el cupo de la sede se cuenta contra ella. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'uq_evaluacion_grupo'
    ) THEN
        ALTER TABLE evaluacion
            ADD CONSTRAINT uq_evaluacion_grupo UNIQUE (grup_id_grupo);
    END IF;
END $$;

/* Estado inicial consistente: las sedes sin programación o llenas no ofrecen cupo. */
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

/* Los datos históricos pueden usar IDs explícitos; se evita que una alta
   posterior reutilice un identificador que ya existe. */
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

/* Un solo documento por tipo en cada solicitud. */
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'uq_documento_solicitud_tipo'
    ) THEN
        ALTER TABLE documento
            ADD CONSTRAINT uq_documento_solicitud_tipo
            UNIQUE (soli_id_solicitud, tido_id_tipo_documento);
    END IF;
END $$;

/* El rol 1 se llamaba "Participante" antes del refactor a "Persona".
   El INSERT de suif_catalogos.sql no lo corrige porque lleva
   ON CONFLICT DO NOTHING, así que las bases existentes se quedan con el
   nombre viejo y el pre-registro no encuentra el rol al dar de alta. */
UPDATE rol SET rol_tipo_rol = 'Persona'
 WHERE rol_id_rol = 1 AND rol_tipo_rol = 'Participante';

/*==============================================================*/
/* >>> suif_catalogos.sql                                       */
/*==============================================================*/

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
    (1, 'Persona'),
    (2, 'Administrador')
ON CONFLICT (rol_id_rol) DO NOTHING;

SELECT setval(pg_get_serial_sequence('rol', 'rol_id_rol'),
              (SELECT MAX(rol_id_rol) FROM rol));

/* Tipos de comunicación — correos y teléfono de la persona */
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
INSERT INTO c_estado_solicitud (esso_id_c_estado_solicitud, esso_estado_solicitud) VALUES
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

/*==============================================================*/
/* >>> suif_grupos_multiples.sql                                */
/*==============================================================*/

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

/*==============================================================*/
/* >>> suif_referencias_bancarias.sql                           */
/*==============================================================*/

/*==============================================================*/
/* SUIF — Catálogo de referencias bancarias                     */
/* Complemento de suif.sql. Ejecutar DESPUÉS del esquema.       */
/* Puede volver a ejecutarse sin duplicar ni destruir nada.     */
/*==============================================================*/

/*--------------------------------------------------------------*/
/* PAGO nace cuando se le asigna la referencia a la persona, no  */
/* cuando paga: en ese momento todavía no hay datos fiscales, ni */
/* fecha de pago, ni comprobante. Esas cuatro columnas dejan de  */
/* ser obligatorias y se llenan más adelante en el trámite.      */
/*--------------------------------------------------------------*/
ALTER TABLE pago ALTER COLUMN pago_id_dato_fiscal DROP NOT NULL;
ALTER TABLE pago ALTER COLUMN pago_fecha_pago DROP NOT NULL;
ALTER TABLE pago ALTER COLUMN pago_hora_pago DROP NOT NULL;
ALTER TABLE pago ALTER COLUMN pago_comprobante_path DROP NOT NULL;

/*==============================================================*/
/* Table: REFERENCIA_BANCARIA                                   */
/*                                                              */
/* Catálogo de referencias que el administrador carga por CSV.  */
/* Cada renglón se entrega a una sola persona: al asignarse se  */
/* copia a PAGO (PAGO_REFERENCIA_BANCARIA y su PATH) y aquí se  */
/* guarda a qué PAGO quedó ligada. REBA_ID_PAGO es único, así   */
/* que la base impide que la misma referencia se reparta dos    */
/* veces aunque dos personas la pidan al mismo tiempo.          */
/*==============================================================*/
create table if not exists REFERENCIA_BANCARIA (
   REBA_ID_REFERENCIA_BANCARIA SERIAL               not null,
   REBA_ID_PAGO         INT4                 null,
   REBA_REFERENCIA      VARCHAR(20)          not null,
   REBA_PATH            VARCHAR(200)         null,
   REBA_MONTO           DECIMAL(10,4)        null,
   REBA_VIGENCIA        DATE                 null,
   REBA_FECHA_CARGA     DATE                 not null,
   REBA_HORA_CARGA      TIME                 not null,
   REBA_FECHA_ASIGNACION DATE                null,
   REBA_HORA_ASIGNACION TIME                 null,
   constraint PK_REFERENCIA_BANCARIA primary key (REBA_ID_REFERENCIA_BANCARIA)
);

/*==============================================================*/
/* Index: REFERENCIA_BANCARIA_PK                                */
/*==============================================================*/
create unique index if not exists REFERENCIA_BANCARIA_PK on REFERENCIA_BANCARIA (
REBA_ID_REFERENCIA_BANCARIA
);

/*==============================================================*/
/* Index: REFERENCIA_BANCARIA_AK                                */
/* El número de referencia no se repite dentro del catálogo:    */
/* volver a cargar el mismo CSV actualiza, no duplica.          */
/*==============================================================*/
create unique index if not exists REFERENCIA_BANCARIA_AK on REFERENCIA_BANCARIA (
REBA_REFERENCIA
);

/*==============================================================*/
/* Index: REFERENCIA_BANCARIA_PAGO_AK                           */
/* Una referencia pertenece a un solo pago.                     */
/*==============================================================*/
create unique index if not exists REFERENCIA_BANCARIA_PAGO_AK on REFERENCIA_BANCARIA (
REBA_ID_PAGO
);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_referencia_bancaria_pago'
    ) THEN
        ALTER TABLE referencia_bancaria
            ADD CONSTRAINT fk_referencia_bancaria_pago
            FOREIGN KEY (reba_id_pago) REFERENCES pago (pago_id_pago)
            ON DELETE RESTRICT ON UPDATE RESTRICT;
    END IF;
END $$;

/*--------------------------------------------------------------*/
/* Los estados del pago viven en un catálogo que suif.sql crea   */
/* vacío: sin estos tres renglones la revisión del comprobante   */
/* no puede registrar nada.                                      */
/*--------------------------------------------------------------*/
INSERT INTO c_estado_pago (espa_id_c_estado_pago, esta_estado_pago) VALUES
    (1, 'Pendiente'),
    (2, 'Completado'),
    (3, 'Declinado')
ON CONFLICT (espa_id_c_estado_pago) DO NOTHING;

SELECT setval(pg_get_serial_sequence('c_estado_pago', 'espa_id_c_estado_pago'),
              (SELECT MAX(espa_id_c_estado_pago) FROM c_estado_pago));

/*==============================================================*/
/* >>> suif_rfc_persona.sql                                     */
/*==============================================================*/

/*==============================================================*/
/* SUIF — RFC de la persona                                     */
/* Complemento de suif.sql. Ejecutar DESPUÉS del esquema.       */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* El RFC identifica a la persona, igual que la CURP, y se pide desde el
   pre-registro. DATO_FISCAL cuelga de PAGO y describe la facturación, no
   a la persona, así que el RFC vive aquí. DAFI_RFC se conserva.

   DEFAULT '' permite agregar la columna sobre una tabla que ya tiene
   renglones: los anteriores al cambio quedan sin RFC y los nuevos siempre
   lo traen, porque la aplicación lo exige. */
ALTER TABLE persona ADD COLUMN IF NOT EXISTS pers_rfc VARCHAR(13) NOT NULL DEFAULT '';

/* Dos personas no pueden compartir RFC. El índice es parcial a propósito:
   un UNIQUE normal fallaría con más de un renglón heredado, porque todos
   comparten la cadena vacía. */
CREATE UNIQUE INDEX IF NOT EXISTS uq_persona_rfc
    ON persona (pers_rfc)
    WHERE pers_rfc <> '';

/*==============================================================*/
/* >>> suif_referencia_fecha_emision.sql                        */
/*==============================================================*/

/*==============================================================*/
/* SUIF — Fecha de emisión de la referencia bancaria            */
/* Complemento de suif_referencias_bancarias.sql.               */
/* Puede volver a ejecutarse sin efectos secundarios.           */
/*==============================================================*/

/* El archivo que manda la DEC trae dos fechas por referencia: cuándo la
   emitió el banco y hasta cuándo vale. REBA_VIGENCIA ya guardaba la
   segunda; ésta guarda la primera, que hasta ahora se leía y se tiraba.

   Sirve para auditar: si alguien discute una vigencia, aquí queda de qué
   emisión salió sin tener que volver al Excel original.

   NULL a propósito: los renglones cargados antes de este cambio no tienen
   emisión. Que la columna venga en el archivo lo exige el importador, no
   el esquema, para no invalidar lo ya cargado. */
ALTER TABLE referencia_bancaria
    ADD COLUMN IF NOT EXISTS reba_fecha_emision DATE NULL;


/*==============================================================*/
/* >>> suif_roles_administrativos.sql                           */
/*==============================================================*/

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
