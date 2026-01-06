#!/usr/bin/env python3
import urllib.request
import urllib.parse
import http.cookiejar
import sys
import re

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
    if status >= 400:
        print(f"ERROR: Page returned {status}")
        if 'Missing required parameter' in body:
            print("Found: Missing required parameter error")
        match = re.search(r'<h1>([^<]+)</h1>', body)
        if match:
            print(f"Error Message: {match.group(1)}")
    else:
        print(f"SUCCESS: Page returned {status}")
except urllib.error.HTTPError as e:
    print(f"HTTP Error {e.code}: {e.reason}")
    body = e.read().decode('utf-8')
    if 'Missing required parameter' in body:
        print("Found: Missing required parameter error")
    match = re.search(r'<h1>([^<]+)</h1>', body)
    if match:
        print(f"Error Message: {match.group(1)}")
except Exception as e:
    print(f"Error: {e}")

# Step 3: Test with a valid procedure ID
print("\nStep 3: Testing /procedure/complications/1 (with ID=1)...")
test_url = f'{url_base}/procedure/complications/1'
try:
    response = opener.open(test_url)
    status = response.status
    body = response.read().decode('utf-8')
    print(f"HTTP Status: {status}")
    if status >= 400:
        print(f"ERROR: Page returned {status}")
    else:
        print(f"SUCCESS: Page returned {status}")
        print(f"Response length: {len(body)} bytes")
        print(f"Response content (first 200 chars): {body[:200]}")
except urllib.error.HTTPError as e:
    print(f"HTTP Error {e.code}: {e.reason}")
except Exception as e:
    print(f"Error: {e}")
