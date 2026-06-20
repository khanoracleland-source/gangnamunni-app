# Bed Allocation API Documentation

## Base URL
```
http://your-domain/api
```

## Authentication
All API endpoints require authentication. Include your API token in the request header:
```
Authorization: Bearer your-api-token
```

## Endpoints

### 1. Get All Bed Allocations
```http
GET /bed-allocation
```

**Response:**
```json
{
    "status": true,
    "message": "Bed allocations retrieved successfully",
    "data": [
        {
            "id": 1,
            "patient_id": 1,
            "patient_name": "John Doe",
            "bed_master_id": 1,
            "room_number": "101",
            "bed_type": "General",
            "assign_date": "2024-03-20",
            "discharge_date": "2024-03-25",
            "status": 1,
            "description": "Regular checkup",
            "temperature": "98.6",
            "symptoms": "None",
            "notes": "Patient is stable",
            "charge": 1000.00,
            "payment_status": "pending",
            "created_at": "2024-03-20T10:00:00.000000Z",
            "updated_at": "2024-03-20T10:00:00.000000Z"
        }
    ]
}
```

### 2. Get Single Bed Allocation
```http
GET /bed-allocation/{id}
```

**Response:**
```json
{
    "status": true,
    "message": "Bed allocation retrieved successfully",
    "data": {
        "id": 1,
        "patient": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        },
        "bed": {
            "id": 1,
            "room_number": "101",
            "bed_type": "General",
            "charges": 1000.00
        },
        "assign_date": "2024-03-20",
        "discharge_date": "2024-03-25",
        "status": 1,
        "description": "Regular checkup",
        "temperature": "98.6",
        "symptoms": "None",
        "notes": "Patient is stable",
        "charge": 1000.00,
        "payment_status": "pending",
        "patient_info": {
            "weight": "70",
            "height": "170",
            "blood_pressure": "120/80",
            "heart_rate": "72",
            "blood_group": "O+"
        }
    }
}
```

### 3. Create Bed Allocation
```http
POST /bed-allocation
```

**Request Body:**
```json
{
    "patient_id": 1,
    "bed_master_id": 1,
    "assign_date": "2024-03-20",
    "discharge_date": "2024-03-25",
    "status": true,
    "description": "Regular checkup",
    "temperature": "98.6",
    "symptoms": "None",
    "notes": "Patient is stable",
    "payment_status": "pending",
    "patient_info": {
        "weight": "70",
        "height": "170",
        "blood_pressure": "120/80",
        "heart_rate": "72",
        "blood_group": "O+"
    }
}
```

**Response:**
```json
{
    "status": true,
    "message": "Bed allocation created successfully",
    "data": {
        "id": 1,
        "patient_id": 1,
        "bed_master_id": 1,
        "assign_date": "2024-03-20",
        "discharge_date": "2024-03-25",
        "status": 1,
        "description": "Regular checkup",
        "temperature": "98.6",
        "symptoms": "None",
        "notes": "Patient is stable",
        "charge": 1000.00,
        "payment_status": "pending",
        "created_at": "2024-03-20T10:00:00.000000Z",
        "updated_at": "2024-03-20T10:00:00.000000Z"
    }
}
```

### 4. Update Bed Allocation
```http
PUT /bed-allocation/{id}
```

**Request Body:**
```json
{
    "patient_id": 1,
    "bed_master_id": 1,
    "assign_date": "2024-03-20",
    "discharge_date": "2024-03-25",
    "status": true,
    "description": "Updated checkup",
    "temperature": "98.7",
    "symptoms": "Mild fever",
    "notes": "Patient condition improved",
    "payment_status": "paid",
    "patient_info": {
        "weight": "71",
        "height": "170",
        "blood_pressure": "118/78",
        "heart_rate": "70",
        "blood_group": "O+"
    }
}
```

**Response:**
```json
{
    "status": true,
    "message": "Bed allocation updated successfully",
    "data": {
        "id": 1,
        "patient_id": 1,
        "bed_master_id": 1,
        "assign_date": "2024-03-20",
        "discharge_date": "2024-03-25",
        "status": 1,
        "description": "Updated checkup",
        "temperature": "98.7",
        "symptoms": "Mild fever",
        "notes": "Patient condition improved",
        "charge": 1000.00,
        "payment_status": "paid",
        "updated_at": "2024-03-20T11:00:00.000000Z"
    }
}
```

### 5. Delete Bed Allocation
```http
DELETE /bed-allocation/{id}
```

**Response:**
```json
{
    "status": true,
    "message": "Bed allocation deleted successfully"
}
```

### 6. Get Available Rooms by Bed Type
```http
GET /rooms/{bedTypeId}
```

**Query Parameters:**
- `current_allocation_id` (optional): ID of current allocation when editing
- `current_room_id` (optional): ID of current room when editing

**Response:**
```json
{
    "status": true,
    "message": "Rooms fetched successfully",
    "rooms": [
        {
            "id": 1,
            "bed": "101"
        },
        {
            "id": 2,
            "bed": "102"
        }
    ]
}
```

## Error Responses

### Validation Error (422)
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "patient_id": ["The patient id field is required."],
        "bed_master_id": ["The bed master id field is required."]
    }
}
```

### Not Found Error (404)
```json
{
    "status": false,
    "message": "Bed allocation not found"
}
```

### Server Error (500)
```json
{
    "status": false,
    "message": "Error updating bed allocation",
    "error": "Detailed error message"
}
```

## Data Types

### Bed Allocation Status
- `1` or `true`: Active
- `0` or `false`: Inactive

### Payment Status
- `pending`: Payment pending
- `paid`: Payment completed
- `partial`: Partial payment made

### Date Format
- All dates should be in `YYYY-MM-DD` format
- Example: `2024-03-20`

## Rate Limiting
- 60 requests per minute per IP address
- Rate limit headers are included in the response:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset` 