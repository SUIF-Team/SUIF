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

/* "Documentación en revisión" no cabe en 15 caracteres. */
ALTER TABLE c_estado_solicitud ALTER COLUMN esso_estatus_solicitud TYPE VARCHAR(40);

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
