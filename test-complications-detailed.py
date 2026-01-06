#!/usr/bin/env python3
import urllib.request
import urllib.parse
import http.cookiejar
import sys
import re
import json

url_base = 'http://localhost:7777'

# Create a cookie jar and opener
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

# Step 1: Login
print("Step 1: Logging in...")
login_url = f'{url_base}/site/login'
login_data = urllib.parse.urlencode({
    'User[username]': 'admin',
    'User[password]': 'admin'
}).encode('utf-8')

try:
    response = opener.open(login_url, login_data)
    print(f"Login request completed with status: {response.status}")
except Exception as e:
    print(f"Error during login: {e}")
    sys.exit(1)

# Step 2: Test the /procedure/complications endpoint without ID
print("\nStep 2: Testing /procedure/complications (no ID)...")
test_url = f'{url_base}/procedure/complications'
try:
    response = opener.open(test_url)
    status = response.status
    body = response.read().decode('utf-8')
    print(f"HTTP Status: {status}")
    
    # Check for error indicators
    if '<title>Error' in body or 'CHttpException' in body or 'Exception' in body:
        print("Page contains error indicators")
    
    # Extract title
    match = re.search(r'<title>([^<]+)</title>', body)
    if match:
        print(f"Page Title: {match.group(1)}")
    
    # Extract h1
    match = re.search(r'<h1>([^<]+)</h1>', body)
    if match:
        print(f"Page H1: {match.group(1)}")
    
    # Check for "Missing required parameter"
    if 'Missing required parameter' in body:
        print("ERROR: Missing required parameter found")
        # Extract the error message
        match = re.search(r'Missing required parameter.*?id.*?\.', body, re.DOTALL)
        if match:
            print(f"Error Details: {match.group(0)[:200]}")
    
    # Check if it's a table/list page
    if '<table' in body or 'procedure' in body.lower():
        print("Page contains table or procedure content")
    
    print(f"\nResponse body length: {len(body)}")
    
except urllib.error.HTTPError as e:
    print(f"HTTP Error {e.code}: {e.reason}")
    body = e.read().decode('utf-8')
    print(f"Error body (first 500 chars): {body[:500]}")
except Exception as e:
    print(f"Error: {e}")

# Step 3: Test with query parameter ID
print("\nStep 3: Testing /procedure/complications?id=1 (query param)...")
test_url = f'{url_base}/procedure/complications?id=1'
try:
    response = opener.open(test_url)
    status = response.status
    body = response.read().decode('utf-8')
    print(f"HTTP Status: {status}")
    print(f"Response length: {len(body)}")
    print(f"Response content type: {response.headers.get('Content-Type', 'unknown')}")
    
    # Try to parse as JSON
    try:
        data = json.loads(body)
        print(f"Valid JSON response: {data}")
    except:
        print("Not JSON, checking for HTML...")
        if 'CHttpException' in body:
            print("ERROR: CHttpException found")
        if 'Missing required parameter' in body:
            print("ERROR: Missing required parameter found")
    
except urllib.error.HTTPError as e:
    print(f"HTTP Error {e.code}: {e.reason}")
except Exception as e:
    print(f"Error: {e}")
