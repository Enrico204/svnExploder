<?php
require "lib.php";

if(count($argv) < 3)	die("Usage: {$argv[0]} srcfile dstfile\n");

$src = new svnDump($argv[1]);

@unlink("/tmp/ri.dat");
@unlink("/tmp/fi.dat");
@unlink("/tmp/di.dat");
svnDump::createIndexFile($argv[1], "/tmp/ri.dat", "/tmp/fi.dat", "/tmp/di.dat");
$src->loadIndexFile("/tmp/ri.dat", "/tmp/fi.dat", "/tmp/di.dat");

$dest = svnDump::createDumpFile($argv[2], $src->version, $src->UUID);

$currev = 0;

while(!feof($src->fp)) {
	$node = svnNode::readNode($src, $currev);
	if($node == null) continue;
	
	if($node->isRevision()) {
		$currev = $node->revision;
		$dest->addNode($node);
	} else {
		if($node->getNodePath() == "" && $node->getAction() == "delete") {
			$onelevel = svnNode::bulkfindOneLevel($src, "", $currev);
			
			foreach($onelevel as $file => $r) {
				$newnode = new svnNode($node->d);
				$newnode->setNodePath($file);
				$newnode->setAction("delete");
				$dest->addNode($newnode);
			}
		} elseif($node->getNodePath() != "") {
			$dest->addNode($node);
		}
	}
}

