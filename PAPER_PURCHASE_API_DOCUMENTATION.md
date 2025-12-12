# Paper Purchase APIs Documentation

## Overview

This documentation covers two APIs for managing paper purchases:
1. **Admin API** - Get aggregated list of all paper purchases grouped by parent
2. **Parent API** - Get individual paper purchases for authenticated parent

---

## Base Configuration

### Base URL
```
Production: https://your-domain.com/api
Development: http://localhost:8000/api
```

### Authentication
Both APIs require **Bearer Token Authentication** using Laravel Passport.

**Required Header:**
```
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

---

## 1. Admin API - Paper Purchases List

### Endpoint
```
GET /api/admin/paper-purchases
```

### Description
Returns an aggregated list of all paper purchases grouped by parent. Each parent entry includes:
- Parent information (name, email)
- Total papers purchased count
- Total amount spent
- Payment status summary
- Individual purchase details

### Authorization
- **Role Required:** Admin
- **Middleware:** `auth:api` + `CheckToken::using('Admin')`

### Query Parameters

All parameters are **optional**:

| Parameter | Type | Default | Description | Validation |
|-----------|------|---------|-------------|------------|
| `search` | string | null | Search by parent name or email | Max 255 characters |
| `page` | integer | 1 | Page number for pagination | Min: 1 |
| `per_page` | integer | 10 | Items per page | Min: 1, Max: 100 |
| `payment_status` | string | null | Filter by payment status | See valid values below |
| `date_from` | date | null | Filter purchases from this date | Format: Y-m-d (e.g., 2025-01-01) |
| `date_to` | date | null | Filter purchases until this date | Format: Y-m-d, must be >= date_from |
| `sort_by` | string | latest_purchase_date | Sort field | Values: `latest_purchase_date`, `total_amount`, `total_papers`, `parent_name` |
| `sort_order` | string | desc | Sort direction | Values: `asc`, `desc` |

### Valid Payment Status Values
```
- "pending"
- "paid"
- "failed"
- "canceled"
- "expired"
- "refunded"
- "partially_refunded"
```

### Request Example

#### Basic Request
```bash
curl -X GET "https://your-domain.com/api/admin/paper-purchases" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Accept: application/json"
```

#### With Filters
```bash
curl -X GET "https://your-domain.com/api/admin/paper-purchases?page=1&per_page=20&search=john&payment_status=paid&date_from=2025-01-01&date_to=2025-12-31&sort_by=total_amount&sort_order=desc" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Accept: application/json"
```

#### JavaScript/Fetch Example
```javascript
const fetchPaperPurchases = async (filters = {}) => {
  const queryParams = new URLSearchParams({
    page: filters.page || 1,
    per_page: filters.perPage || 10,
    ...(filters.search && { search: filters.search }),
    ...(filters.paymentStatus && { payment_status: filters.paymentStatus }),
    ...(filters.dateFrom && { date_from: filters.dateFrom }),
    ...(filters.dateTo && { date_to: filters.dateTo }),
    sort_by: filters.sortBy || 'latest_purchase_date',
    sort_order: filters.sortOrder || 'desc'
  });

  const response = await fetch(
    `${API_BASE_URL}/admin/paper-purchases?${queryParams}`,
    {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${adminToken}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }
  );

  return await response.json();
};
```

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Paper purchases fetched successfully",
  "data": {
    "data": [
      {
        "parent_id": 1,
        "parent_name": "John Doe",
        "parent_email": "john.doe@example.com",
        "total_papers": 5,
        "total_amount": 500.00,
        "currency": "gbp",
        "payment_status_summary": {
          "paid": 4,
          "pending": 1,
          "failed": 0,
          "cancelled": 0,
          "expired": 0,
          "refunded": 0
        },
        "latest_purchase_date": "2025-01-15 10:30:00",
        "purchases": [
          {
            "purchase_id": 10,
            "paper_id": 4,
            "paper_name": "11+ Math Paper 1",
            "amount": 100.00,
            "currency": "gbp",
            "payment_status": "paid",
            "purchased_at": "2025-01-15 10:30:00",
            "student_id": 5,
            "student_name": "Jane Doe",
            "student_email": "jane.doe@example.com",
            "purchased_by": "parent"
          },
          {
            "purchase_id": 11,
            "paper_id": 5,
            "paper_name": "11+ English Paper 1",
            "amount": 100.00,
            "currency": "gbp",
            "payment_status": "paid",
            "purchased_at": "2025-01-14 14:20:00",
            "student_id": null,
            "student_name": null,
            "student_email": null,
            "purchased_by": "parent"
          }
        ]
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 25,
      "last_page": 3,
      "from": 1,
      "to": 10
    }
  }
}
```

