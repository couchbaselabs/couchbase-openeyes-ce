#!/usr/bin/env python3
import sqlite3
import os
import sys
from datetime import datetime

# Try to find the database
db_path = '/var/www/openeyes/protected/data/test.db'
if not os.path.exists(db_path):
    # Try other locations
    possible_paths = [
        '/var/www/openeyes/test.db',
        '/var/openeyes/test.db',
        '/openeyes/test.db',
    ]
    for path in possible_paths:
        if os.path.exists(path):
            db_path = path
            break

if not os.path.exists(db_path):
    print(f"Database not found at {db_path}")
    sys.exit(1)

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

try:
    # Get event type ID for OphOuCatprom5
    cursor.execute("SELECT id FROM event_type WHERE class_name = 'OphOuCatprom5'")
    result = cursor.fetchone()
    if not result:
        print("Event type OphOuCatprom5 not found")
        sys.exit(1)
    
    event_type_id = result[0]
    print(f"Event Type ID: {event_type_id}")
    
    # Get any patient
    cursor.execute("SELECT id FROM patient LIMIT 1")
    result = cursor.fetchone()
    if not result:
        print("No patients found")
        sys.exit(1)
    
    patient_id = result[0]
    print(f"Patient ID: {patient_id}")
    
    # Get any user
    cursor.execute("SELECT id FROM user LIMIT 1")
    result = cursor.fetchone()
    if not result:
        print("No users found")
        sys.exit(1)
    
    user_id = result[0]
    print(f"User ID: {user_id}")
    
    # Create an event
    now = datetime.now().isoformat()
    cursor.execute("""
        INSERT INTO event (patient_id, event_type_id, event_date, created_user_id, created_date, last_modified_user_id, last_modified_date)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    """, (patient_id, event_type_id, now, user_id, now, user_id, now))
    
    event_id = cursor.lastrowid
    print(f"Created Event ID: {event_id}")
    
    # Create CatProm5EventResult
    cursor.execute("""
        INSERT INTO cat_prom5_event_result (event_id, total_raw_score, total_rasch_measure)
        VALUES (?, ?, ?)
    """, (event_id, 10, '-0.32'))
    
    element_id = cursor.lastrowid
    print(f"Created CatProm5EventResult ID: {element_id}")
    
    conn.commit()
    print(f"Successfully created test CatProm5 event with ID {event_id}")
    
except Exception as e:
    print(f"Error: {e}")
    conn.rollback()
    sys.exit(1)
finally:
    conn.close()

