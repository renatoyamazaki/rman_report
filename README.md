# RMAN Report

Simple report application for rman backups


# How does it work?

Almost every rman backup uses a recovery catalog.
This rman report utility makes use of the recovery catalog plus a custom table. 
This custom table has information of the instances server, purpose and environment.

# What do you need
* A web server with PHP and OCI configured
* RMAN Catalog

# Instructions

## Database

### User

* Create a separate user for this report in all the target instances. 
In this guide, the user will be called 'rman_report' and the password will 
be 'passreport':

SQL> create user rman_report identified by "passreport";

* Grant 'connect' and 'select_catalog_role' for this user:

SQL> grant connect, select_catalog_role for rman_report;

* ONLY in the RMAN catalog instance, grant 'EXEMPT ACCESS POLICY' 
and 'select on rman.rc_database' for this user:

SQL> grant exempt_access_policy to rman_report;
SQL> grant select on rman.rc_database to rman_report;

### Objects creation (tables and dblink)

* Go to the sql directory, and edit the file 'create.sql':
$ cd sql
$ vim create.sql

* Create database objects on the instance:
SQL> @create.sql

## PHP

- Go to the config directory, and rename the db.php_model to db.php:
$ cd config
$ mv db.php_model db.php

- Edit the db.php for with the database credentials:
$ vim db.php 



