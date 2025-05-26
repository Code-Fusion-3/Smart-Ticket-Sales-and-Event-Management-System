<?php
require_once 'config.php';

// Database connection class
class Database {
    private $conn;
    
    // Constructor - connect to database
    public function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            // Set charset to utf8
            $this->conn->set_charset("utf8");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    // Execute query
    public function query($sql) {
        $result = $this->conn->query($sql);
        
        if (!$result) {
            die("Query failed: " . $this->conn->error . " SQL: " . $sql);
        }
        
        return $result;
    }
    
    // Fetch all results as associative array
    public function fetchAll($sql) {
        $result = $this->query($sql);
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    // Fetch single row
    public function fetchOne($sql) {
        $result = $this->query($sql);
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    // Insert data and return last insert ID
    public function insert($sql) {
        $this->query($sql);
        return $this->conn->insert_id;
    }
    
    // Update data and return affected rows
    public function update($sql) {
        $this->query($sql);
        return $this->conn->affected_rows;
    }
    
    // Prepare statement
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    // Escape string
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
    
    // Close connection
    public function close() {
        $this->conn->close();
    }
}

// Create database instance
$db = new Database();
?>