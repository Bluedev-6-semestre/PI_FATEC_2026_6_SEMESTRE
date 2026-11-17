CREATE DATABASE IF NOT EXISTS bluedev_machine;
CREATE DATABASE IF NOT EXISTS bluedev_occurrence;
CREATE DATABASE IF NOT EXISTS bluedev_user;

CREATE USER IF NOT EXISTS 'machine_user'@'%' IDENTIFIED BY 'machine_pass';
CREATE USER IF NOT EXISTS 'occurrence_user'@'%' IDENTIFIED BY 'occurrence_pass';
CREATE USER IF NOT EXISTS 'user_user'@'%' IDENTIFIED BY 'user_pass';

GRANT ALL PRIVILEGES ON bluedev_machine.* TO 'machine_user'@'%';
GRANT ALL PRIVILEGES ON bluedev_occurrence.* TO 'occurrence_user'@'%';
GRANT ALL PRIVILEGES ON bluedev_user.* TO 'user_user'@'%';

FLUSH PRIVILEGES;