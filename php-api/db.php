<?php

 trait DBConnection{
    protected mysqli $connection;
    public string $mysqliConnectionMessage;

    private $hostname = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'test';


    public function connectToDB(
        string|null $hostname = null,
        string|null $username = null,
        string|null $password = null,
        string|null $database = null,
    ){
        if(is_string($hostname)) $this->hostname = $hostname; // 'localhost';
        if(is_string($username)) $this->username = $username; // 'root';
        if(is_string($password)) $this->password = $password; // '';
        if(is_string($database)) $this->database = $database; // 'test';

        try {
            $this->connection = mysqli_connect($this->hostname, $this->username, $this->password, $this->database);
            
            if($this->connection){
                $this->mysqliConnectionMessage = 'mySql DB Connected!';
            }else $this->mysqliConnectionMessage = 'Failed to mySql DB Connection!';
        } catch (Throwable $throwable) {
            $this->mysqliConnectionMessage = $throwable->getMessage();
        }
    }
}

interface QueryBuilderType {
    public function __construct();
    // public function name(string $firstName);
}

abstract class Query{
    use DBConnection;
    public string $timestamp;
    public string $timezone = ' GMT+0200';

    public function __construct() {
        // $this->tableName = $tableName;
        $this->timestamp = date('d M Y h:i:s') . $this->timezone;
        $this->connectToDB();
    }
 
    protected $tableName;
    protected $encodedPrettyPrint = false;
    protected $encoded = true;
    public string $limitationQueryString = '';
    // public string $orderBy = '';
    public string $orderByQueryString = '';
    public array $whereQueryArray = [];
    public array $orWhereQueryArray = [];
    public array $notWhereQueryArray = [];
    public array $orNotWhereQueryArray = [];
    protected $fillable;
    protected $hidden;
    protected $enableIdAndDatetime = false;
    public string $query = '';

    protected function getWhereQueryString(){
        if(empty($this->whereQueryArray)) return '';
        return " WHERE " . implode(" AND ", $this->whereQueryArray);
    }
    
    protected function getOrWhereQueryString(){
        if(empty($this->orWhereQueryArray)) return '';
        return " OR " . implode(" AND OR ", $this->orWhereQueryArray);
    }
    
    protected function getNotWhereQueryString(){
        if(empty($this->notWhereQueryArray)) return '';
        return " NOT " . implode(" AND NOT ", $this->notWhereQueryArray);
    }
    
    protected function getOrNotWhereQueryString(){
        if(empty($this->orNotWhereQueryArray)) return '';
        return " OR NOT " . implode(" AND OR NOT ", $this->orNotWhereQueryArray);
    }

    public function whereQueryString(){
        $where = $this->getWhereQueryString();
        $orWhere = $this->getOrWhereQueryString();
        $notWhere = $this->getNotWhereQueryString();
        $orNotWhere = $this->getOrNotWhereQueryString();

        $whereAndOr = $where.$orWhere;
        $notWhereAndOrNot = $notWhere.$orNotWhere;
        
        $whereQuery = $whereAndOr;
        if(!empty($notWhereAndOrNot) && !empty($whereAndOr)){
            $whereQuery .= ' AND ' . $notWhereAndOrNot;
        }elseif(empty($whereAndOr)){
            echo "PASS\n";
            $whereQuery = ' WHERE ' . $notWhereAndOrNot;
        }

        return $whereQuery;
    }
} 

// SELECT * FROM tasks WHERE id=1 AND type='income' OR id=2 AND NOT id=3 OR NOT id=4

abstract class QueryBuilder extends Query implements QueryBuilderType {
    public function where(string $column, string|int $value, string|null $operator = null, string $scope = 'whereQueryArray'){
        $columnValue = is_string($value) ? "'{$value}'" : $value;
        $this->{$scope}[] = "{$column}={$columnValue}";
        
        return $this;
    }

    public function orWhere(string $column, string|int $value, string|null $operator = null){
        return $this->where($column, $value, $operator, 'orWhereQueryArray');
    }

    public function notWhere(string $column, string|int $value, string|null $operator = null){
        return $this->where($column, $value, $operator, 'notWhereQueryArray');
    }

    public function orNotWhere(string $column, string|int $value, string|null $operator = null){
        return $this->where($column, $value, $operator, 'orNotWhereQueryArray');
    }

    public function executeQuery(){
        return null;
    }

    public function latest(string $column = 'id'){
        $this->orderByQueryString = "ORDER BY {$column} DESC";
        
        return $this;
    }

    public function oldest(string $column = 'id'){
        $this->orderByQueryString = "ORDER BY {$column} ASC";
        return $this;
    }

    public function orderBy(string $column, string $type = 'ASC'){
        $this->orderByQueryString = "ORDER BY {$column} {$type}";
        return $this;
    }

    public function limit(int $count){
        $this->limitationQueryString = "LIMIT {$count}";
        return $this;
    }

    public function notEncoded(){
        $this->encoded = false;
        $this->encodedPrettyPrint = false;

        return $this;
    }

