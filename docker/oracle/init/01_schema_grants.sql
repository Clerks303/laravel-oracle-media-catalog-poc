-- Grants for the application user (created by gvenzl/oracle-xe via APP_USER env)
ALTER SESSION SET CONTAINER = XEPDB1;

GRANT CREATE SESSION,
      CREATE TABLE,
      CREATE SEQUENCE,
      CREATE VIEW,
      CREATE PROCEDURE,
      CREATE TRIGGER,
      CREATE SYNONYM
   TO mediacat;

ALTER USER mediacat QUOTA UNLIMITED ON USERS;