### Response Fields Description

#### Parent Object
| Field | Type | Description |
|-------|------|-------------|
| `parent_id` | integer | Unique parent user ID |
| `parent_name` | string | Full name of parent (first_name + last_name) |
| `parent_email` | string | Parent's email address |
| `total_papers` | integer | Total number of papers purchased by this parent |
| `total_amount` | float | Total amount spent by this parent |
| `currency` | string | Currency code (e.g., "gbp", "eur", "usd") |
| `payment_status_summary` | object | Count of purchases by payment status |
| `latest_purchase_date` | string | Date and time of most recent purchase (Y-m-d H:i:s) |
| `purchases` | array | Array of individual purchase objects |

#### Purchase Object (within parent)
| Field | Type | Description |
|-------|------|-------------|
| `purchase_id` | integer | Unique purchase ID |
| `paper_id` | integer | ID of the purchased paper |
| `paper_name` | string | Name of the paper |
| `amount` | float | Purchase amount |
| `currency` | string | Currency code |
| `payment_status` | string | Payment status (pending, paid, failed, etc.) |
| `purchased_at` | string | Purchase date and time (Y-m-d H:i:s) |
| `student_id` | integer\|null | Student ID if purchased for a student |
| `student_name` | string\|null | Student name if purchased for a student |
| `student_email` | string\|null | Student email if purchased for a student |
| `purchased_by` | string | Who made the purchase ("parent" or "student") |

### Error Responses

#### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

#### 403 Forbidden (Not Admin)
```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

#### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "per_page": [
      "Per page cannot exceed 100."
    ],
    "date_to": [
      "Date to must be after or equal to date from."
    ],
    "sort_by": [
      "Invalid sort field. Allowed values: latest_purchase_date, total_amount, total_papers, parent_name."
    ]
  }
}
```

#### 500 Server Error
```json
{
  "success": false,
  "message": "An error occurred while fetching paper purchases"
}
```

---

## 2. Parent API - My Paper Purchases

### Endpoint
```
GET /api/parent/paper-purchases
```

### Description
Returns all paper purchases for the authenticated parent user. Includes complete paper details including PDFs, format, category, and description.

### Authorization
- **Role Required:** Parent
- **Middleware:** `auth:api` + `CheckToken::using('Parent')`

### Query Parameters

All parameters are **optional**:

| Parameter | Type | Default | Description | Validation |
|-----------|------|---------|-------------|------------|
| `status` | string | null | Filter by payment status | See valid values below |

### Valid Payment Status Values
```
- "pending"
- "paid"
- "failed"
- "canceled"
- "expired"
- "refunded"
- "partially_refunded"
```

### Request Example

#### Basic Request
```bash
curl -X GET "https://your-domain.com/api/parent/paper-purchases" \
  -H "Authorization: Bearer {parent_token}" \
  -H "Accept: application/json"
```

#### With Status Filter
```bash
curl -X GET "https://your-domain.com/api/parent/paper-purchases?status=paid" \
  -H "Authorization: Bearer {parent_token}" \
  -H "Accept: application/json"
```

