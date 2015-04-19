#!/bin/bash

#### CONFIG

EMAILDEST="user@dest"

#### END CONFIG

DUMP=$1
LOG=/tmp/fixer.log
TMPDIR=/tmp

START=`date`
php fixer.php $DUMP $TMPDIR/fixed.dmp > $LOG 2>&1; cat $LOG | mail -s "DUMP fixed (project history un-chained) Esplosione finita (started at $START)" $EMAILDEST

START=`date`
bash svn_explode.sh 2>&1 | mail -s "Splitted SVN repository dumps (started at $START)" $EMAILDEST

START=`date`
bash svn_fixemptyroot.sh 2>&1 | mail -s "Fixed empty SVN root (started at $START)" $EMAILDEST

START=`date`
bash svn_import.sh $2 2>&1 | mail -s "svnExploder ended, repositories created (started at $START)" $EMAILDEST
