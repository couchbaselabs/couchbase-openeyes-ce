#!/bin/bash
mysql -h localhost -u openeyes -popeneyes openeyes << 'SQL'
SELECT id, name, institution_id, created_date FROM ophciexamination_surgical_history_set ORDER BY id DESC LIMIT 5;
SQL