#### JavaScript/Fetch Example
```javascript
const fetchMyPaperPurchases = async (status = null) => {
  const queryParams = status ? `?status=${status}` : '';

  const response = await fetch(
    `${API_BASE_URL}/parent/paper-purchases${queryParams}`,
    {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${parentToken}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }
  );

  return await response.json();
};
```

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Purchased papers fetched successfully",
  "data": [
    {
      "purchase_id": 10,
      "paper_id": 4,
      "paper_name": "11+ Math Paper 1",
      "paper_description": "Complete math practice paper for 11+ exam preparation",
      "paper_format": "download",
      "paper_category": "11+",
      "paper_price": 100.00,
      "paper_currency": "gbp",
      "paper_image": "https://your-domain.com/storage/papers/images/paper-4.jpg",
      "paper_pdfs": [
        {
          "id": 15,
          "name": "11plus-math-paper-1",
          "file_name": "11plus-math-paper-1.pdf",
          "url": "https://your-domain.com/storage/papers/pdfs/15/11plus-math-paper-1.pdf",
          "size": 2048576,
          "order": 0,
          "original_name": "11+ Math Paper 1.pdf",
          "mime_type": "application/pdf",
          "created_at": "2025-01-10 08:00:00"
        },
        {
          "id": 16,
          "name": "11plus-math-paper-1-answers",
          "file_name": "11plus-math-paper-1-answers.pdf",
          "url": "https://your-domain.com/storage/papers/pdfs/16/11plus-math-paper-1-answers.pdf",
          "size": 1024000,
          "order": 1,
          "original_name": "11+ Math Paper 1 Answers.pdf",
          "mime_type": "application/pdf",
          "created_at": "2025-01-10 08:00:00"
        }
      ],
      "paper_duration_minutes": 60,
      "paper_total_marks": 100,
      "amount": 100.00,
      "currency": "gbp",
      "payment_status": "paid",
      "purchased_at": "2025-01-15 10:30:00",
      "purchased_by": "parent",
      "student_id": 5,
      "student_name": "Jane Doe",
      "student_email": "jane.doe@example.com",
      "status": 2,
      "score": null,
      "started_at": null,
      "completed_at": null
    },
    {
      "purchase_id": 11,
      "paper_id": 5,
      "paper_name": "11+ English Paper 1",
      "paper_description": "Complete English practice paper",
      "paper_format": "physical",
      "paper_category": "11+",
      "paper_price": 120.00,
      "paper_currency": "gbp",
      "paper_image": "https://your-domain.com/storage/papers/images/paper-5.jpg",
      "paper_pdfs": [],
      "paper_duration_minutes": 45,
      "paper_total_marks": null,
      "amount": 120.00,
      "currency": "gbp",
      "payment_status": "pending",
      "purchased_at": "2025-01-14 14:20:00",
      "purchased_by": "parent",
      "student_id": null,
      "student_name": null,
      "student_email": null,
      "status": 1,
      "score": null,
      "started_at": null,
      "completed_at": null
    }
  ]
}
```

### Response Fields Description

| Field | Type | Description |
|-------|------|-------------|
| `purchase_id` | integer | Unique purchase ID |
| `paper_id` | integer | ID of the purchased paper |
| `paper_name` | string | Name of the paper |
| `paper_description` | string\|null | Description of the paper |
| `paper_format` | string\|null | Paper format (e.g., "download", "physical", "online") |
| `paper_category` | string\|null | Paper category name (e.g., "11+") |
| `paper_price` | float\|null | Original paper price |
| `paper_currency` | string\|null | Currency code for paper price |
| `paper_image` | string | URL to paper image (or dummy image if not available) |
| `paper_pdfs` | array | Array of PDF objects (see below) |
| `paper_duration_minutes` | integer\|null | Paper duration in minutes |
| `paper_total_marks` | integer\|null | Total marks for the paper |
| `amount` | float\|null | Purchase amount |
| `currency` | string | Currency code for purchase |
| `payment_status` | string\|null | Payment status (pending, paid, failed, etc.) |
| `purchased_at` | string\|null | Purchase date and time (Y-m-d H:i:s) |
| `purchased_by` | string | Who made the purchase ("parent" or "student") |
| `student_id` | integer\|null | Student ID if purchased for a student |
| `student_name` | string\|null | Student full name if purchased for a student |
| `student_email` | string\|null | Student email if purchased for a student |
| `status` | integer\|null | Purchase status (1=Pending, 2=Approved, 3=Rejected) |
| `score` | float\|null | Score achieved (if applicable) |
| `started_at` | string\|null | When paper was started (Y-m-d H:i:s) |
| `completed_at` | string\|null | When paper was completed (Y-m-d H:i:s) |

#### PDF Object (within paper_pdfs array)
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Media ID |
| `name` | string | PDF name |
| `file_name` | string | Stored file name |
| `url` | string | Full URL to download PDF |
| `size` | integer | File size in bytes |
| `order` | integer | Display order (0-based) |
| `original_name` | string | Original uploaded file name |
| `mime_type` | string | MIME type (usually "application/pdf") |
| `created_at` | string | Upload date and time (Y-m-d H:i:s) |

### Error Responses

#### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

#### 403 Forbidden (Not Parent)
```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

#### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "Invalid payment status. Allowed values: pending, paid, failed, canceled, expired, refunded, partially_refunded."
    ]
  }
}
```

#### 500 Server Error
```json
{
  "success": false,
  "message": "Error",
  "data": {
    "error": "An error occurred."
  }
}
```

---

## Frontend Integration Examples

### React/Next.js Example

```typescript
// types.ts
interface PaperPurchase {
  purchase_id: number;
  paper_id: number;
  paper_name: string;
  paper_description: string | null;
  paper_format: string | null;
  paper_category: string | null;
  paper_price: number | null;
  paper_currency: string | null;
  paper_image: string;
  paper_pdfs: PDF[];
  amount: number | null;
  currency: string;
  payment_status: string | null;
  purchased_at: string | null;
  purchased_by: string;
  student_id: number | null;
  student_name: string | null;
  student_email: string | null;
}

