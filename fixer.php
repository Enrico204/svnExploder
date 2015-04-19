<?php
require "lib.php";

if(count($argv) < 3)	die("Usage: {$argv[0]} srcfile dstfile\n");

function recursiveDuplication($dest, $oldnodepath, $newnodepath, $refrev) {
//	echo "Recursive call for $oldnodepath => $newnodepath...\n";
	$node = svnNode::find($src, $oldnodepath, $refrev);
	if($node == null) {
//		echo "Node is null\n";
		exit;
	}
	if($node->isDir()) {
		$node->setAction("add");
		$node->setNodepath($newnodepath);
		fwrite($dest, $node->headers);
	
		preg_match("/^([^\/]*)\/(.*)$/i", $newnodepath, $m);
		$baserepo = $m[1];
	
		$v = svnNode::bulkfind($src, $oldnodepath, $refrev);
		foreach($v as $file => $rev) {
			if($file == $oldnodepath) continue;
//			echo "Cycling for $file...\n";
	
			preg_match("/^([^\/]*)\/(.*)$/i", $file, $m);
			recursiveDuplication($dest, $file, $baserepo . "/" . $m[2], $rev);
		}
	} else {
		if($node->getSize() > 0) {
			$node->cleanCopyFrom();
			$node->setAction("add");
			fwrite($dest, $node->headers);
			fwrite($dest, $node->blob);
		} else {
			$copyinfo = $node->getCopyInfo();
			// Trovare file
//			echo "File sorgente: ".$copyinfo["repo"]."/".$copyinfo["file"]."@".$copyinfo["rev"]."\n";
			$newnode = svnNode::find($srcfile, $copyinfo["repo"]."/".$copyinfo["file"], $copyinfo["rev"]);
			if(!is_object($newnode)) {
				var_dump($copyinfo);
				exit;
			}
			$newnode->setAction("add");
			$newnode->setNodepath($newnodepath);
			fwrite($dest, $newnode->headers);
			fwrite($dest, $newnode->blob);
		}
	}
}

function sameRoot(svnNode $a, $srcnode) {
	$aroot = explode("/", $a->getNodePath(), 2);
	$broot = explode("/", $srcnode, 2);
	return $aroot[0] == $broot[0];
}

function nodecopy(svnNode $node, svnDump $dest, $destnoderoot) {
	$copyinfo = $node->getCopyInfo();
	$newnode = svnNode::find($node->d, $copyinfo["path"], $copyinfo["rev"], true);
	if($newnode === null) {
		$newnode = clone $node;
	}
	$newnode->setAction("add");
	$newnode->setNodePath($destnoderoot);
	$newnode->cleanCopyFrom();
//	echo "Node: " . $node->getNodePath() . " new root: " . $newnode->getNodePath() . "\n";
	$dest->addNode($newnode);
}

function nodeWorker($dest, svnNode $node, $destnoderoot=null) {
	$copyinfo = $node->getCopyInfo();
	if(
		($copyinfo != null && !sameRoot($node, $copyinfo["path"]) && $node->getAction() != "change")
			||
		($destnoderoot !== null)
		) {
		if($destnoderoot === null)
			echo "Node " . $node->getNodePath() . " is copied from " . $copyinfo["path"] . "@" . $copyinfo["rev"] . "\n";
		else
			echo "Node " . $node->getNodePath() . " is moved to root \"$destnoderoot\"\n";
		
		// COPY file
		$oldroot = null;
		$filepath = null;
		$r = preg_match("/^([^\/]*)\/(.*)$/i", $node->getNodePath(), $m);
		if(!$r) {
			$oldroot = "";
			$filepath = $node->getNodePath();
		} else {
			$oldroot = $m[1];
			$filepath = $m[2];
		}
		
		$newpath = $node->getNodePath();
		if($destnoderoot !== null)
			$newpath = $destnoderoot . $filepath;
		
		nodecopy($node, $dest, $newpath);
		
		if($node->isDir()) {
//			echo "Node " . $node->getNodePath() . " is DIR\n";
			$nodes = svnNode::bulkfind($node->d, $copyinfo["path"], $copyinfo["rev"]);
//			echo "Children: " . count($nodes) . "\n";
			unset($nodes[$node->getNodePath()]);
			foreach($nodes as $file => $rev) {
				if($file == $copyinfo["path"]) continue;
				
//				echo "Doing file $file with oldroot:\"$oldroot\"\n";
				nodeWorker($dest, svnNode::find($node->d, $file, $rev), $oldroot);
			}
		}
	} elseif($copyinfo != null && !sameRoot($node, $copyinfo["path"]) && $node->getAction() == "change") {
		$node->cleanCopyFrom();
		$dest->addNode($node);
	} else {
//		echo "Node OK\n";
		$dest->addNode($node);
	}
}


$src = new svnDump($argv[1]);

@unlink("/tmp/ri.dat");
@unlink("/tmp/fi.dat");
@unlink("/tmp/di.dat");
svnDump::createIndexFile($argv[1], "/tmp/ri.dat", "/tmp/fi.dat", "/tmp/di.dat");
$src->loadIndexFile("/tmp/ri.dat", "/tmp/fi.dat", "/tmp/di.dat");

$dest = svnDump::createDumpFile($argv[2], $src->version, $src->UUID);

#$fp = fopen($argv[1], "rb");
#$dest = fopen($argv[2], "w");

$currev = 0;

while(!feof($src->fp)) {
	$node = svnNode::readNode($src, $currev);
	if($node == null) continue;
//	echo "Read node " . $node->getNodePath() . "...";
	if($node->isRevision()) {
//		echo " revision detected! r" . $node->revision . "\n";
		$currev = $node->revision;
		$dest->addNode($node);
	} else {
//		echo " node detected! Launch worker.\n";
		nodeWorker($dest, $node);
	}
	
//	$tmprow = fgets($fp);
//	$row = trim($tmprow, "\n");
//	if(preg_match("/^Node-path: (.*)/i", $row, $matches)) {
//		$node = svnNode::readNode($matches[1], $fp);
//		$nodePath = $matches[1];
//
//		$copyinfo = $node->getCopyInfo();
//		if($copyinfo !== null && $copyinfo["repo"] !== null) {
//			if($node->isDir()) {
//				recursiveDuplication($argv[1], $dest, $copyinfo["repo"]."/".$copyinfo["file"], $nodePath, $copyinfo["rev"]);
//			} else {
//				if($node->getSize() > 0) {
//					$node->cleanCopyFrom();
//					$node->setAction("add");
//					fwrite($dest, $node->headers);
//					#fwrite($dest, "\n");
//					fwrite($dest, $node->blob);
//				} else {
//					// Trovare file
//					echo "File sorgente: ".$copyinfo["repo"]."/".$copyinfo["file"]."@".$copyinfo["rev"]."\n";
//					$newnode = svnNode::find($argv[1], $copyinfo["repo"]."/".$copyinfo["file"], $copyinfo["rev"]);
//					if(!is_object($newnode)) {
//						var_dump($copyinfo);
//						exit;
//					}
//					$newnode->setAction("add");
//					$newnode->setNodepath($nodePath);
//					fwrite($dest, $newnode->headers);
//					#fwrite($dest, "\n");
//					fwrite($dest, $newnode->blob);
//				}
//				#fwrite($dest, "\n");
//			}
//		 } else {
//			fwrite($dest, $node->headers);
//			if($node->getSize() > 0) {
//				fwrite($dest, $node->blob);
//			}
//		}
//	} else {
//		fwrite($dest, $tmprow);
//	}
}

