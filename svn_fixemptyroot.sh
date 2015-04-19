#!/bin/bash

REPODIR=/tmp/tmp_repo
REPOFIXEDDIR=/tmp/tmp_repo_fixed

for project in `cat projects.txt`; do
        echo -n "Fixing $project files..."
        php fix_empty_root.php $REPODIR/repo_$project.dmp $REPOFIXEDDIR/repo_$project.dmp
        echo " done.";
done

