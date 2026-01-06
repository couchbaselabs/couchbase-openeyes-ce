#!/bin/bash

# Test if we can query the database for OphInDnaextraction events
# Using sqlite3 if available, otherwise looking for mysql connection

if command -v sqlite3 &> /dev/null; then
    echo "SQLite3 found"
    # Look for any SQLite databases
    find /var/www/openeyes -name "*.db" 2>/dev/null | head -5
elif command -v mysql &> /dev/null; then
    echo "MySQL found"
    mysql -h localhost -u root -e "SELECT * FROM event_type WHERE class_name='OphInDnaextraction';" 2>/dev/null || echo "Cannot connect to MySQL"
else
    echo "Neither sqlite3 nor mysql command available"
fi
