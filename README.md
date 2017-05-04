# RMAN Report

Simple report application for rman backups


# How does it work?

This rman report utility makes use of a custom table. 
This custom table has information of the database: server, instance, purpose and environment:

server|instance|purpose|environment
------|--------|-------|-----------
hostname01|instanceA|SAP|dev
hostname02|instanceX|CATALOG|prd

# What do you need
* A web server with PHP and OCI configured

# Instructions

## Database

### User

* Create a separate user for this report in all the target instances. 
In this guide, the user will be called 'rman\_report' and the password will 
be 'passreport':

```
SQL> create user rman_report identified by "passreport";
```

* Grant 'connect' and 'select_catalog_role' for this user:

```
SQL> grant connect, select_catalog_role for rman_report;
```

### Objects creation (tables)

* Choose one of the oracle instances for use in this rman_report.
In this instance, we will create 2 tables (ora_instance and rman_log).
The *ora_instance* table is a custom table populated manually.
The *rman_log* table is populated through the report application.
Go to the sql directory, edit the file 'create.sql' and execute in the
chosen instance:

```
$ cd sql
$ vim create.sql
SQL> @create.sql
```

## PHP

- Go to the config directory, and rename the db.php_model to db.php:

```
$ cd config
$ mv db.php_model db.php
```

- Edit the db.php for with the database credentials:

```
$ vim db.php 
```
