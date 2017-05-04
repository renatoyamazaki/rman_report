# RMAN Report

Simple report application for rman backups


# How does it work?

This rman report utility makes use of a custom table. 
This custom table has information of the database: dbid, hostname, instance, application, environment and active.

dbid|hostname|instance|application|environment|active
----|--------|--------|-----------|-----------|------
18927333|hostname01|instanceA|SAP|dev|1
45902394|hostname02|instanceX|CATALOG|prd|1

# What do you need
* A web server with PHP and OCI configured

# Instructions

## Database

### User (Execute in all target instances)

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

### Objects creation (Execute in only one instance)

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

### Instance 

* Populate the table **ora_instance** with the info of the target instances.
You can see a model in the file *'sql/data.sql_model'*. For example:

```
SQL> alter session set current_schema = rman_report;
SQL> insert into ora_instance (dbid, hostname, application, env, active) values (99283123,'hostname01.example.com', 'instance01', 'SAP','DEV',1);
SQL> commit;
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
