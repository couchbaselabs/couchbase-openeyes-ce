#!/usr/bin/env python3
import sqlite3
import os

# Connect to the database
db_path = "/Users/asahu/Desktop/untitled folder/openeyes/openeyes.db"
if not os.path.exists(db_path):
    print("Database not found")
    exit(1)

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Check if table exists
cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='ophtroperation_name_rule'")
if not cursor.fetchone():
    print("Table ophtroperation_name_rule does not exist")
    conn.close()
    exit(1)

# Get existing rules
cursor.execute("SELECT id, theatre_id, name FROM ophtroperation_name_rule ORDER BY id DESC LIMIT 5")
rules = cursor.fetchall()

if rules:
    print(f"Found {len(rules)} operation name rules:")
    for rule in rules:
        print(f"  ID: {rule[0]}, Theatre ID: {rule[1]}, Name: {rule[2]}")
else:
    print("No operation name rules found")
    # Get available theatres
    cursor.execute("SELECT id, name FROM ophtroperationbooking_operation_theatre LIMIT 3")
    theatres = cursor.fetchall()
    if theatres:
        print(f"\nAvailable theatres:")
        for theatre in theatres:
            print(f"  ID: {theatre[0]}, Name: {theatre[1]}")
        
        # Insert a test rule
        theatre_id = theatres[0][0]
        cursor.execute(
            "INSERT INTO ophtroperation_name_rule (theatre_id, name) VALUES (?, ?)",
            (theatre_id, "Test Delete Rule")
        )
        conn.commit()
        
        # Get the inserted ID
        cursor.execute("SELECT last_insert_rowid()")
        new_id = cursor.fetchone()[0]
        print(f"\nCreated new rule with ID: {new_id}")
    else:
        print("No theatres found in database")

conn.close()
