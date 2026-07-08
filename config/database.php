<?php

    // Clase encargada de crear la conexión a la base de datos.
    class Database {
        // Parámetros de conexión.
        public $host = "";
        public $db_name = "";
        public $username = "";
        public $password = "";
        public $conn;

        // Devuelve una conexión PDO lista para usar.
        public function getConnection()
        {
            // Intenta conectarse y configurar el manejo de errores.
            try
            {
                // Crea la conexión usando los datos definidos arriba.
                $this->conn = new PDO
                (
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password
                );
                // Fuerza a PDO a lanzar excepciones ante errores.
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            // Si falla la conexión, muestra el motivo.
            catch(PDOException $excepcion)
            {
                echo "Error: " . $excepcion->getMessage();
            }
            // Retorna la conexión (o null si falló).
            return $this->conn;
        }
    }
?>
