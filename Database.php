<?php
/*
 * 
 * original 
 * @Author Rory Standley <rorystandley@gmail.com>
 * @Version 1.3
 * @Package Database
 * https://github.com/rorystandley/MySQL-CRUD-PHP-OOP
 * changes by vince
 * 2015-11-25 re-code to work with postgresql,
 * remove " JOIN " from the join key word, the user has to insert it, becuase of the syntax
 * NATURAL JOIN or JOIN INNER or ... several other types of JOINs
 * 
 * Add methods for transactions
 * begin,rollback,commit, and end.
 * 
 * Add support for insert ..... RETURNING mycolumn
 * 
 * 
 */
class Database{
	/* 
	 * Create variables for credentials to the database
	 * The variables have been declared as private. This
	 * means that they will only be available with the 
	 * Database class
	 */
	private $db_host;
	private $db_user;
	private $db_pass;
	private $db_name;
	
	private $myconn;
	/*
	 * Extra variables that are required by other function such as boolean con variable
	 */
	private $con = false; 		// Check to see if the connection is active
	private $result = array(); 	// Any results from a query will be stored here
    private $myQuery = "";		// used for debugging process with SQL return
    private $numResults = "";	// used for returning the number of rows
	
	// public function connect()   * note don't make this a constructor because they can't return a value ( did it work? )
	public function connect($db_host,$db_user,$db_pass,$db_name=null){
		// this gets called in php5.
		$this->db_host=$db_host;
		$this->db_user=$db_user;
		$this->db_pass=$db_pass;
		if (!$db_name) {
			$this->db_name=$db_user;
		}
		if(!$this->con){
			// note '@' suppresses error messages.
			$this->myconn = @pg_connect("host=".$this->db_host .  
					" user=".$this->db_user . 
					" password=".$this->db_pass . 
					" dbname=".$this->db_name);  // pg_connect() with variables defined at the start of Database class
            if($this->myconn){
            	$seldb = @pg_dbname($this->myconn); // Credentials have been pass through pg_connect() now select the database
                if($seldb){
                	$this->con = true;
                	$this->numResults=1;
                    return true;  // Connection has been made return TRUE
                }else{ 
					$this->result = error_get_last();
                    return false;  // Problem selecting database return FALSE
                }  
            }else{
            	$this->result=error_get_last();
                return false; // Problem connecting return FALSE
            }  
        }else{  
            return true; // Connection has already been made return TRUE 
        }  	
	}
	
	// Function to disconnect from the database
    public function __destruct(){
    	// If there is a connection to the database
    	if($this->con){
    		// We have found a connection, try to close it
    		if(@pg_close($this->myconn)){
    			// We have successfully closed the connection, set the connection variable to false
    			$this->con = false;
				// Return true tjat we have closed the connection
				return true;
			}else{
				// We could not close the connection, return false
				return false;
			}
		}
    }
    
    // function to begin a transaction
    public function begin() {
    	if ( $this->con) {
    		pg_query($this->myconn,"BEGIN");
    		return true;
    	} else {
    		$this->result['message'] = "Not connected to the database.";
    		return false;
    	}
    }
    
    // function to rollback a transaction
    public function rollback() {
    	pg_query($this->myconn,"ROLLBACK");
    	return true;
    }
    
    // function to commit a transaction
    public function commit() {
    	pg_query($this->myconn,"COMMIT");
    	return true;
    }
    
    // function to commit a transaction
    public function end() {
    	pg_query($this->myconn,"COMMIT");
    	return true;
    }
    
	// function to run a  query
	public function sql($sql){
		$query = @pg_query($this->myconn,$sql);
        $this->myQuery = $sql; // Pass back the SQL
		if($query){
			// If the query returns >= 1 assign the number of rows to numResults
			$this->numResults = pg_num_rows($query);
			// Loop through the query results by the number of rows returned
			for($i = 0; $i < $this->numResults; $i++){
				$r = pg_fetch_array($query);
               	$key = array_keys($r);
               	for($x = 0; $x < count($key); $x++){
               		// Sanitizes keys so only alphavalues are allowed
                   	if(!is_int($key[$x])){
                   		if(pg_num_rows($query) >= 1){
                   			$this->result[$i][$key[$x]] = $r[$key[$x]];
						}else{
							$this->result = null;
						}
					}
				}
			}
			return true; // Query was successful
		}else{
			array_push($this->result,error_get_last());
			return false; // No rows where returned
		}
	}
	
