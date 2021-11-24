<?php

class DB {
    private static ?PDO $pdo = null;

    public function __construct()
    {
        try {
            $this::$pdo = new PDO("mysql:host=" . $_ENV["DB_HOST"] . ";port=" . $_ENV["DB_PORT"] . ";dbname=" . $_ENV["DB_TABLE"], $_ENV["DB_USERNAME"], $_ENV["DB_PASSWORD"]);
            $this::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "<br/>";
            die();
        }
    }

    public static function query(string $sql, array $prepared = []) {
        $query = self::$pdo->prepare($sql);
        if($query)
            $query->execute($prepared);
        else
            print_r(self::$pdo->errorInfo());

        return $query;
    }
}