# Parent Module - Complete API Documentation

## Overview

This documentation covers all APIs related to **Billing Information Management** and **Paper Request to Home** functionality in the Parent module. These APIs allow parents to manage their billing addresses and request physical paper delivery for purchased papers.

---

## Base URL

All APIs are under the parent route prefix:
```
/api/parent
```

---

## Authentication

All APIs require authentication using Bearer Token.

**Required Headers:**
```
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

---

## API Endpoints

### 1. Billing Information APIs

#### 1.1. Get All Billing Information

**Endpoint:** `GET /api/parent/billing-information`

**Description:** Fetch all billing information records for the authenticated parent.

**Request:**
- **Method:** `GET`
- **Headers:** 
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

**Response:**
- **Status Code:** `200 OK`
- **Success Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "parent_id": 10,
      "address_line1": "123 Main Street",
      "address_line2": "Apt 4B",
      "city": "London",
      "state": "Greater London",
      "postal_code": "SW1A 1AA",
      "country": "United Kingdom",
      "phone": "+44 20 1234 5678",
      "created_at": "2025-12-14T10:30:00.000000Z",
      "updated_at": "2025-12-14T10:30:00.000000Z",
      "deleted_at": null
    }
  ],
  "message": "Billing information fetched successfully."
}
```

- **Empty Response:**
```json
{
  "success": true,
  "data": [],
  "message": "Billing information fetched successfully."
}
```

- **Error Response (500):**
```json
{
  "success": false,
  "message": "An error occurred while fetching billing information"
}
```

---

#### 1.2. Create Billing Information

**Endpoint:** `POST /api/parent/billing-information`

**Description:** Create a new billing information record for the authenticated parent.

**Request:**
- **Method:** `POST`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`
  - `Content-Type: application/json`

- **Request Body (JSON):**
```json
{
  "address_line1": "123 Main Street",
  "address_line2": "Apt 4B",
  "city": "London",
  "state": "Greater London",
  "postal_code": "SW1A 1AA",
  "country": "United Kingdom",
  "phone": "+44 20 1234 5678"
}
```

**Validation Rules:**

| Field | Type | Required | Max Length | Notes |
|-------|------|----------|------------|-------|
| `address_line1` | string | No | 255 | At least one address field must be provided |
| `address_line2` | string | No | 255 | At least one address field must be provided |
| `city` | string | No | 100 | At least one address field must be provided |
| `state` | string | No | 100 | At least one address field must be provided |
| `postal_code` | string | No | 20 | At least one address field must be provided |
| `country` | string | No | 100 | At least one address field must be provided |
| `phone` | string | No | 20 | Optional |

**Important:** At least one of the following fields must be provided: `address_line1`, `address_line2`, `city`, `state`, `postal_code`, or `country`.

**Response:**
- **Status Code:** `201 Created`
- **Success Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "parent_id": 10,
    "address_line1": "123 Main Street",
    "address_line2": "Apt 4B",
    "city": "London",
    "state": "Greater London",
    "postal_code": "SW1A 1AA",
    "country": "United Kingdom",
    "phone": "+44 20 1234 5678",
    "created_at": "2025-12-14T10:30:00.000000Z",
    "updated_at": "2025-12-14T10:30:00.000000Z",
    "deleted_at": null
  },
  "message": "Billing information created successfully."
}
```

- **Error Response (422 - Validation Error):**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "address": [
      "At least one address field is required."
    ],
    "address_line1": [
      "Address line 1 cannot exceed 255 characters."
    ],
    "city": [
      "City cannot exceed 100 characters."
    ]
  }
}
```

- **Error Response (500):**
```json
{
  "success": false,
  "message": "An error occurred while creating billing information"
}
```

---

#### 1.3. Update Billing Information

**Endpoint:** `PUT /api/parent/billing-information`

**Description:** Update an existing billing information record. Both `id` and `parent_id` must be provided in the request body for security validation.

**Request:**
- **Method:** `PUT`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`
  - `Content-Type: application/json`

