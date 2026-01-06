#!/usr/bin/env python3
"""
Unlock admin user by clearing softlock
"""

import os
import pymysql
from datetime import datetime

# Get database config from environment variables or defaults
host = os.getenv('DATABASE_HOST', 'localhost')
dbname = os.getenv('DATABASE_NAME', 'openeyes')
username = os.getenv('DATABASE_USER', 'openeyes')
password = os.getenv('DATABASE_PASS', 'openeyes')
port = int(os.getenv('DATABASE_PORT', '3306'))

print(f"Connecting to database at {host}:{port} / {dbname}")

try:
    conn = pymysql.connect(
        host=host,
        port=port,
        user=username,
        password=password,
        database=dbname
    )
    
    print("Connected to database")
    
    with conn.cursor() as cursor:
        # Find admin user authentication
        cursor.execute(
            "SELECT id, username, password_softlocked_until FROM user_authentication WHERE username = 'admin' LIMIT 1"
        )
        result = cursor.fetchone()
        
        if result:
            auth_id, auth_username, softlock_until = result
            print(f"Found admin user authentication with ID: {auth_id}")
            print(f"Current softlock timestamp: {softlock_until}")
            
            # Clear the softlock by setting the timestamp to NULL
            cursor.execute(
                "UPDATE user_authentication SET password_softlocked_until = NULL, password_failed_tries = 0 WHERE id = %s",
                (auth_id,)
            )
            
            conn.commit()
            print("✓ Admin user unlocked successfully!")
            print("Password softlock cleared.")
        else:
            print("ERROR: Admin user authentication not found")
    
    conn.close()
    print("Done!")
    
except Exception as e:
    print(f"Error: {e}")
