<?php

    require('config/db-connect.php');

    $delivery = new Delivery();

    if ( isset($_POST['testClass']) ) {
        $jsonData = json_decode( $_POST['testClass'] );
        echo $delivery->testClass( $jsonData );
    }

    # Clases y metodos
    class Delivery {
        
        private $id_user, $role, $mail, $db, $lastInsertId;
        public $objCnn;

        public function __construct() {
            
            $this->objCnn = new ConexionBD();
            $this->db = $this->objCnn->conectaBD();

        }

        #Helpers
        const ERRORS = [
            'session' => ["error" => 'Session not active', "fix" => 'User must be log-out and log-in again'],
        ];

        // TODO: Crear metodo para enviar error por correo electronico
        private function sendNotifyError($error) {
            var_dump($error);
        }

        private function sessionStatus() {
            return session_status() === PHP_SESSION_ACTIVE ? true : false;
        }

        public function startTransaction() {
            $this->db->beginTransaction();
        }

        public function insertTransaction($sql, $data) {
            $sttmt = $this->db->prepare($sql);
            $sttmt->execute($data);
            $this->lastInsertId = $this->db->lastInsertId();
        }

        public function updateTransaction($sql, $data) {
            $sttmt = $this->db->prepare($sql);
            $sttmt->execute($data);
        }

        public function submitTransaction() {

            try {
                $this->db->commit();
            } catch(PDOException $e) {

                $this->db->rollBack();
                $this->sendNotifyError($e);
                echo 'Error en transaccion: ' . $e->getMessage();
                return false;

            }

            return true;

        }

        public function testClass($jsonData) {
            var_dump($jsonData);
        }

    }
    

?>