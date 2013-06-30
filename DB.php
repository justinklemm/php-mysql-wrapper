<?php


class DB {


	private static $numQueries = 0;
	private static $executedQueries = array();


	public static function getNumQueries()
	{
		return self::$numQueries;
	}
	
	public static function getExecutedQueries()
	{
		return self::$executedQueries;
	}


	public static function connect($host, $db, $user, $pass)
	{
		$result = @mysql_connect($host, $user, $pass);
		
		if(!$result) die('Could not connect to database.');
		
		if($db != '') mysql_select_db($db);

		return true;
	}


	public static function disconnect()
	{
		return mysql_close();
	}


	public static function createDatabase($db)
	{
		return mysql_create_db($db);
	}


	public static function listDbs()
	{
		$result = array();

		$list = mysql_list_dbs();

		while ($row = mysql_fetch_row($list))
		{
			$result[] = $row[0];
		}

		return $result;
	}


	public static function listTables($db)
	{
		$result = array();

		$list = mysql_list_tables($db);

		while ($row = mysql_fetch_row($list))
		{
			$result[] = $row[0];
		}

		return $result;
	}


	public static function listFields($db, $table)
	{
		$result = array();

		$list = mysql_list_fields($db, $table);

		while ($row = mysql_fetch_row($list))
		{
			$result[] = $row[0];
		}

		return $result;
	}


	public static function query($query)
	{
		global $_CONFIG;

		$result = mysql_query($query);

		if($_CONFIG->testing_mode && $error = mysql_error()){
			echo('<pre>');
			echo("<strong>Database Error</strong>: $error\n\n<strong>Query</strong>: $query\n\n");
			print_r(debug_backtrace());
			echo('</pre>');
		}

		self::$numQueries++;
		self::$executedQueries[] = trim(str_replace("\t", '  ', $query));

		return $result;
	}


	public static function getRow(&$resource)
	{
		return mysql_fetch_assoc($resource);
	}


	public static function getObject(&$resource)
	{
		return mysql_fetch_object($resource);
	}


	public static function dataSeek(&$resource, $row = 0)
	{
		$numRows = self::rowsInResource($resource);

		if($numRows == 0 || $row < 0 || $row >= $numRows){
			return false;
		}

		mysql_data_seek($resource, $row);

		return true;
	}


	public static function queryArray($query, $single = false, $keyField = '', $valField = '')
	{
		$items = array();

		$result = self::query($query);
		
		if($keyField != ''){
			if($valField != ''){
				while($row = self::getRow($result)){ $items[$row[$keyField]] = $row[$valField]; }
			}else{
				while($row = self::getRow($result)){ $items[$row[$keyField]] = $row; }
			}
		}else{
			if($valField != ''){
				while($row = self::getRow($result)){ $items[] = $row[$valField]; }
			}else{
				while($row = self::getRow($result)){ $items[] = $row; }
			}
		}

		if($single && count($items) > 0){
			 $first = array_slice($items, 0, 1);
			 return $first[0];
		}
		
		unset($result);

		return $items;
	}


	public static function selectArray($table, $fields = '*', $match = '', $orderBy = '', $orderDesc = false, $limit = '', $groupBy = '')
	{
		$query = "SELECT $fields FROM `$table` WHERE 1 ";
		if(is_array($match)){
			foreach($match as $col => $val)
			{
				if(strpos($col, '?') !== false){
					$query .= " AND ".str_replace('?', self::quote($val), $col)." ";
				}else{
					$query .= " AND $col = ".self::quote($val);
				}
			}
		}

		if($groupBy != '')
			$query .= " GROUP BY $groupBy ";

		if($orderBy != '') 		$query .= " ORDER BY $orderBy ";
		if($orderDesc) 			$query .= " DESC ";
		if($limit != '') 		$query .= " LIMIT $limit ";
		
		return self::queryArray($query);
	}


	public static function select($table, $fields = '*', $match = '', $orderBy = '')
	{
		$query = "SELECT $fields FROM `$table` WHERE 1 ";
		if(is_array($match)){
			foreach($match as $col => $val)
			{
				if(strpos($col, '?') !== false){
					$query .= " AND ".str_replace('?', self::quote($val), $col)." ";
				}else{
					$query .= " AND $col = ".self::quote($val);
				}
			}
		}
		
		if($orderBy != '') $query .= " ORDER BY $orderBy ";
		
		unset($match);

		return self::query($query);
	}


