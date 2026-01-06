#!/usr/bin/env python3
import json
import urllib.request
import urllib.error
import base64

BASE_URL = "http://localhost:8091"
BUCKET = "openeyes"
SCOPE = "clinical"

# Couchbase credentials
USERNAME = "Administrator"
PASSWORD = "password"

def make_auth_header():
    credentials = f"{USERNAME}:{PASSWORD}"
    encoded = base64.b64encode(credentials.encode()).decode()
    return f"Basic {encoded}"

def couchbase_n1ql_query(query):
    """Execute a N1QL query against Couchbase"""
    url = f"{BASE_URL}/query"
    headers = {
        "Authorization": make_auth_header(),
        "Content-Type": "application/json",
    }
    data = json.dumps({"statement": query}).encode('utf-8')
    
    try:
        req = urllib.request.Request(url, data=data, headers=headers, method='POST')
        with urllib.request.urlopen(req) as response:
            result = json.loads(response.read())
            return result
    except Exception as e:
        print(f"Error executing query: {e}")
        return None

def create_test_data():
    print("Creating test data in Couchbase...")
    
    # Step 1: Create a Patient
    patient_id = "patient_test_1"
    patient_query = f"""
    INSERT INTO `{BUCKET}`.`{SCOPE}`.`patient` (KEY, VALUE)
    VALUES ('{patient_id}', {{
        "id": 1,
        "type": "patient",
        "first_name": "TestGeneticsSubject",
        "last_name": "TestPatient",
        "dob": "1980-01-01",
        "gender": "M",
        "is_deceased": false
    }})
    """
    print(f"Creating patient...")
    result = couchbase_n1ql_query(patient_query)
    if result and 'errors' not in result or not result.get('errors'):
        print(f"Patient created successfully")
    else:
        print(f"Failed to create patient: {result}")
    
    # Step 2: Create a GeneticsPatient
    genetics_patient_id = "genetics_patient_test_1"
    genetics_patient_query = f"""
    INSERT INTO `{BUCKET}`.`{SCOPE}`.`genetics_patient` (KEY, VALUE)
    VALUES ('{genetics_patient_id}', {{
        "id": 1,
        "type": "genetics_patient",
        "patient_id": 1,
        "gender_id": 1,
        "is_deceased": false
    }})
    """
    print(f"Creating genetics patient...")
    result = couchbase_n1ql_query(genetics_patient_query)
    if result and 'errors' not in result or not result.get('errors'):
        print(f"Genetics patient created successfully")
    else:
        print(f"Failed to create genetics patient: {result}")
    
    # Step 3: Create a GeneticsStudy
    study_id = "genetics_study_test_1"
    study_query = f"""
    INSERT INTO `{BUCKET}`.`{SCOPE}`.`genetics_study` (KEY, VALUE)
    VALUES ('{study_id}', {{
        "id": 1,
        "type": "genetics_study",
        "name": "Test Study",
        "criteria": "Test Criteria",
        "end_date": null
    }})
    """
    print(f"Creating study...")
    result = couchbase_n1ql_query(study_query)
    if result and 'errors' not in result or not result.get('errors'):
        print(f"Study created successfully")
    else:
        print(f"Failed to create study: {result}")
    
    # Step 4: Create a GeneticsStudySubject (the pivot table)
    study_subject_id = "genetics_study_subject_test_1"
    study_subject_query = f"""
    INSERT INTO `{BUCKET}`.`{SCOPE}`.`genetics_study_subject` (KEY, VALUE)
    VALUES ('{study_subject_id}', {{
        "id": 1,
        "type": "genetics_study_subject",
        "study_id": 1,
        "subject_id": 1,
        "participation_status_id": 1,
        "is_consent_given": false,
        "consent_received_by": null,
        "comments": "",
        "consent_given_on": null
    }})
    """
    print(f"Creating study-subject relationship...")
    result = couchbase_n1ql_query(study_subject_query)
    if result and 'errors' not in result or not result.get('errors'):
        print(f"Study-subject created successfully")
        print(f"\nTest data created successfully!")
        print(f"GeneticsStudySubject ID for testing: 1")
        print(f"Test the editStudyStatus page with: /Genetics/subject/editStudyStatus/1")
    else:
        print(f"Failed to create study-subject: {result}")
    
    # Print results
    print(f"\nTesting database connection...")
    test_query = "SELECT COUNT(*) as count FROM `openeyes`.`clinical`.`genetics_study_subject`"
    result = couchbase_n1ql_query(test_query)
    print(f"Result: {result}")

if __name__ == '__main__':
    create_test_data()
