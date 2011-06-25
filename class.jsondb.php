<?php
function slice($array, $start, $end = null, $step = 1) {
	$len = count($array);
	if( !$end ) {
		$end = $len;}
	if( !is_int($step) || $step == 0 ) {
		$step = 1;}
	if( $start > $end ) {
		list($start, $end) = array($end, $start);}

	if( $step == 1 ) {
		return array_slice($array,$start,$end - $start);}

	$result = array();
	for($i = $start; $i < $end; $i+=$step) {
		$result[] = $array[$i];}
	return $result;
}

class jsondb implements Countable {
	private $dbfilename;
	public static $data;


	public function __construct($dbname) {
		$this->dbfilename = dirname(__FILE__)."/db/db-".$dbname.".json";

		if( !is_array(self::$data) ) {
			self::$data = array();
		}

		if( !isset(self::$data[$this->dbfilename]) ) {
			$contents = @file_get_contents($this->dbfilename);
			if( $contents ) {
				self::$data[$this->dbfilename] = json_decode($contents);}
			else {
				self::$data[$this->dbfilename] = new stdClass;}}
	}


	public function save() {
		$fh = fopen($this->dbfilename,'w');
		fwrite($fh, json_encode(self::$data[$this->dbfilename]));
		fclose($fh);
	}


	public function clear() {
		self::$data[$this->dbfilename] = new stdClass;
		$this->save();
	}


	public function create($entry) {
		$entry = (object) $entry;
		if( !isset($entry->id) ) {
			$maxval = 0;
			foreach($this->all() as $x) {
				$idnum = intval(substr($x->id,2));
				if( $idnum >= $maxval ) {
					$maxval = $idnum+1;}}
			$id = 'id'.$maxval;
			$entry->id = $id;}
		else {
			$id = $entry->id;}
		self::$data[$this->dbfilename]->{$id} = $entry;
		return $entry;
	}


	public function read($id) {
		return self::$data[$this->dbfilename]->{$id};
	}


	public function update($id, $entry) {
		$entry = (object) $entry;
		$entry->id = $id;
		self::$data[$this->dbfilename]->{$id} = $entry;
	}


	public function delete($id) {
		unset(self::$data[$this->dbfilename]->{$id});
	}

	
	public static function keypath($entry, $keypath) {
		// For now, just assume that keypaths are always top-level names
		return $entry->{$keypath};
	}


	public function all() {
		return new jsondbIterator($this, array_keys(get_object_vars(self::$data[$this->dbfilename])));
	}
	public function reverse() {
		return $this->all()->reverse();
	}
	public function sort($key) {
		return $this->all()->sort($key);
	}
	public function filter($key, $condition) {
		return $this->all()->filter($key, $condition);
	}
	public function slice($start, $end=null, $step=1) {
		return $this->all()->slice($start, $end, $step);
	}
	public function item($n) {
		return $this->all()->item($n);
	}
	public function first() {
		return $this->item(0);
	}
	public function last() {
		return $this->item(-1);
	}

	public function count() {
		return $this->all()->count();
	}
}


class jsondbIterator implements Iterator, ArrayAccess, Countable {
	private $db;
	private $indexes;
	private $i;


	public function __construct($db, $indexes) {
		$this->db = $db;
		$this->indexes = $indexes;
		$this->i = 0;
	}


	public function count() {
		return count($this->indexes);
	}

	public function current() {
		return $this->db->read($this->indexes[$this->i]);
	}
	public function key() {
		return $this->i;
	}
	public function next() {
		$this->i++;
	}
	public function rewind() {
		$this->i = 0;
	}
	public function valid() {
		return $this->i < count($this->indexes);
	}


	public function reverse() {
		return new jsondbIterator($this->db, array_reverse($this->indexes));
	}


	public function slice($start, $end=null, $step=1) {
		return new jsondbIterator($this->db, slice($this->indexes, $start, $end, $step));
	}


	public function filter($key, $condition) {
		$temp = array();
		foreach($this->indexes as $i) {
			$entry = $this->db->read($i);
			if( $this->test(jsondb::keypath($entry, $key), $condition) ) {
				$temp[] = $i;}}
		return new jsondbIterator($this->db, $temp);
	}


	public function sort($key) {
		$temp = array();
		foreach($this->indexes as $i) {
			$entry = $this->db->read($i);
			$temp[$i] = jsondb::keypath($entry, $key);
		}
		asort($temp);
		return new jsondbIterator($this->db, array_keys($temp));
	}


	private function test($val, $cond) {
		if( is_array( $cond ) ) {
			switch($cond[0]) {
			case "between":
				return ($cond[1] <= $val) && ($val <= $cond[2]);
			case "<":
			case "lt":
				return $val < $cond[1];
			case "<=":
			case "lte":
				return $val <= $cond[1];
			case ">":
			case "gt":
				return $val > $cond[1];
			case ">=":
			case "gte":
				return $val >= $cond[1];
			case "!=":
			case "ne":
				return $val != $cond[1];
			case "==":
			case "eq":
				return $val == $cond[1];
			case "in":
				return false !== array_search($val, $cond[1]);}}
		else {
			return $val == $cond;}
		return true;
	}


	/* Manipulate the indexes directly. */
	public function offsetExists($offset) {
		return $offset >= 0 && $offset < count($this->indexes);
	}
	public function offsetGet($offset) {
		return $this->indexes[$offset];
	}
	public function offsetSet($offset, $value) {
		if( is_int($value) ) {
			$this->indexes[$offset] = $value;
		}
	}
	public function offsetUnset($offset) {
		unset( $this->indexes[$offset] );
	}


	public function item($index) {
		if( $index < 0 ) {
			$index = count($this->indexes) + $index;}
		return $this->db->read($this->indexes[$index]);
	}
	public function first() {
		return $this->item(0);
	}
	public function last() {
		return $this->item(-1);
	}
}
		
?>
