#!/bin/bash

DUMP=/tmp/fixed.dmp
REPODIR=/tmp/tmp_repo

for project in `cat projects.txt`; do
        echo -n "Filtering $project files..."
        svndumpfilter include $project --drop-empty-revs --renumber-revs --quiet < $DUMP | sed -e "s/Node-path: $project\/\?/Node-path: /" | sed -e "s/Node-copyfrom-path: $project\/\?/Node-copyfrom-path: /" > $REPODIR/repo_$project.dmp
        echo " done.";
done

