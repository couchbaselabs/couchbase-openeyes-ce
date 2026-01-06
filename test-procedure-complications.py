#!/usr/bin/env python3
import requests
import json
import sys

url_base = 'http://localhost:7777'
session = requests.Session()

# Step 1: Login
print("Step 1: Logging in...")
login_url = f'{url_base}/site/login'
login_data = {
    'User[username]': 'admin',
    'User[password]': 'admin'
}
try:
    response = session.post(login_url, data=login_data)
    print(f"Login request completed with status: {response.status_code}")
except Exception as e:
    print(f"Error during login: {e}")
    sys.exit(1)

# Step 2: Test the /procedure/complications endpoint without ID
print("\nStep 2: Testing /procedure/complications (no ID)...")
test_url = f'{url_base}/procedure/complications'
try:
    response = session.get(test_url)
    print(f"HTTP Status: {response.status_code}")
    if response.status_code >= 400:
        print(f"ERROR: Page returned {response.status_code}")
        # Check for error messages
        if 'Missing required parameter' in response.text:
            print("Found: Missing required parameter error")
        if 'CHttpException' in response.text:
            print("Found: CHttpException")
        # Extract first error message
        import re
        match = re.search(r'<h1>([^<]+)</h1>', response.text)
        if match:
            print(f"Error Message: {match.group(1)}")
    else:
        print(f"SUCCESS: Page returned {response.status_code}")
except Exception as e:
    print(f"Error: {e}")

# Step 3: Test with a valid procedure ID
print("\nStep 3: Testing /procedure/complications/1 (with ID=1)...")
test_url = f'{url_base}/procedure/complications/1'
try:
    response = session.get(test_url)
    print(f"HTTP Status: {response.status_code}")
    if response.status_code >= 400:
        print(f"ERROR: Page returned {response.status_code}")
    else:
        print(f"SUCCESS: Page returned {response.status_code}")
        print(f"Response length: {len(response.text)} bytes")
        # Try to parse as JSON
        try:
            data = response.json()
            print(f"Response is valid JSON: {type(data)}")
            print(f"Data: {data}")
        except:
            print("Response is not JSON")
except Exception as e:
    print(f"Error: {e}")
