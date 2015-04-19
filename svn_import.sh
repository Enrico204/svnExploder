#!/bin/bash

SVNSTORE=$1
TMPREPO=/tmp/tmp_repo_fixed

mkdir -p $SVNSTORE

for project in `cat projects.txt`; do
	echo -n "Creating repository $project...";
	svnadmin create --fs-type fsfs $SVNSTORE/$project
	echo " done";
	echo -n "Importing repository $project...";
	svnadmin load $SVNSTORE/$project < $TMPREPO/repo_$project.dmp
	if [ "$?" -ne "0" ]; then
		exit 1
	fi
	echo " done."
	echo -n "Sleeping 10 sec..."
	sleep 10
	echo " done"
	#echo -n "Cleanup old files..."
	#rm -f /usr/local/tmp/repo_$project.dmp
	#echo " done."
done

