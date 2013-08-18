<?php

class DB {

	private static $db = null;
	private static $numQueries = 0;
	private static $executedQueries = array();


	public static function getNumQueries () {
		return self::$numQueries;
	}
	

	public static function getExecutedQueries () {
		return self::$executedQueries;
	}


	public static function connect ($host, $db, $user, $pass) {
		$db = new mysqli($host, $user, $pass, $db);
		if ($db->connect_errno) die($db->connect_error);
		self::$db = $db;
		return true;
	}


	public static function disconnect () {
		if (!self::$db) return false;
		self::$db->close();
	}


	public static function selectDatabase ($name) {
		self::$db->select_db($name);
	}


	public static function createDatabase ($name) {
		if (!self::$db) return false;
		return self::$db->query(" CREATE DATABASE `{$name}` ");
	}


	public static function query ($query) {
		if (!self::$db) return false;

		self::$numQueries++;
		self::$executedQueries[] = $query;

		return self::$db->query($query);;
	}


	public static function queryArray ($query, $single = false, $keyField = null, $valField = null) {
		if (!self::$db) return false;

		$items = array();
		$result = self::query($query);
		
		if ($keyField && $valField) {
			while ($row = self::getRow($result)) { $items[$row[$keyField]] = $row[$valField]; }
		} else if ($keyField) {
			while ($row = self::getRow($result)) { $items[$row[$keyField]] = $row; }
		} else if ($valField) {
			while ($row = self::getRow($result)) { $items[] = $row[$valField]; }
		} else {
			while ($row = self::getRow($result)) { $items[] = $row; }
		}
		$result->free();
		unset($result);

		if ($single && count($items) > 0) {
			 $first = array_slice($items, 0, 1);
			 return $first[0];
		}
		
		return $items;
	}



	public static function select ($table, $fields = '*', $match = null, $orderBy = null, $orderDesc = false, $limit = null, $groupBy = null) {
		return self::query(self::buildSelect($table, $fields, $match, $orderBy, $orderDesc, $limit, $groupBy));
	}


	public static function selectArray ($table, $fields = '*', $match = null, $orderBy = null, $orderDesc = false, $limit = null, $groupBy = null) {
		return self::queryArray(self::buildSelect($table, $fields, $match, $orderBy, $orderDesc, $limit, $groupBy));
	}


	public static function insert ($table, $data, $dateField = null, $replace = false, $ignore = false, $updateOnDuplicate = false) {
		if (!self::$db) return false;
		
		if ($replace) {
			$query = " REPLACE INTO `{$table}` ";
		} else {
			$query = " INSERT ".($ignore ? "IGNORE" : "")." INTO `{$table}` ";
		}

		$setQuery = self::buildSetQuery($data, $dateField);
		
		if ($setQuery) {
			$query .= " SET " . $setQuery;
			if ($updateOnDuplicate) $query .= " ON DUPLICATE KEY UPDATE " . $setQuery;
		}
		
		self::query($query);

		return self::$db->insert_id;
	}


	public static function update ($table, $data, $match = null, $dateField = null, &$affectedRows = null) {
		if (!self::$db) return false;

		if (!is_array($data) || count($data) == 0) {
			if ($affectedRows !== null) $affectedRows = 0;
			return false;
		}

		$query = " UPDATE `{$table}` SET ";
		$query .= self::buildSetQuery($data, $dateField);
		$query .= " WHERE 1 ";
		$query .= self::buildMatchQuery($match);
		self::query($query);

		if ($affectedRows !== null) $affectedRows = self::affectedRows();

		return true;
	}


	public static function delete ($table, $match = null, &$affectedRows = null) {
		if (!self::$db) return false;

		$query = " DELETE FROM `{$table}` WHERE 1 ";
		$query .= self::buildMatchQuery($match);
		self::query($query);

		if ($affectedRows !== null) $affectedRows = self::affectedRows();

		return true;
	}


	public static function incrementField ($table, $field, $increment = 1, $match = null, &$affectedRows = null) {
		if (!self::$db) return false;

		$query = " UPDATE `{$table}` SET `{$field}` = `{$field}` + {$increment} WHERE 1 ";
		$query .= self::buildMatchQuery($match);
		self::query($query);

		if ($affectedRows !== null) $affectedRows = self::affectedRows();
		
		return true;
	}


	public static function numRows ($table, $match = null) {
		if (!self::$db) return false;

		$query = " SELECT COUNT(*) AS rows FROM `{$table}` WHERE 1 ";
		$query .= self::buildMatchQuery($match);
		$result = self::query($query);
		
		if ($row = self::getRow($result)) return $row['rows'];

		return 0;
	}


	public static function rowsInQuery($query) {
		if (!self::$db) return false;

		$result = self::query($query);

		return $result->num_rows;
	}


	public static function getRow (&$resource) {
		return $resource->fetch_assoc();
	}


	public static function getObject (&$resource) {
		return $resource->fetch_object();
	}


	public static function dataSeek (&$resource, $row = 0) {
		$numRows = self::rowsInResource($resource);
		if ($numRows == 0 || $row < 0 || $row >= $numRows) return false;
		return $resource->data_seek($row);
	}


	public static function rowsInResource(&$resource) {
		return $resource->num_rows;
	}


	public static function affectedRows() {
		if (!self::$db) return false;

		return self::$db->affected_rows;
	}


	public static function quote($val, $includeQuotes = true) {
		if (!self::$db) return false;

		if (get_magic_quotes_gpc()) $val = stripslashes($val);

	    if (strtolower($val) == 'null') {
	    	$val = " NULL ";
	    }else if ($includeQuotes) {
	        $val = " '" . self::$db->real_escape_string($val) . "' ";
	    }else{
	    	$val = self::$db->real_escape_string($val) ;
	    }

	    return $val;
	}


	private static function buildMatchQuery ($match) {
		$query = "";
		if (is_array($match)) {
			foreach ($match as $col => $val) {
				if (strpos($col, '?') !== false) {
					$query .= " AND " . str_replace('?', self::quote($val), $col);
				}else{
					$query .= " AND $col = " . self::quote($val);
				}
			}
		}
		return $query;
	}


	private static function buildSetQuery ($data, $dateField = null) {
		$query = "";
		if (is_array($data) || $dateField) {
			if (is_array($data)) {
				foreach ($data as $field => $val) {
					$query .= " `{$field}` = ".self::quote($val).", ";
				}
			}
			if ($dateField && !isset($data[$dateField])) {
				$query .= " `{$dateField}` = NOW() ";
			} else {
				$query = substr($query, 0, strlen($query) - 2);
			}
		}
		return $query;
	}


	private static function buildSelect ($table, $fields = '*', $match = null, $orderBy = null, $orderDesc = false, $limit = null, $groupBy = null) {
		$query = " SELECT {$fields} FROM `{$table}` WHERE 1 ";
		$query .= self::buildMatchQuery($match);
		if ($groupBy)	$query .= " GROUP BY {$groupBy} ";
		if ($orderBy)	$query .= " ORDER BY {$orderBy} ";
		if ($orderDesc) $query .= " DESC ";
		if ($limit) 	$query .= " LIMIT {$limit} ";
		return $query;
	}

}