- **Request Body (JSON):**
```json
{
  "id": 1,
  "parent_id": 10,
  "address_line1": "456 Updated Street",
  "address_line2": "Suite 5C",
  "city": "Manchester",
  "state": "Greater Manchester",
  "postal_code": "M1 1AA",
  "country": "United Kingdom",
  "phone": "+44 161 1234 5678"
}
```

**Validation Rules:**

| Field | Type | Required | Max Length | Notes |
|-------|------|----------|------------|-------|
| `id` | integer | **Yes** | - | Must exist in billing_informations table |
| `parent_id` | integer | **Yes** | - | Must match authenticated user's ID |
| `address_line1` | string | No | 255 | Optional, use `sometimes` |
| `address_line2` | string | No | 255 | Optional, use `sometimes` |
| `city` | string | No | 100 | Optional, use `sometimes` |
| `state` | string | No | 100 | Optional, use `sometimes` |
| `postal_code` | string | No | 20 | Optional, use `sometimes` |
| `country` | string | No | 100 | Optional, use `sometimes` |
| `phone` | string | No | 20 | Optional, use `sometimes` |

**Authorization Validation:**
- The `parent_id` in the request must match the authenticated user's ID
- The billing information record with the given `id` must belong to the authenticated parent
- If either validation fails, a 422 error will be returned

**Response:**
- **Status Code:** `200 OK`
- **Success Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "parent_id": 10,
    "address_line1": "456 Updated Street",
    "address_line2": "Suite 5C",
    "city": "Manchester",
    "state": "Greater Manchester",
    "postal_code": "M1 1AA",
    "country": "United Kingdom",
    "phone": "+44 161 1234 5678",
    "created_at": "2025-12-14T10:30:00.000000Z",
    "updated_at": "2025-12-14T11:45:00.000000Z",
    "deleted_at": null
  },
  "message": "Billing information updated successfully."
}
```

- **Error Response (422 - Validation Error):**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "id": [
      "Billing information not found or does not belong to you."
    ],
    "parent_id": [
      "Parent ID does not match authenticated user."
    ]
  }
}
```

- **Error Response (404 - Not Found):**
```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\BillingInformation] 1"
}
```

- **Error Response (500):**
```json
{
  "success": false,
  "message": "An error occurred while updating billing information"
}
```

---

#### 1.4. Delete Billing Information

**Endpoint:** `DELETE /api/parent/billing-information/{id}`

**Description:** Soft delete a billing information record. Only the parent who owns the record can delete it.

**Request:**
- **Method:** `DELETE`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

- **Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | **Yes** | Billing information ID to delete |

**Authorization:** The billing information must belong to the authenticated parent.

**Response:**
- **Status Code:** `200 OK`
- **Success Response:**
```json
{
  "success": true,
  "data": [],
  "message": "Billing information deleted successfully."
}
```

- **Error Response (404 - Not Found):**
```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\BillingInformation] 1"
}
```

- **Error Response (500):**
```json
{
  "success": false,
  "message": "An error occurred while deleting billing information"
}
```

---

### 2. Paper Request to Home API

#### 2.1. Request Paper to Home

**Endpoint:** `POST /api/parent/request-paper-to-home`

**Description:** Request a physical paper to be delivered to the parent's billing address. This API validates that:
1. The parent has purchased the paper (with paid status)
2. The paper format is "physical" or "any"
3. The billing information exists and belongs to the parent
4. No duplicate request exists for the same paper

