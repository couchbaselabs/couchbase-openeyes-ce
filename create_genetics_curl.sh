#!/bin/bash

# Create a genetics subject using curl form POST

echo "Creating genetics subject for patient 1..."

curl -X POST http://localhost:7777/Genetics/subject/edit?patient=1 \
  -H "Cookie: PHPSESSID=test" \
  -d "GeneticsPatient[patient_id]=1" \
  -d "GeneticsPatient[gender_id]=1" \
  -d "GeneticsPatient[comments]=Test genetics subject" \
  -d "GeneticsPatient[pedigrees]=" \
  -d "no_pedigree=1" \
  -d "referer=" \
  -v \
  -L

echo ""
echo "Done!"