interface PDF {
  id: number;
  name: string;
  file_name: string;
  url: string;
  size: number;
  order: number;
  original_name: string;
  mime_type: string;
  created_at: string;
}

// api.ts
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

export const fetchMyPaperPurchases = async (
  token: string,
  status?: string
): Promise<PaperPurchase[]> => {
  const url = status
    ? `${API_BASE_URL}/parent/paper-purchases?status=${status}`
    : `${API_BASE_URL}/parent/paper-purchases`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    }
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Failed to fetch paper purchases');
  }

  const data = await response.json();
  return data.data;
};

// Component usage
const MyPapersPage = () => {
  const [purchases, setPurchases] = useState<PaperPurchase[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<string>('');

  useEffect(() => {
    const loadPurchases = async () => {
      try {
        const token = localStorage.getItem('auth_token');
        const data = await fetchMyPaperPurchases(token!, filter || undefined);
        setPurchases(data);
      } catch (error) {
        console.error('Error loading purchases:', error);
      } finally {
        setLoading(false);
      }
    };

    loadPurchases();
  }, [filter]);

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      <select
        value={filter}
        onChange={(e) => setFilter(e.target.value)}
      >
        <option value="">All Status</option>
        <option value="paid">Paid</option>
        <option value="pending">Pending</option>
        <option value="failed">Failed</option>
      </select>

      {purchases.map((purchase) => (
        <div key={purchase.purchase_id}>
          <h3>{purchase.paper_name}</h3>
          <p>{purchase.paper_description}</p>
          <p>Status: {purchase.payment_status}</p>
          <p>Purchased: {purchase.purchased_at}</p>
          
          {purchase.paper_pdfs.length > 0 && (
            <div>
              <h4>PDFs:</h4>
              {purchase.paper_pdfs.map((pdf) => (
                <a
                  key={pdf.id}
                  href={pdf.url}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {pdf.original_name}
                </a>
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  );
};
```

### Vue.js Example

```javascript
// api.js
export const paperPurchaseApi = {
  async getMyPurchases(token, status = null) {
    const url = status
      ? `/api/parent/paper-purchases?status=${status}`
      : '/api/parent/paper-purchases';

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to fetch purchases');
    }

    const data = await response.json();
    return data.data;
  }
};

// Component
export default {
  data() {
    return {
      purchases: [],
      loading: false,
      statusFilter: ''
    };
  },
  async mounted() {
    await this.loadPurchases();
  },
  methods: {
    async loadPurchases() {
      this.loading = true;
      try {
        const token = this.$store.state.auth.token;
        this.purchases = await paperPurchaseApi.getMyPurchases(
          token,
          this.statusFilter || null
        );
      } catch (error) {
        console.error('Error:', error);
      } finally {
        this.loading = false;
      }
    }
  },
  watch: {
    statusFilter() {
      this.loadPurchases();
    }
  }
};
```

---

## Common Error Handling

### Handling 401 Unauthorized
```javascript
if (response.status === 401) {
  // Token expired or invalid
  localStorage.removeItem('auth_token');
  window.location.href = '/login';
}
```

### Handling 422 Validation Errors
```javascript
if (response.status === 422) {
  const error = await response.json();
  // Display validation errors to user
  Object.keys(error.errors).forEach(field => {
    console.error(`${field}: ${error.errors[field][0]}`);
  });
}
```

### Handling Network Errors
```javascript
try {
  const response = await fetch(url, options);
  // ... handle response
} catch (error) {
  if (error.name === 'TypeError' && error.message.includes('fetch')) {
    // Network error
    console.error('Network error. Please check your connection.');
  } else {
    console.error('Unexpected error:', error);
  }
}
```

---

## Notes

1. **Date Format**: All dates in query parameters should be in `Y-m-d` format (e.g., `2025-01-15`)
2. **DateTime Format**: All datetime values in responses are in `Y-m-d H:i:s` format (e.g., `2025-01-15 10:30:00`)
3. **Currency**: Currency codes follow ISO 4217 standard (gbp, eur, usd)
4. **Pagination**: Admin API supports pagination with max 100 items per page
5. **PDF URLs**: PDF URLs are direct download links. Ensure proper authentication if PDFs are protected
6. **Image URLs**: If paper image is not available, a dummy image URL is returned from `config('constants.dummy_image')`
7. **Empty Results**: Both APIs return empty arrays `[]` when no purchases are found (not an error)
8. **Soft Deletes**: Soft-deleted purchases are automatically excluded from results

---

## Support

For issues or questions, please contact the development team or refer to the main API documentation.