**Request:**
- **Method:** `POST`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`
  - `Content-Type: application/json`

- **Request Body (JSON):**
```json
{
  "paper_id": 4,
  "billing_information_id": 1
}
```

**Validation Rules:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `paper_id` | integer | **Yes** | Must exist in papers table |
| `billing_information_id` | integer | **Yes** | Must exist in billing_informations table |

**Business Logic Validation:**

1. **Paper Purchase Check:**
   - Parent must have purchased the paper with `payment_status = 'paid'`
   - If not purchased, error: `"You do not have access to this paper. Please purchase it first."`

2. **Paper Format Check:**
   - Paper format must be either `"physical"` or `"any"` (case-insensitive)
   - If format is `"online"` or `"download"`, error: `"Only physical or any format papers can be requested for home delivery."`

3. **Billing Information Check:**
   - Billing information must exist
   - Billing information must belong to the authenticated parent
   - If not found or doesn't belong, error: `"Billing information not found."`

4. **Duplicate Request Check:**
   - Check if a request already exists for the same `parent_id` and `paper_id` (including soft-deleted records)
   - If duplicate exists, error: `"Already requested. We are processing your request."`

**Response:**
- **Status Code:** `201 Created`
- **Success Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "parent_id": 10,
    "paper_id": 4,
    "billing_information_id": 1,
    "requested": true,
    "created_at": "2025-12-14T12:00:00.000000Z",
    "updated_at": "2025-12-14T12:00:00.000000Z",
    "deleted_at": null
  },
  "message": "Paper request submitted successfully. We are processing your request."
}
```

- **Error Response (422 - Validation Error):**

**Case 1: Paper not purchased**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "paper_id": [
      "You do not have access to this paper. Please purchase it first."
    ]
  }
}
```

**Case 2: Invalid paper format**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "paper_id": [
      "Only physical or any format papers can be requested for home delivery."
    ]
  }
}
```

**Case 3: Billing information not found**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "billing_information_id": [
      "Billing information not found."
    ]
  }
}
```

**Case 4: Duplicate request**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "paper_id": [
      "Already requested. We are processing your request."
    ]
  }
}
```

**Case 5: Paper not found**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "paper_id": [
      "Paper not found."
    ]
  }
}
```

- **Error Response (500):**
```json
{
  "success": false,
  "message": "An error occurred while submitting paper request"
}
```

---

## Frontend Integration Guide

### API Flow & Dependencies

#### Step 1: Get Billing Information (Optional)
Before requesting a paper, check if the parent has any billing information saved:

```javascript
// GET /api/parent/billing-information
const response = await fetch('/api/parent/billing-information', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});

const data = await response.json();
if (data.success && data.data.length > 0) {
  // Parent has billing information
  const billingInfo = data.data[0]; // Use first or let user select
} else {
  // No billing information - show form to create
}
```

#### Step 2: Create/Update Billing Information (If Needed)
If no billing information exists, create one:

```javascript
// POST /api/parent/billing-information
const response = await fetch('/api/parent/billing-information', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    address_line1: '123 Main Street',
    city: 'London',
    postal_code: 'SW1A 1AA',
    country: 'United Kingdom',
    phone: '+44 20 1234 5678'
  })
});
```

#### Step 3: Verify Paper Purchase
Before allowing paper request, verify the parent has purchased the paper:

```javascript
// GET /api/parent/paper-purchases
// Check if paper_id exists in purchased papers with payment_status = 'paid'
const purchases = await fetch('/api/parent/paper-purchases', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});

const purchaseData = await purchases.json();
const hasPurchased = purchaseData.data.some(
  purchase => purchase.paper_id === paperId && purchase.payment_status === 'paid'
);
```

#### Step 4: Request Paper to Home
Once all validations pass, submit the request:

```javascript
// POST /api/parent/request-paper-to-home
const response = await fetch('/api/parent/request-paper-to-home', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    paper_id: 4,
    billing_information_id: 1
  })
});

