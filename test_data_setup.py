#!/usr/bin/env python3
import sqlite3
import os
import sys

# Try to find and connect to the database
possible_db_paths = [
    '/Users/asahu/Desktop/untitled folder/openeyes/protected/yii_base.db',
    '/var/www/openeyes/protected/yii_base.db',
    'protected/yii_base.db',
]

db_path = None
for path in possible_db_paths:
    if os.path.exists(path):
        db_path = path
        break

if not db_path:
    print("Database not found. Trying alternate approach...")
    # Try to find any .db files
    for root, dirs, files in os.walk('/Users/asahu/Desktop/untitled folder/openeyes'):
        for file in files:
            if file.endswith('.db'):
                db_path = os.path.join(root, file)
                print(f"Found database: {db_path}")
                break

if not db_path:
    print("No database file found")
    sys.exit(1)

try:
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    # Check if genetics_study_subject table exists
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='genetics_study_subject';")
    if cursor.fetchone():
        print("genetics_study_subject table found")
        # Query existing records
        cursor.execute("SELECT id, study_id, subject_id FROM genetics_study_subject LIMIT 1;")
        row = cursor.fetchone()
        if row:
            print(f"Found existing record: ID={row[0]}")
        else:
            print("No existing records in genetics_study_subject")
    else:
        print("genetics_study_subject table not found")
        # List all tables
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
        tables = cursor.fetchall()
        print(f"Available tables: {[t[0] for t in tables]}")
    
    conn.close()
except Exception as e:
    print(f"Error: {e}")
    sys.exit(1)
