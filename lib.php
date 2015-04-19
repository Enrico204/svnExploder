<?php

class svnDump {
	public $UUID;
	public $version;
	public $filepath;
	public $fp;
	public $revmap = array();
	public $filemap = array();
	public $lastrevmap = array();

	/**
	 * Create an index file for an SVN dump
	 * @param string $dump Dump File
	 * @param string $ri Index file for revision
	 * @param type $fi Index file for nodes
	 * @param type $di Index file for deletes nodes
	 */
	public static function createIndexFile($dump, $ri, $fi, $di) {
		$revs = array();
		$files = array();
		$deletion = array();
		$currev = 0;
		$d = new svnDump($dump);
		while(!feof($d->fp)) {
			$curpos = ftell($d->fp);
			$node = svnNode::readNode($d, $currev);
			if($node == null) break;
			
			if($node->isRevision()) {
				$revs[] = $node->revision . "," . $curpos;
//				echo "Indexed revision " . $node->revision . "\n";
				$currev = $node->revision;
			} else {
				if($node->getAction() != "delete") {
					$files[] = "$currev,\"" . $node->getNodePath() . "\",$curpos";
//					echo "Indexed ";
//					if($node->isFile()) echo "file";
//					elseif($node->isDir()) echo "dir";
//					echo " " . $node->getNodePath() . "@$currev\n";
				} else {
					$deletion[] = "$currev,\"" . $node->getNodePath() . "\",$curpos";
				}
			}
		}
		$d = null;
		file_put_contents($ri, implode("\n", $revs));
		file_put_contents($fi, implode("\n", $files));
		file_put_contents($di, implode("\n", $deletion));
	}
	
	/**
	 * Create a new dump file for SVN
	 * @param string $path DUMP file path
	 * @param string $version Version
	 * @param string $uuid UUID
	 * @return \svnDump 
	 */
	public static function createDumpFile($path, $version, $uuid) {
		$fp = fopen($path, "wb");
		fwrite($fp, "SVN-fs-dump-format-version: $version\n\nUUID:$uuid\n\n");
		fclose($fp);
		
		$d = new svnDump($path, true);
		fseek($d->fp, 0, SEEK_END);
		return $d;
	}

	/**
	 * Constructor
	 * @param string $filepath Dump file to open
	 * @param string $writeable Open in read/write mode
	 * @throws Exception 
	 */
	public function __construct($filepath, $writeable=false) {
		if(is_string($filepath)) {
			$this->filepath = $filepath;
			$this->fp = fopen($filepath, (!$writeable) ? "rb" : "r+b");
			
			if($this->fp == null) {
				echo "Cannot load $filepath\n";
				exit;
			}
			
			// Read DUMP version
			$row = trim(fgets($this->fp), "\n");
			if(preg_match("/^SVN-fs-dump-format-version: ([0-9]*)$/i", $row, $m)) {
				$this->version = intval($m[1]);
			} else {
				throw new Exception("Version not supported");
			}
			// Trying to read UUID if exists ($oldpos = position to
			// return if UUID doesn't exists
			$oldpos = ftell($this->fp);
			while(!feof($this->fp)) {
				$row = trim(fgets($this->fp), "\n");
				if($row == "") continue;
				if(preg_match("/^UUID:(.*)$/im", $row, $m)) {
					$this->UUID = $m[1];
				} else {
					fseek($this->fp, $oldpos);
				}
				break;
			}
		} elseif(is_resource($filepath)) {
			$this->fp = $filepath;
			$this->filepath = ":memory";
		}
	}

