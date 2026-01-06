#!/bin/bash

# Create a genetics subject in Couchbase using REST API

CB_HOST="localhost"
CB_PORT="8093"
CB_USER="Administrator"
CB_PASS="password"

# Insert genetics_patient record
echo "Inserting genetics_patient record..."

curl -X POST http://$CB_HOST:$CB_PORT/query \
  -u $CB_USER:$CB_PASS \
  -d "statement=INSERT INTO openeyes.clinical.genetics_patient (KEY, VALUE) VALUES ('genetics_patient::1', {'id': '1', 'patient_id': 1, 'gender_id': 1, 'is_deceased': false, 'comments': 'Test genetics subject', '_type': 'genetics_patient'})" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -v

echo ""
echo "Done!"
