
# !/bin/bash

# Note: both NodeJS (+npm) and Java are required to run this script
# Update openapi-spec.json according to the Workflow OCR Backend API (/openapi.json) and run this script to generate Models
npx -y @openapitools/openapi-generator-cli generate