	/**
	 * Load generated index files
	 * @param string $ri Revisions index file
	 * @param string $fi Nodes index file
	 * @param string $di Deleted nodes index file
	 * @throws Exception 
	 */
	public function loadIndexFile($ri, $fi, $di) {
		if(!file_exists($ri) || !file_exists($fi)) {
			throw new Exception();
			return;
		}
		// Carico indice revisioni
		$fp = fopen($ri, "rb");
		while($row = fgetcsv($fp)) {
			$this->revmap[$row[0]] = $row[1];
		}
		fclose($fp);
		// Carico indice file
		$fp = fopen($fi, "rb");
		while($row = fgetcsv($fp)) {
			$this->filemap[$row[0]][$row[1]] = $row[2];
		}
		fclose($fp);
		// Carico indice file eliminati
		$fp = fopen($di, "rb");
		while($row = fgetcsv($fp)) {
			$this->lastrevmap[$row[0]][$row[1]] = $row[2];
		}
		fclose($fp);
	}
	
	/**
	 * Add a new node
	 * @param svnNode $node 
	 */
	public function addNode(svnNode $node) {
//		echo "Add node called on " . $node->getNodePath() . "\n";
		foreach($node->headers as $header => $value) {
			fwrite($this->fp, "$header: $value\n");
		}
		fwrite($this->fp, "\n");
		if($node->blob != null && $node->getSize() > 0) {
			fwrite($this->fp, $node->blob);
			fwrite($this->fp, "\n\n");
		}
		fflush($this->fp);
	}
	
	/**
	 * Get the latest (actual) revision of the Subversion
	 * @return integer
	 */
	public function getLastRevision() {
		$revmap = array_keys($this->revmap);
		sort($revmap);
		return array_pop($revmap);
	}
}

class svnNode {
	public $headers = array();
	public $blob = null;
	public $d;
	public $revision = 0;

	/**
	 * @param svnDump $svnDumpObject
	 */
	public function __construct(svnDump $svnDumpObject) {
		$this->d = $svnDumpObject;
	}
	
	/**
	 * Reads a new node from svnNode object
	 * @param svnDump $d
	 * @param int $revision
	 * @return null|\svnNode 
	 */
	public static function readNode(svnDump $d, $revision=0) {
		$node = new svnNode($d);
		$noderead = false;
		// Scorro gli attributi del nodo
		while(!feof($d->fp)) {
			$row = trim(fgets($d->fp), "\n");
			
			if(trim($row) == "" && $noderead) {
				// Fine attributi nodo?
				$newSize = $node->getSize();
				if($newSize > 0)
					$node->blob = fread($d->fp, $newSize);
				break;
			} elseif(trim($row) == "" && !$noderead) {
				continue;
			} else {
				$noderead = true;
				$v = explode(":", $row, 2);
				$node->headers[$v[0]] = trim($v[1]);
			}
		}
		if(!$noderead) return null;
		
		if(!isset($node->headers["Revision-number"]))
			$node->revision = $revision;
		else
			$node->revision = $node->headers["Revision-number"];
		
		return $node;
	}

	/**
	 * Find a node
	 * @param svnDump $d
	 * @param string $nodePath
	 * @param int $rev
	 * @param bool $original If true, the node is the original node (if copied from)
	 * @return null 
	 */
	public static function find(svnDump $d, $nodePath, $rev, $original=false) {
		$testrev = $rev;
		$node = null;

//		echo "Searching $nodePath@$rev... ";
		for(; !isset($d->filemap[$testrev][$nodePath]) && $testrev > 0; $testrev--);

		if($testrev <= 0) {
//			echo "not found!\n";
			return null;
		}

//		echo "found on r$testrev\n";

		$oldseek = ftell($d->fp);

		fseek($d->fp, $d->filemap[$testrev][$nodePath]);
		$node = svnNode::readNode($d);

		if($nodePath != $node->getNodePath()) {
			die("Index mismatch\n");
		}
		$copyinfo = $node->getCopyInfo();
		if($copyinfo != null && $original && ($node->blob == null || strlen($node->blob) == 0)) {
			// I should return the original copy...
			$node = svnNode::find($d, $copyinfo["path"], $copyinfo["rev"], true);
		}
		
		$node->revision = $testrev;
		
		fseek($d->fp, $oldseek);
		return $node;
	}
	
