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
be 'passreport'. Grant 'connect' and 'select_catalog_role' for this user:

```
SQL> create user rman_report identified by "passreport";
SQL> grant connect, select_catalog_role for rman_report;
```

### Objects creation / data insert (Execute in only one instance)

* Create a separate tablespace for this user. Optional but recommended:
```
SQL> create tablespace rman_report_tabspc datafile '...' size 100M autoextend on;
SQL> alter user rman_report quota unlimited on rman_report_tabspc;
```

* Choose one of the oracle instances for use in this rman_report.
In this instance, we will create 2 tables (ora_instance and rman_log).
The **ora_instance** table is a custom table populated manually.
The **rman_log** table is populated through the report application.
Edit the first line on the file *'sql/create.sql_model'* 
and execute in the chosen instance.
Following this guide example, modify *USERNAME* to *rman_report* 
on the file *'sql/create.sql_model'*
Copy, paste and execute all the SQL in the choosen instance.

```
$ cd sql
$ vim create_model.sql
```

## PHP

* Go to the config directory, and rename the db.php_model to db.php:

```
$ cd config
$ mv db.php_model db.php
```

* Edit the db.php for with the database credentials of the choosen instance:

```
$ vim db.php 
```

* Go to the URL that was configured, and register all the target instances:

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



