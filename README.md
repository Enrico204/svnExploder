# svnExploder

# How svnExploder works

This project was written to "explode" a single SVN repository (which contains
one directory per project) to separated repository, maintaining commit/file
history.

Eg. from one SVN repo with this structure:

<pre>
OneBigRepository:
	/project1
	/project2
	/project3
</pre>

To:

<pre>
project1:
	/trunk

project2:
	/trunk

project3:
	/trunk
</pre>

# Requirements

- PHP
- Subversion command line tools
- svndumpfilter
- Bash
- Sed
- "mail" command (tested with Postfix)

Tested on GNU/Linux Debian.

# Launch

* Download this repository
* Create a new file called projects.txt with project list to migrate from repository
* Modify "scriptChain.sh" parameter EMAILDEST
* chmod +x ./scriptChain.sh
* ./scriptChain.sh <path-to-big-repository-dump> <output-folder>