	/**
	 * Find deletion of $nodePath between $startrev and $endrev revisions
	 * @param svnDump $d
	 * @param string $nodePath Nodepath to find
	 * @param int $startrev Start revision
	 * @param int $endrev End revision (included)
	 * @return boolean True if the file is deleted between $startrev and $endrev
	 */
	public static function deletionFind(svnDump $d, $nodePath, $startrev, $endrev) {
		for($testrev = $startrev; $testrev <= $endrev; $testrev++) {
			if(isset($d->lastrevmap[$testrev][$nodePath]))
				return true;
		}
		return false;
	}

	/**
	 * Find every Nodepath that starts with $nodePath
	 * @param svnDump $d
	 * @param string $nodePath
	 * @param int $rev
	 * @return array 
	 */
	public static function bulkfind(svnDump $d, $nodePath, $rev) {
		$ret = array();
		for($i = 0; $i <= $rev; $i++) {
			if(!isset($d->filemap[$i])) continue;
			foreach($d->filemap[$i] as $key => $value) {
				if(preg_match("/^".preg_quote($nodePath, "/")."/i", $key)) {
					$ret[$key] = $i;
				}
			}
		}
		return $ret;
	}
	
	/**
	 * One level version of bulkfind
	 * @see svnNode::bulkfind
	 * @param svnDump $d
	 * @param string $nodePath
	 * @param int $rev
	 * @return array 
	 */
	public static function bulkfindOneLevel(svnDump $d, $nodePath, $rev) {
		$ret = array();
		// From zero to current revision, looping throught the map
		for($i = 0; $i <= $rev; $i++) {
			if(!isset($d->filemap[$i])) continue;
			foreach($d->filemap[$i] as $key => $value) {
				// IF findDeleted($i to $rev) then skip
				if(svnNode::deletionFind($d, $key, $i, $rev)) continue;

				$regex = "/^".preg_quote($nodePath, "/")."\/([^\/]*)\/?$/i";
				if($nodePath == "")
					$regex = "/^([^\/]*)?$/i";
				
				if(preg_match($regex, $key) && trim($key) != "") {
					$ret[$key] = $i;
				}
			}
		}
		return $ret;
	}

	/**
	 * Get copy info (if exists)
	 * @return null|array
	 */
	public function getCopyInfo() {
		if(!isset($this->headers["Node-copyfrom-path"])) return null;
		
		return array(
		    "path" => $this->headers["Node-copyfrom-path"],
		    "rev" => $this->headers["Node-copyfrom-rev"]
			);
	}

	/**
	 * Clean copy from headers 
	 */
	public function cleanCopyFrom() {
		unset($this->headers["Node-copyfrom-rev"]);
		unset($this->headers["Node-copyfrom-path"]);
		unset($this->headers["Text-copy-source-md5"]);
		unset($this->headers["Text-copy-source-sha1"]);
	}

	public function setAction($action) {
		$this->headers["Node-action"] = $action;
	}

	public function getAction() {
		return isset($this->headers["Node-action"]) ? $this->headers["Node-action"] : null;
	}

	public function setNodePath($nodePath) {
		$this->headers["Node-path"] = $nodePath;
	}

	public function getNodePath() {
		return isset($this->headers["Node-path"]) ? $this->headers["Node-path"] : null;
	}

	public function getSize() {
		return isset($this->headers["Content-length"]) ? intval($this->headers["Content-length"]) : 0;
	}

	public function isDir() {
		return isset($this->headers["Node-kind"]) && $this->headers["Node-kind"] == "dir";
	}
	
	public function isFile() {
		return isset($this->headers["Node-kind"]) && $this->headers["Node-kind"] == "file";
	}
	
	public function isRevision() {
		return isset($this->headers["Revision-number"]);
	}
	
	public function isRootDir() {
		return strpos("/", $this->headers["Node-path"]) == false;
	}
}
