/**
 IMPORTANT: If you want to use a different prefix 
 from the default "rm_" then make sure you change 
 the table names below.
**/

CREATE TABLE rm_job (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    app_id TEXT NOT NULL,
    metric TEXT NOT NULL,
    value TEXT NOT NULL,
    distinct_id TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    lock_id CHAR(32) NULL,
    attempts BIGINT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
INDEX (lock_id)
) ENGINE = INNODB;

CREATE TABLE rm_job_log (
    id BIGINT NOT NULL PRIMARY KEY,
    app_id TEXT NOT NULL,
    metric TEXT NOT NULL,
    value TEXT NOT NULL,
    distinct_id TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    sent_at TIMESTAMP NOT NULL
) ENGINE = INNODB;
