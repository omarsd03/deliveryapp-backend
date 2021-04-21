<?php

    require('../config/db-connect.php');

    $users = new Users();

    if ( isset( $_POST['signUp'] ) ) {
        $jsonData = json_decode( $_POST['signUp'] );
        echo $users->signUp( $jsonData );
    }

    if ( isset( $_POST['signIn'] ) ) {
        $jsonData = json_decode( $_POST['signIn'] );
        echo $users->signIn( $jsonData );
    }

    class Users {
        
        private $db, $lastInsertId;
        public $objCnn;

        public function __construct() {

            $this->objCnn = new ConexionBD();
            $this->db = $this->objCnn->conectaBD();

        }

        const ERRORS = [
            'mail_duplicated' => ["success" => 0, "error" => 'Mail existing', "fix" => 'Please enter another email'],
            'username_duplicated' => ["success" => 0, "error" => 'Username existing', "fix" => 'Please enter another username'],
            'signup_error' => ["success" => 0, "error" => 'Fail to user sign up', "fix" => 'Please contact support'],
            'not_registred' => ["success" => 0, "error" => 'User not registered', "fix" => 'Please register in the signup page'],
            'incorrect_credencials' => ["success" => 0, "error" => 'Incorrect credencials', "fix" => 'Please verify your credentials'],
        ];

        const SUCCESS = [
            'signup' => ['success' => 1, "msg" => 'Register successfully!',],
        ];

        // TODO: Crear metodo para enviar error por correo electronico
        private function sendNotifyError($error) {
            var_dump($error);
        }

        public function startTransaction() {
            $this->db->beginTransaction();
        }

        public function insertTransaction($sql, $data, $tipo = 'INSERT') {
            $sttmt = $this->db->prepare($sql);
            $sttmt->execute($data);
            $this->lastInsertId = ($tipo == 'INSERT') ? $this->db->lastInsertId() : null ;
            // $this->lastInsertId = $this->db->lastInsertId();
        }

        public function submitTransaction() {
            
            try {
                $this->db->commit();
            } catch (PDOException $e) {
                
                $this->db->rollBack();
                $this->sendNotifyError($e);
                echo 'Error en la transaccion: ' . $e->getMessage();
                return false;

            }

            return true;

        }

        public function signUp($jsonData) {
            
            try {

                $jsonResp = new stdClass();
                
                $sttmt = $this->db->prepare('SELECT * FROM d_delivery_users WHERE mail = ?');
                $sttmt->execute([ $jsonData->mail ]);
                $resSt = $sttmt->fetchAll();

                if ($sttmt->rowCount() > 0) {
                    return json_encode(self::ERRORS['mail_duplicated']);
                }

                $sttmt = $this->db->prepare('SELECT * FROM d_delivery_users WHERE username = ?');
                $sttmt->execute([ $jsonData->username ]);
                $resSt = $sttmt->fetchAll();

                if ($sttmt->rowCount() > 0) {
                    return json_encode(self::ERRORS['username_duplicated']);
                }

                $this->startTransaction();

                $query = "INSERT INTO d_delivery_users (first_name, last_name, address, colonia, municipio, estado, mail, username, pwd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $data = [ $jsonData->nombre, $jsonData->apellidos, $jsonData->direccion, $jsonData->colonia, $jsonData->municipio, $jsonData->estado, $jsonData->mail, $jsonData->username, $jsonData->password ];

                $this->insertTransaction($query, $data);

                $result = $this->submitTransaction();

                return $response = ($result) ? json_encode(self::SUCCESS['signup']) : json_encode(self::ERRORS['signup_error']) ;

            } catch (Exception $e) {

                $this->sendNotifyError($e);
                echo 'Error en el metodo: ' . $e->getMessage();

            }

        }

        public function signIn($jsonData) {
            
            try {

                $jsonResp = new stdClass();

                $sttmt = $this->db->prepare('SELECT * FROM d_delivery_users WHERE username = ?');
                $sttmt->execute([ $jsonData->username ]);
                $resSt = $sttmt->fetchAll();

                if ($sttmt->rowCount() == 0) {
                    return json_encode(self::ERRORS['not_registred']); 
                }
                
                $sttmt = $this->db->prepare('SELECT * FROM d_delivery_users WHERE username = ? AND pwd = ?');
                $sttmt->execute([ $jsonData->username, $jsonData->password ]);
                $resSt = $sttmt->fetchAll();

                if ($sttmt->rowCount() == 0) {
                    return json_encode(self::ERRORS['incorrect_credencials']); 
                }

                echo 'Crear sesion :D';

            } catch (Exception $e) {
                
                $this->sendNotifyError($e);
                echo 'Error en el metodo: ' . $e->getMessage();
                
            }

        }

    }
    

?>