	// Function to SELECT from the database
	public function select($table, $rows = '*', $join = null, $where = null, $order = null, $limit = null){
		// Create query from the variables passed to the function
		$q = 'SELECT '.$rows.' FROM '.$table;
		if($join != null){
			// $q .= ' JOIN '.$join;
			$q .= " ".$join;
		}
        if($where != null){
        	$q .= ' WHERE '.$where;
		}
        if($order != null){
            $q .= ' ORDER BY '.$order;
		}
        if($limit != null){
            $q .= ' LIMIT '.$limit;
        }
        $this->myQuery = $q; // Pass back the SQL
		// Check to see if the table exists
        if($this->tableExists($table)){
        	// The table exists, run the query
        	$query = @pg_query($this->myconn,$q);
			if($query){
				// If the query returns >= 1 assign the number of rows to numResults
				$this->numResults = pg_affected_rows($query);
				// Loop through the query results by the number of rows returned
				for($i = 0; $i < $this->numResults; $i++){
					$r = pg_fetch_array($query);
                	$key = array_keys($r);
                	for($x = 0; $x < count($key); $x++){
                		// Sanitizes keys so only alphavalues are allowed
                    	if(!is_int($key[$x])){
                    		if(pg_affected_rows($query) >= 1){
                    			$this->result[$i][$key[$x]] = $r[$key[$x]];
							}else{
								$this->result = null;
							}
						}
					}
				}
				return true; // Query was successful
			}else{
				$this->result['message'] = error_get_last();
				$this->result['sql'] = $q;
				return false; // No rows were returned
			}
      	}else{
      		$this->result['sql'] = $q;
      		return false; // Table does not exist
    	}
    }
	
	// Function to insert into the database
    public function insert($table,$params=array(),$returning=null){
    	// Check to see if the table exists
    	 if($this->tableExists($table)){
    	 	// $sql='INSERT INTO `'.$table.'` (`'.implode('`, `',array_keys($params)).'`) VALUES ("' . implode('", "', $params) . '")';
    	 	$sql='INSERT INTO '.$table.' ('.implode(', ',array_keys($params)).") VALUES ('" . implode("','", $params)."')";
    	 	if ($returning) {
    	 		$sql .= " returning ". $returning;
    	 	}
    	 	$this->myQuery = $sql; // Pass back the SQL
            // Make the query to insert to the database
            if($ins = @pg_query($this->myconn,$sql)){
            	if ($returning) {
            		$this->result= pg_fetch_all($ins);
            	}
            	else {
            		array_push($this->result,true);
            	}
            	$this->numResults = pg_affected_rows($ins);
                return true; // The data has been inserted
            }else{
            	// echo pg_get_result($this->myconn);
            	$this->result = error_get_last();
            	$this->result['sql'] = $sql;
            	$this->numResults=0;
            	return false; // The data has not been inserted
            }
        }else{
        	return false; // Table does not exist
        }
    }
	
	//Function to delete table or row(s) from database
    public function delete($table,$where = null){
    	// Check to see if table exists
    	 if($this->tableExists($table)){
    	 	// The table exists check to see if we are deleting rows or table
    	 	if($where == null){
                $delete = 'DROP TABLE '.$table; // Create query to delete table
            }else{
                $delete = 'DELETE FROM '.$table.' WHERE '.$where; // Create query to delete rows
            }
            // Submit query to database
            if($del = @pg_query($this->myconn,$delete)){
                $this->myQuery = $delete; // Pass back the SQL
                $this->numResults=pg_affected_rows($del);  // get number deleted, I hope.
                return true; // The query exectued correctly
            }else{
            	$this->result = error_get_last();
            	$this->result['sql'] = $delete;
         		$this->numResults=0;
            	return false; // The query did not execute correctly
            }
        }else{
            return false; // The table does not exist
        }
    }
	
	// Function to update row in database
    public function update($table,$params=array(),$where){
    	// Check to see if table exists
    	if($this->tableExists($table)){
    		// Create Array to hold all the columns to update
            $args=array();
			foreach($params as $field=>$value){
				// Seperate each column out with it's corresponding value
				$args[]=$field."='".$value."'";
			}
			// Create the query
			$sql='UPDATE '.$table.' SET '.implode(',',$args).' WHERE '.$where;
			// Make query to database
            $this->myQuery = $sql; // Pass back the SQL
            if($query = @pg_query($this->myconn,$sql)){
            	$this->numResults = pg_affected_rows($query);
            	array_push($this->result,pg_affected_rows($query));
            	return true; // Update has been successful
            }else{
            	$this->numResults=0;
            	$this->result = error_get_last();
            	$this->result['sql'] = $sql;
                return false; // Update has not been successful
            }
        }else{
            return false; // The table does not exist
        }
    }
	
	// Private function to check if table exists for use with queries
	private function tableExists($table){
		//$tablesInDb = @pg_query('SHOW TABLES FROM '.$this->db_name.' LIKE "'.$table.'"');
		$tablesInDb = @pg_query($this->myconn,"SELECT tablename FROM pg_tables WHERE tablename = '$table'");
        if($tablesInDb){
        	if(pg_num_rows($tablesInDb)==1){
                return true; // The table exists
            }else{
            	$this->result['table_error'] = "The table \"". $table."\" does not exist in this database.";
                return false; // The table does not exist
            }
        }
    }
	
	// Public function to return the data to the user
    public function getResult(){
        $val = $this->result;
        $this->result = array();
        return $val;
    }
    //Pass the SQL back for debugging
    public function getSql(){
        $val = $this->myQuery;
        $this->myQuery = array();
        return $val;
    }
    //Pass the number of rows back
    public function numRows(){
        $val = $this->numResults;
        $this->numResults = array();
        return $val;
    }
    // Escape your string
    public function escapeString($data){
        return pg_escape_string($this->myconn,$data);
    }
} 