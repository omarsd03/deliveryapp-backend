<?php

    require_once 'vendor/autoload.php';
    require_once 'auth/auth.php';

    require('config/db-connect.php');

    $delivery = new Delivery();
    $auth = new Auth();

    // $data = ['nombre' => 'Omar', 'password' => 'abc123..'];
    // $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MjAxNjU4NTMsImF1ZCI6ImNjNDFhNTlmYzE2NTc2ZTM5N2FjZDVlMWI5NjgzNzBiODUyNmQ2ZTciLCJkYXRhIjp7Im5vbWJyZSI6Ik9tYXIiLCJwYXNzd29yZCI6ImFiYzEyMy4uIn19.Njs-tDDE2Tlya7lUny00VtVXvIwj_2OKrERhfSBipR8";

    // var_dump( $auth->SignIn( $data ) );
    // var_dump( $auth->Check( $token ) );
    // var_dump( $auth->GetData( $token ) );

    # Iniciar sesion / Nuevo Registro
    if ( isset( $_POST['signUp'] ) ) {
        $jsonData = json_decode( $_POST['signUp'] );
        echo $delivery->signUp( $jsonData );
    }

    if ( isset( $_POST['signIn'] ) ) {
        $jsonData = json_decode( $_POST['signIn'] );
        echo $delivery->signIn( $jsonData );
    }

    # Peticiones de aplicacion
    if ( isset( $_POST['crearPedido'] ) ) {
        $jsonData = json_decode( $_POST['crearPedido'] );
        echo $delivery->crearPedido( $jsonData );
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
            
            // ini_set('SMTP', 'smtp.mail.saint-gobain.net');
            // ini_set('port', 25);
            $headers = "Content-Type: text/html; charset=UTF-8\n";
            $headers .= "From: salgadodiazomar96@gmail.com";
            $body = "<html>
                    <p>Se ha registrado el siguiente error</p>
                    <table border='1'>
                    <tr>
                        <th>Message</th>
                        <th>Code</th>
                        <th>File</th>
                        <th>Line</th>
                        <th>Trace As String</th>
                        <th>SQLSTATE</th>
                    </tr>
                    <tr>
                        <td>".$error->getMessage()."</td>
                        <td>".$error->getCode()."</td>
                        <td>".$error->getFile()."</td>
                        <td>".$error->getLine()."</td>
                        <td>".$error->getTraceAsString()."</td>
                        <td>".json_encode($error)."</td>
                    </tr>
                    </table>
                    </html>";
            error_log($body, 1, 'salgadodiazomar96@gmail.com', $headers);

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

                $jsonResp = $resSt[0];
                $jsonResp->pwd = ':D';

                $auth = new Auth();
                $token = $auth->SignIn( $jsonResp );
                
                $response = [ 'success' => 1, 'token' => $token ];

                return json_encode($response);

            } catch (Exception $e) {
                
                $this->sendNotifyError($e);
                echo 'Error en el metodo: ' . $e->getMessage();
                
            }

        }

        public function testClass($jsonData) {

            try {

                $jsonResp = new stdClass();
                
                $sttmt = $this->db->prepare('SELECT * FROM d_delivery_test WHERE id = ?');
                $sttmt->execute();
                $resSt = $sttmt->fetchAll(PDO::FETCH_OBJ);

                $jsonResp = $resSt;

                return json_encode($jsonResp);

            } catch (Exception $e) {
                echo 'Error ' . $e->getMessage();
                $this->sendNotifyError($e);
            }
            
        }

        public function crearPedido($jsonData) {
            
            var_dump($jsonData);
            $query = "INSERT INTO `d_delivery_general`(g_domicilio, g_colonia, g_municipio, g_estado, g_comentarios, g_usr_solicitante, g_usr_now, g_name_usr_now, g_stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $data = [];

        }

    }
    

?>