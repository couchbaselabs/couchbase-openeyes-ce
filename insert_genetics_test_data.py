#!/usr/bin/env python3
import subprocess
import json

def insert_test_data():
    """Insert test data using cbimport if available, or fallback to REST API"""
    
    # Try to find cbimport  
    try:
        result = subprocess.run(['which', 'cbimport'], capture_output=True, text=True)
        if result.returncode == 0:
            print("cbimport found, using it to insert data")
            # Use cbimport
            return use_cbimport()
    except:
        pass
    
    # Fallback to REST API
    print("Using REST API to insert data")
    return use_rest_api()

def use_cbimport():
    """Use cbimport to insert test data"""
    # This would require the Couchbase CLI tools to be installed
    pass

def use_rest_api():
    """Use Couchbase REST API to insert data"""
    import urllib.request
    import urllib.error
    import base64
    
    BASE_URL = "http://localhost:8091"
    USERNAME = "Administrator"
    PASSWORD = "password"
    
    auth = base64.b64encode(f"{USERNAME}:{PASSWORD}".encode()).decode()
    headers = {
        "Authorization": f"Basic {auth}",
        "Content-Type": "application/json"
    }
    
    # Try using /api/v1 endpoint
    test_doc = {
        "id": 1,
        "type": "genetics_patient",
        "patient_id": 1,
        "gender_id": 1
    }
    
    # Try different endpoints
    endpoints = [
        f"{BASE_URL}/api/v1/b/openeyes/scopes/clinical/collections/genetics_patient/docs/genetics_patient_1",
        f"{BASE_URL}/pools/default/buckets/openeyes/docs/genetics_patient_1",
    ]
    
    for endpoint in endpoints:
        try:
            req = urllib.request.Request(
                endpoint,
                data=json.dumps(test_doc).encode('utf-8'),
                headers=headers,
                method='PUT'
            )
            with urllib.request.urlopen(req) as response:
                print(f"Success at {endpoint}: {response.status}")
                return True
        except urllib.error.HTTPError as e:
            print(f"Failed at {endpoint}: {e.code} - {e.reason}")
        except Exception as e:
            print(f"Error at {endpoint}: {e}")
    
    return False

if __name__ == '__main__':
    insert_test_data()