    public function get(){
        $whereQueryString = $this->whereQueryString();
        $this->query = "SELECT * FROM {$this->tableName} {$whereQueryString} {$this->orderByQueryString} {$this->limitationQueryString};";
        $builder = mysqli_query($this->connection, $this->query);

        if($this->encoded){
            $rows = mysqli_fetch_all($builder, MYSQLI_ASSOC);
            return $this->encodedPrettyPrint ? json_encode($rows, JSON_PRETTY_PRINT) : json_encode($rows);
        }

        return mysqli_fetch_all($builder);
    }

    public function findOne(int|string $id, string $column = 'id'){
        $filterId = is_numeric($id) ? $id : "'{$id}'";
        $this->query = "SELECT * FROM {$this->tableName} WHERE {$column}={$filterId} {$this->orderByQueryString} LIMIT 1;";
        $builder = mysqli_query($this->connection, $this->query);
        
        if($this->encoded){
            $rows = mysqli_fetch_assoc($builder);
            return $this->encodedPrettyPrint ? json_encode($rows, JSON_PRETTY_PRINT) : json_encode($rows);
        }

        return mysqli_fetch_assoc($builder);
    }
    
    public function find(int $limit = 1){
        $whereQueryString = $this->whereQueryString();
        
        $this->query = "SELECT * FROM {$this->tableName} {$whereQueryString} {$this->orderByQueryString} LIMIT {$limit};";
        $builder = mysqli_query($this->connection, $this->query);

        if($limit > 1) {
            $rows = $this->encoded ? mysqli_fetch_all($builder, MYSQLI_ASSOC) : mysqli_fetch_all($builder);
        }else{
            $rows = mysqli_fetch_assoc($builder);
        }

        if($this->encoded) return $this->encodedPrettyPrint ? json_encode($rows, JSON_PRETTY_PRINT) : json_encode($rows);

        return $rows;
    }

    public function insert(array $row, bool $new = true){
        $rowKeys = array_keys($row);
        $rowValues = array_map(function($value){
            $mysqliStr = mysqli_real_escape_string($this->connection, $value);
            return is_numeric($mysqliStr) ? $mysqliStr : "'{$mysqliStr}'";
        }, $row);

        if($this->enableIdAndDatetime){
            array_push($rowKeys, 'created_at', 'updated_at');
            array_push($rowValues, "'{$this->timestamp}'", "'{$this->timestamp}'");
        }

        $keys = implode(", ", $rowKeys);
        $values = implode(", ", $rowValues);
        $this->query = "INSERT INTO {$this->tableName} ({$keys}) VALUES ({$values});";
        
        $result = mysqli_query($this->connection, $this->query);
        $id = mysqli_insert_id($this->connection);

        if($result && $new){
            return $this->findOne($id);
        }

        return $result;
    }

    public function update(array $row){
        $rowValues = [];

        foreach($row as $key => $value){
            $mysqliStr = mysqli_real_escape_string($this->connection, $value);
            $pureStr = is_numeric($mysqliStr) ? $mysqliStr : "'{$mysqliStr}'";

            $rowValues[] = "{$key}={$pureStr}";
        }

        $values = implode(", ", $rowValues);
        $whereQueryString = $this->whereQueryString();
        $this->query = "UPDATE {$this->tableName} SET {$values} {$whereQueryString}";

        $result = mysqli_query($this->connection, $this->query);

        return $result;
    }

    public function updateAndFindOne(array $row){
        $result = $this->update($row);

        if($result){
            return $this->find();
        }

        return $result;
    }

    public function updateAndFindMany(array $row){
        $result = $this->update($row);

        if($result){
            return $this->get();
        }

        return $result;
    }

    public function delete(){
        $whereQueryString = $this->whereQueryString();
        $query = "DELETE * FROM {$this->tableName} {$whereQueryString}";

        $result = mysqli_query($this->connection, $query);
        return $result;
    }
}


final class Model extends QueryBuilder { 
    protected $tableName = 'tasks';
    protected $fillable = ['title', 'amount', 'type'];
    protected $enableIdAndDatetime = true; 
    protected $encodedPrettyPrint = true;
    public $name = ['SYED AMIR ALI'];
}
// $QB = new QueryBuilder('tasks');
$QB = new Model();
// $insert = $QB->insert(['title'=> 'Title 4th!', 'type'=> 'income', 'amount'=> 500]);
$update = $QB->where('type', 'expense')->updateAndFindMany(['amount'=> '100']);
echo $update;

echo "\n\n\n";
// echo $QB->{"name"}[0];

// echo $QB->where('id', 1)->orWhere('id', 2)->notWhere('id', 3)->where('type', 'income')->find(2);
// echo is_numeric(intval('xxxxxxxxxxxx')) ? 'TRUE' : 'FALSE';
// echo "\n";
// echo number('50x');
// echo json_encode(compact('insert'), 128);
// print_r($QB->get(0,1));
// echo $QB->latest()->limit(1)->get();
// echo QueryBuilder::latest();
// $QB->where('name')->executeQuery();
// echo json_encode(QueryBuilder::name(), JSON_PRETTY_PRINT);
// echo QueryBuilder::name("\n hello \n");
echo "\n\n\n";

