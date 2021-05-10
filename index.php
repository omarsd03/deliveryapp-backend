<?php

    require_once 'vendor/autoload.php';
    require_once 'auth/auth.php';

    require('config/db-connect.php');

    $delivery = new Delivery();
    $auth = new Auth();

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

        // TODO: Establecer el puerto SMTP y el gestor de Google para enviar los mails
        private function sendNotifyError($error) {
            
            // ini_set('SMTP', '');
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

        # Comentada ya que se utilizaran tokens para el manejo de sesion
        // private function sessionStatus() {
        //     return session_status() === PHP_SESSION_ACTIVE ? true : false;
        // }

        # Get header Authorization
        private function getAuthorizationHeader() {

            $headers = null;

            if (isset($_SERVER['Authorization'])) {
                $headers = trim($_SERVER["Authorization"]);
            } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
                $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
            } elseif (function_exists('apache_request_headers')) {

                $requestHeaders = apache_request_headers();
                // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
                $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
                //print_r($requestHeaders);
                if (isset($requestHeaders['Authorization'])) {
                    $headers = trim($requestHeaders['Authorization']);
                }

            }

            return $headers;

        }
        
        # Get access token from header
        private function getBearerToken() {

            $headers = $this->getAuthorizationHeader();

            // HEADER: Get the access token from the header
            if (!empty($headers)) {

                if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                    return $matches[1];
                }

            }

            return null;

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

        # Registro
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

        # Inicio de sesion
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

        # Funcion para administrar el flujo de pedidos
        public function administrarFlujo($folio, $idUsr, $role, $stage, $status, $cadenaAuth = '', $comentarios = null) {
        
            $usrNow = null; $usrNowName = null; $usrNowRole = null; $usrAuth = null; $usrNext = null; $stage++;

            if ($role == 'Requester') {
                
                $sttmt = $this->db->prepare("SELECT * FROM users WHERE role = ? AND available = ?");
                $sttmt->execute([ 'Deliver', 1 ]);
                $resSt = $sttmt->fetchAll();

                if ($sttmt->rowCount() > 1 || $sttmt->rowCount() == 0) {

                    $usrNow = 0;
                    $usrNowName = 'Delivers';

                    $query = "UPDATE d_delivery_general SET g_usr_now = ?, g_name_usr_now = ?, g_stand_by = ? WHERE g_folio = ?";
                    $data = [$usrNow, $usrNowName, 1, $folio];

                    $this->updateTransaction($query, $data);

                    $result = $this->submitTransaction();

                    // Enviar Notificacion de espera
                    // if ($result) {

                    // }
                    
                } elseif ($sttmt->rowCount() == 1) {
                    
                    $usrNext = $resSt[0];
                    $usrNow = $usrNext->id_user;
                    $usrNowRole = $usrNext->role;
                    $usrNowName = $usrNext->first_name . " " . $usrNext->last_name;
                    $usrAuth = $cadenaAuth . $usrNow . "/";

                    $query = "INSERT INTO progress_icrf (folio, approval, role, stage, status) VALUES (?, ?, ?, ?, ?)";
                    $data = [$folio, $usrNow, $usrNowRole, $stage, 'Pendiente'];

                    $this->insertTransaction($query, $data);

                    $query = "UPDATE d_delivery_general SET g_usr_now = ?, g_name_usr_now = ?, g_stage = ? WHERE g_folio = ?";
                    $data = [$usrNow, $usrNowName, $stage, $folio];

                    $this->updateTransaction($query, $data);

                    $result = $this->submitTransaction();

                    // Enviar Notificacion
                    // if ($result) {

                    // }

                }

            }

            return $result;

        }

        public function crearPedido($jsonData) {

            try {

                $auth = new Auth();

                $token = $this->getBearerToken();
                $auth->Check($token);

                $user = $auth->GetData($token);
                
                $query = "INSERT INTO d_delivery_general (g_domicilio, g_colonia, g_municipio, g_estado, g_comentarios, g_usr_solicitante, g_stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $data = [ $jsonData->domicilio, $jsonData->colonia, $jsonData->municipio, $jsonData->estado, $jsonData->comentarios, $user->id_user, 1 ];

                $this->insertTransaction($query, $data);

                $sttmt = $this->db->prepare("SELECT * FROM d_delivery_general WHERE id_g_delivery = ?");
                $sttmt->execute([ $this->lastInsertId ]);
                $resSt = $sttmt->fetchAll();

                $folio = $resSt[0]->g_folio;

                foreach ($jsonData->items as $item) {
                    
                    $query = "INSERT INTO d_delivery_items (i_folio, i_item, i_descripcion) VALUES (?, ?, ?, ?)";
                    $data = [ $folio, $item, $descripcion ];

                    $this->insertTransaction($query, $data);

                }

                $result = $this->administrarFlujo($folio, $user->id_user, $user->role, 1, 'En Proceso', $user->id_user);

            } catch (Exception $e) {

                // $this->sendNotifyError($e);
                echo 'Error en el metodo: ' . $e->getMessage();

            }
            

        }

    }
    

?>