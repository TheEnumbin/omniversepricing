<?php


class Omniversecron{

    private $db_instance;

    public function __construct(){
        $root_dir = dirname(dirname(dirname(__DIR__)));
        $configs = include_once $root_dir . '/app/config/parameters.php';
        $configs = array_shift($configs);
        try {
            $host = $configs['database_host'];
            $dbname = $configs['database_name'];
            $username = $configs['database_user'];
            $password = $configs['database_password'];
            $pref = $configs['database_prefix'];
            // Create a new PDO instance
            $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        
            // Set PDO error mode to exception
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
            // Example: Execute a simple SQL query to retrieve data from a table
            $stmt = $db->query("SELECT * FROM {$pref}product");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo '<pre>';
            print_r($result);
            echo '</pre>';
            echo __FILE__ . ' : ' . __LINE__;
        } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage();
            exit;
        }
    }

}

new Omniversecron();