const result = await response.json();
if (result.success) {
  // Show success message
  alert('Paper request submitted successfully!');
} else {
  // Handle errors
  if (result.errors) {
    // Display validation errors
    Object.keys(result.errors).forEach(field => {
      console.error(`${field}: ${result.errors[field].join(', ')}`);
    });
  }
}
```

### Complete Frontend Flow Example

```javascript
async function requestPaperToHome(paperId) {
  try {
    // Step 1: Check billing information
    const billingResponse = await fetch('/api/parent/billing-information', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    const billingData = await billingResponse.json();
    
    if (!billingData.success || billingData.data.length === 0) {
      // Show billing information form
      return { error: 'Please add billing information first' };
    }
    
    const billingInfoId = billingData.data[0].id;
    
    // Step 2: Verify paper purchase
    const purchasesResponse = await fetch('/api/parent/paper-purchases', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    const purchasesData = await purchasesResponse.json();
    const hasAccess = purchasesData.data.some(
      p => p.paper_id === paperId && p.payment_status === 'paid'
    );
    
    if (!hasAccess) {
      return { error: 'Please purchase this paper first' };
    }
    
    // Step 3: Request paper
    const requestResponse = await fetch('/api/parent/request-paper-to-home', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        paper_id: paperId,
        billing_information_id: billingInfoId
      })
    });
    
    const requestData = await requestResponse.json();
    
    if (requestData.success) {
      return { success: true, message: requestData.message };
    } else {
      return { error: requestData.message, errors: requestData.errors };
    }
    
  } catch (error) {
    return { error: 'An error occurred. Please try again.' };
  }
}
```

---

## Error Handling Best Practices

### 1. Validation Errors (422)
Always check for `errors` object in the response:
```javascript
if (response.errors) {
  // Display field-specific errors
  Object.keys(response.errors).forEach(field => {
    showFieldError(field, response.errors[field][0]);
  });
}
```

### 2. Authorization Errors
If you receive a 403 or 404 error, it usually means:
- The resource doesn't belong to the authenticated parent
- The resource doesn't exist
- The parent doesn't have access

**Action:** Show a user-friendly message and redirect if necessary.

### 3. Duplicate Request Error
If you receive "Already requested" error:
- Don't allow the user to submit again
- Show a message: "You have already requested this paper. We are processing your request."
- Optionally, show the request status if available

### 4. Network Errors
Handle network failures gracefully:
```javascript
try {
  const response = await fetch(url, options);
  // Handle response
} catch (error) {
  if (error.name === 'TypeError' && error.message.includes('fetch')) {
    // Network error
    showError('Network error. Please check your connection.');
  } else {
    // Other error
    showError('An unexpected error occurred.');
  }
}
```

---

## Important Notes for Frontend Developers

### 1. Update Billing Information
- **Always send both `id` and `parent_id`** in the request body for update operations
- The `parent_id` must match the authenticated user's ID
- Only send fields that need to be updated (partial updates are supported)

### 2. Paper Request Validation
- The API automatically checks:
  - Paper purchase status (must be paid)
  - Paper format (must be physical or any)
  - Billing information ownership
  - Duplicate requests
- Frontend should validate these conditions before showing the request button to improve UX

### 3. Duplicate Request Prevention
- Once a paper is requested, it cannot be requested again (even if soft-deleted)
- Show appropriate UI to prevent duplicate submissions
- Consider disabling the request button after successful submission

### 4. Billing Information Management
- A parent can have multiple billing information records
- When requesting a paper, allow the user to select which billing address to use
- Always validate that billing information exists before allowing paper request

### 5. Error Messages
- All error messages are user-friendly and can be displayed directly to the user
- Validation errors are returned in a structured format for easy field-level error display

---

## Testing Checklist

### Billing Information APIs
- [ ] Create billing information with all fields
- [ ] Create billing information with minimum required fields
- [ ] Create billing information with no address fields (should fail)
- [ ] Update billing information with valid id and parent_id
- [ ] Update billing information with invalid parent_id (should fail)
- [ ] Update billing information with non-existent id (should fail)
- [ ] Delete billing information that belongs to parent
- [ ] Delete billing information that doesn't belong to parent (should fail)
- [ ] List all billing information for authenticated parent

### Paper Request API
- [ ] Request paper with valid purchase and billing info
- [ ] Request paper without purchase (should fail)
- [ ] Request paper with online/download format (should fail)
- [ ] Request paper with non-existent billing info (should fail)
- [ ] Request same paper twice (should fail with duplicate error)
- [ ] Request paper with billing info that doesn't belong to parent (should fail)

---

## Support

For any questions or issues, please contact the backend development team.

**Last Updated:** December 14, 2025