	public static function insert($table, $data, $dateField = '', $replace = false, $ignore = false, $updateOnDuplicate = false)
	{
		$setQuery = '';
		
		if($replace){
			$query = "REPLACE INTO `$table` ";
		}else{
			$query = "INSERT ".($ignore ? 'IGNORE ' : '')."INTO `$table` ";
		}
		
		if(is_array($data) || $dateField){
			$query .= " SET ";
			if(is_array($data)) foreach($data as $field => $val)
			{
				$setQuery .= " `$field` = ".self::quote($val).', ';
			}
			if($dateField != '' && !isset($data[$dateField])){
				$setQuery .= " `$dateField` = NOW() ";
			}else{
				$setQuery = substr($setQuery, 0, strlen($setQuery) - 2);
			}
		}
		
		$query .= $setQuery;
		
		if($updateOnDuplicate){
			$query .= " ON DUPLICATE KEY UPDATE ".$setQuery;
		}
		
		self::query($query);
		
		unset($data);

		return mysql_insert_id();
	}



	public static function update($table, $data, $match = '', $dateField = '', &$affectedRows = null)
	{
		if(!is_array($data) || count($data) == 0){
			if($affectedRows !== null)
				$affectedRows = 0;
			return false;
		}

		$query = "UPDATE `$table` SET ";
		foreach($data as $field => $val)
		{
			$query .= " `$field` = ".self::quote($val).', ';
		}
		if($dateField != '')
			$query .= " `$dateField` = NOW() ";
		else
			$query = substr($query, 0, strlen($query) - 2);

		$query .= " WHERE 1 ";

		if(is_array($match)){
			foreach($match as $col => $val)
			{
				if(strpos($col, '?') !== false){
					$query .= " AND ".str_replace('?', self::quote($val), $col)." ";
				}else{
					$query .= " AND $col = ".self::quote($val);
				}
			}
		}

		self::query($query);

		if($affectedRows !== null) $affectedRows = self::affectedRows();
		
		unset($data);
		unset($match);

		return true;
	}


	public static function incrementField($table, $field, $increment = 1, $match = '', &$affectedRows = null)
	{
		$query = "UPDATE `$table` SET `$field` = `$field` + $increment WHERE 1 ";

		if(is_array($match)){
			foreach($match as $col => $val)
			{
				if(strpos($col, '?') !== false){
					$query .= " AND ".str_replace('?', self::quote($val), $col)." ";
				}else{
					$query .= " AND $col = ".self::quote($val);
				}
			}
		}

		self::query($query);

		if($affectedRows !== null) $affectedRows = self::affectedRows();

		unset($data);
		
		return true;
	}


	public static function delete($table, $match = '', &$affectedRows = null)
	{
		$query = "DELETE FROM `$table` WHERE 1 ";

		if(is_array($match)){
			foreach($match as $col => $val)
			{
				if(strpos($col, '?') !== false){
					$query .= " AND ".str_replace('?', self::quote($val), $col)." ";
				}else{
					$query .= " AND $col = ".self::quote($val);
				}
			}
		}

		self::query($query);

		if($affectedRows !== null) $affectedRows = self::affectedRows();
		
		unset($match);

		return true;
	}


	public static function numRows($table, $match = '')
	{
		$query = "SELECT COUNT(*) AS rows FROM `$table` WHERE 1 ";

		if(is_array($match)){
			foreach($match as $col => $val)
			{
				if(strpos($col, '?') !== false){
					$query .= " AND ".str_replace('?', self::quote($val), $col)." ";
				}else{
					$query .= " AND $col = ".self::quote($val);
				}
			}
		}

		$result = self::query($query);
		
		unset($match);

		if($row = self::getRow($result))
			return $row['rows'];
			

		return 0;
	}


	public static function rowsInQuery($query)
	{
		$result = self::query($query);
		return mysql_num_rows($result);
	}


	public static function rowsInResource(&$resource)
	{
		return mysql_num_rows($resource);
	}


	public static function affectedRows()
	{
		return mysql_affected_rows();
	}


	public static function quote($val, $includeQuotes = true)
	{
		if (get_magic_quotes_gpc())
			$val = stripslashes($val);

	    if (strtolower($val) == 'null') {
	    	$val = " NULL ";
	    }else if($includeQuotes){
	        $val = " '" . mysql_real_escape_string($val) . "' ";
	    }else{
	    	$val = mysql_real_escape_string($val) ;
	    }

	    return $val;
	}


}
