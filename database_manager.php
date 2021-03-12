<?php


class DatabaseManager{

    /*
                    DatabaseManager() - Datubāzes datu apstrādes klase

    Šī klase ir paredzēta datu apstrādei izmantojot datubāzi. Šī klase piedāvā arī datu
    drošības pārbaudes funkcijas kā, piemēram, check_username(), kas pārbauda vai lietotājvārds
    ir pareizi noformulēts. Vairāk: <ENTER WIKI URL HERE>

    @author: CracX

    */

    // Klasi iniciējot, saglabājam dažas lietiņas par datubāzi no config.php un uzsākam savienojumu
    function __construct(){
        require_once 'config.php';

        $this->DB_HOST = $DB_HOST;
        $this->DB_DATABASE = $DB_DATABASE;
        $this->DB_USER = $DB_USER;
        $this->DB_PASS = $DB_PASS;

        $this->CONN = $this->_init_db_connection();
    }

    // Privāta funckija, kas izveido savienojumu ar datubāzi, izveidojot PDO instanci
    private function _init_db_connection(){
        try {
            $dbh = new PDO("mysql:host=$this->DB_HOST;dbname=$this->DB_DATABASE;charset=utf8", 
                            $this->DB_USER, 
                            $this->DB_PASS);
        } catch (PDOException $e) {
            http_response_code(500);
            die("Database error");
        }

        return $dbh;
    }

    // Publiska funkcija, kas iegūst visus kursus no tabulas 'courses' un ieliek tos masīvā
    public function get_courses(){
        $sql = "SELECT * FROM courses";
        $stmt = $this->CONN->prepare($sql);

        $stmt->execute();

        $courses_arr = array();

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $course = $row['course'];
            $courses_arr[] = array("id"=>$id, "course"=>$course);
        }

        return $courses_arr;
    }

    // Publiska funkcija, kas iegūst lietotāju (ja tāds eksistē) pēc lietotājvārda
    public function get_user(string $username){
        $sql = "SELECT * FROM users WHERE username=?;";
        $stmt = $this->CONN->prepare($sql);

        $stmt->execute(array($username));

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$result){
            return false;
        }

        return $result;
    }

    // Publiska funkcija, kas pārbauda vai lietotājvārds sader ar formātu
    public function check_username(string $username){
        if (strlen($username) < 5){
            return array(
                "success" => false, 
                "error_id" => 0
            );
        }

        $user = $this->get_user($username);

        if ($user){
            return array(
                "success" => false, 
                "error_id" => 1
            );
        }

        return array(
            "success" => true 
        );
    }

    // Publiska funkcija, kas pārbauda vai prole sader ar formātu
    public function check_password(string $password){
        if (strlen($password) < 5){
            return array(
                "success" => false, 
                "error_id" => 0
            );
        }
        return array(
            "success" => true
        );
    }

    // Publiska funckijas, kas reģistrē lietotāju
    public function register(string $firstname, string $lastname, string $status, $courseID, string $username, string $password, string $basename){

        if(!$this->check_username($username)){
            return false;
        }

        $sql = "INSERT INTO users(firstname, lastname, status, courseID, username, password, image) VALUES (?, ?, ?, ?, ?, ?, ?);";
        $stmt = $this->CONN->prepare($sql);

        $hash = password_hash($password, PASSWORD_ARGON2I);

        $results = $stmt->execute(array($firstname, $lastname, $status, $courseID, $username, $hash, $basename));

        if(!$results){
            return false;
        }

        return true;
    }

    // Publiska funckijas, kas pārbauda lietotāja autentifikācijas datus un saderības gadījumā atgriež lietotāja datus
    public function login(string $username, string $password){
        $user = $this->get_user($username);
        if(!$user){
            return false;
        }

        $real_hash = $user['password'];//Hashotā parole no DB, jo get_user atgriež visus datus par lietotāju
        if(!password_verify($password, $real_hash)){
            return false;
        }

        return $user;
    }

    public function get_events($courseID){
        $sql = "SELECT `id`,`name`, DATE_FORMAT(`date`, '%m/%d/%Y') AS `date` , `type`, `everyYear`, `color`, DATE_FORMAT(`time`, '%H:%i') AS `time`, `description` FROM events_out ORDER BY time";
        
        $stmt = $this->CONN->prepare($sql);
        $stmt->execute();

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row["everyYear"] = ($row["everyYear"] == 1) ? true : false;
            $out[] = $row;
        }

        return $out;
    }
    
    public function insert_event($eventData){
        $sql = "INSERT INTO `events`(`name`, `date`, `type`, `everyYear`, `time`, `description`) VALUES (?,?,?,?,?,?)";
        $stmt = $this->CONN->prepare($sql);
        $stmt->execute(array($eventData["name"],$eventData["date"],$eventData["type"],$eventData["everyYear"],$eventData["time"],$eventData["description"]));

        $sql = "SELECT `id`,`name`, DATE_FORMAT(`date`, '%m/%d/%Y') AS `date` , `type`, `everyYear`, `color`, DATE_FORMAT(`time`, '%H:%i') AS `time`, `description` FROM events_out WHERE `id` = (SELECT MAX(`id`) FROM events_out)";
        $stmt = $this->CONN->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row["everyYear"] = ($row["everyYear"] == 1) ? true : false;
        return $row;
    }
}

?>