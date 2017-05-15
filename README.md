# RMAN Report

Simple report application for rman backups

# What do you need
* A web server with PHP and OCI configured
* An oracle instance for use in this application
* Add some lines in your rman backup script

# Instructions

## Database

### User (Execute in all target instances)

* Create a separate user for this report in all the target instances. 
In this guide, the user will be called 'rman\_report' and the password will 
be 'passreport'. Grant 'connect' and 'select\_catalog\_role' for this user:

```
SQL> create user rman_report identified by "passreport";
SQL> grant connect, select_catalog_role to rman_report;
```

### Objects creation / data insert (Execute in only one instance)

* Choose one of the oracle instances for use in this rman_report.
In this instance, create a separate tablespace for this user.
```
SQL> create tablespace rman_report_tabspc datafile '...' size 100M autoextend on;
SQL> alter user rman_report quota unlimited on rman_report_tabspc;
SQL> alter user rman_report default tablespace rman_report_tabspc;
SQL> grant create table to rman_report;
```

## PHP

* Get the latest source php code from git
```
$ git clone https://github.com/renatoyamazaki/rman_report.git
```

* Move the source code dir to an http directory (for example /var/www/html/).
Depends in what configuration you have of your apache, nginx, etc.
```
$ mv rman_report /var/www/html/
```

* Change the 'config' dir permissions to 777. The install script will put a db.php 
file in this directory.
```
$ cd rman_report
$ chmod 777 config
```

* Go to the install.php url, and put the details of the db connection. For example:
```
http://webapp.example.com/config/install.php
```

* Register all the target instances:

```
http://webapp.example.com/add_instance.php
```

## RMAN Script

* In your rman script add the tags 'level0', 'level1' or 'level2' 
depending on the type of the backup.

```
set command id to 'TAG';
```

* At the end of your script, add a url get requisition for the application. For example:

```
wget "http://webapp.example.com/rman_update.php?dbid=$DBID" -O - > /dev/null 2> /dev/null
```
