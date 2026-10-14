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
