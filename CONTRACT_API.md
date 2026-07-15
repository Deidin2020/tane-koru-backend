# CONTRACT_API.md

Tane Koru CRM API contract draft based on:

- Frontend behavior in `/Users/macbook/Desktop/neotane-salesflow`
- Laravel backend structure in `/Users/macbook/Desktop/tane-koru-backend`
- ERD source of truth in `/Users/macbook/Library/Containers/net.whatsapp.WhatsApp/Data/tmp/documents/31CD46BE-1F12-4B0D-AB35-CB73B8DEB7D9/ERD_corrected_Tane_Koru_CRM.md`

This document is for API design only. No Laravel implementation is included.

## 1. Contract Rules

- Base path: `/api/v1`
- Content type: `application/json; charset=utf-8`
- File upload content type: `multipart/form-data`
- Auth model expected by frontend: bearer token on all non-auth endpoints
- Database source of truth: ERD and Laravel migrations
- Primary key type: `bigint unsigned`
- Timestamp format: ISO-8601
- Date format: `YYYY-MM-DD`
- Soft-deleted rows are excluded from all public endpoints

### Standard error envelope

```json
{
  "error": {
    "code": "validation_error",
    "message": "The given data was invalid.",
    "details": {
      "field_name": ["Validation message"]
    }
  }
}
```

### Standard status codes

- `200 OK` read/update success
- `201 Created` create/upload success
- `204 No Content` delete/logout success
- `401 Unauthorized` missing or invalid token
- `403 Forbidden` authenticated but not allowed
- `404 Not Found` record missing
- `409 Conflict` uniqueness/duplicate conflict
- `422 Unprocessable Entity` validation or business rule violation
- `500 Internal Server Error` unexpected failure

### Enums

- `roles.name`: `admin | project_manager | sales_manager | salesperson | viewer`
- `clients.status`: `new | presentation_completed | follow_up | reservation | deal | not_interested`
- `lead_source`: `agency | direct | referral`
- `payment_method`: `cash | installments`
- `purchase_purpose`: `citizenship | investment | residence`
- `company_category`: `large_company | medium_company | small_agency | individual_agent`
- `document_type`: `passport_id | reservation_form | sales_contract | payment_receipt | other`
- `activity_type`: `client_created | status_changed | follow_up | note | document_uploaded | reservation_uploaded`

### Authorization roles

- `any_authenticated`: any logged-in user
- `can_write`: `admin | project_manager | sales_manager | salesperson`
- `is_manager`: `admin | project_manager | sales_manager`
- `is_admin`: `admin`

## 2. Canonical Resource Shapes

### User

```json
{
  "id": 1,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "created_at": "2026-07-06T09:00:00Z",
  "updated_at": "2026-07-06T09:00:00Z"
}
```

### Profile

```json
{
  "id": 4,
  "user_id": 1,
  "full_name": "Jane Doe",
  "phone": null,
  "avatar": null,
  "email": "jane@example.com"
}
```

### Project

```json
{
  "id": 1,
  "name": "Tane Koru",
  "is_default": true
}
```

### AgencyCompany

```json
{
  "id": 9,
  "name": "Acme Agency",
  "normalized_name": "acme-agency",
  "category": "small_agency",
  "contact_person": "John Smith",
  "phone": "+90 555 000 0000",
  "address": "Istanbul",
  "notes": null,
  "created_by": 1,
  "updated_by": 1,
  "created_at": "2026-07-06T09:00:00Z",
  "updated_at": "2026-07-06T09:00:00Z"
}
```

### Client

```json
{
  "id": 15,
  "project_id": 1,
  "agency_id": 9,
  "client_name": "Ali Veli",
  "phone": "+90 555 123 4567",
  "nationality": "Turkish",
  "lead_source": "agency",
  "direct_source": null,
  "referral_name": null,
  "budget": "150000.00",
  "currency": "USD",
  "required_unit": "2+1",
  "payment_method": "cash",
  "purchase_purpose": "investment",
  "visit_date": "2026-07-06",
  "assigned_salesperson_id": 4,
  "presentation_completed": true,
  "objection": null,
  "notes": "Warm lead",
  "status": "follow_up",
  "not_interested_reason": null,
  "follow_up_date": "2026-07-10",
  "last_activity_at": "2026-07-06T10:15:00Z",
  "created_by": 1,
  "updated_by": 1,
  "created_at": "2026-07-06T09:00:00Z",
  "updated_at": "2026-07-06T10:15:00Z",
  "agency": {
    "id": 9,
    "name": "Acme Agency"
  }
}
```

### ClientDocument

```json
{
  "id": 21,
  "client_id": 15,
  "document_type": "reservation_form",
  "file_name": "reservation.pdf",
  "storage_path": "client-documents/15/1720250000-reservation.pdf",
  "mime_type": "application/pdf",
  "size_bytes": 92881,
  "uploaded_by": 1,
  "created_at": "2026-07-06T11:00:00Z",
  "updated_at": "2026-07-06T11:00:00Z"
}
```

### ClientActivity

```json
{
  "id": 31,
  "client_id": 15,
  "activity_type": "status_changed",
  "from_status": "follow_up",
  "to_status": "reservation",
  "message": null,
  "user_id": 1,
  "created_at": "2026-07-06T11:10:00Z",
  "updated_at": "2026-07-06T11:10:00Z"
}
```

### ProjectVisit

```json
{
  "id": 42,
  "project_id": 1,
  "agency_id": 9,
  "visit_date": "2026-07-06T12:00:00Z",
  "contact_person": "John Smith",
  "phone": "+90 555 111 1111",
  "sales_rep_id": 4,
  "feedback": "Interested in inventory",
  "notes": null,
  "created_by": 1,
  "updated_by": 1,
  "created_at": "2026-07-06T12:00:00Z",
  "updated_at": "2026-07-06T12:00:00Z",
  "agency": {
    "id": 9,
    "name": "Acme Agency"
  }
}
```

### CompanyVisit

```json
{
  "id": 43,
  "project_id": 1,
  "agency_id": 12,
  "visit_date": "2026-07-06T13:00:00Z",
  "category": "large_company",
  "contact_person": "Sara Kim",
  "address": "Ankara",
  "sales_rep_id": 4,
  "feedback": "Requested follow-up pack",
  "notes": null,
  "created_by": 1,
  "updated_by": 1,
  "created_at": "2026-07-06T13:00:00Z",
  "updated_at": "2026-07-06T13:00:00Z",
  "agency": {
    "id": 12,
    "name": "Beta Holdings"
  }
}
```

### DailyReport

```json
{
  "id": 5,
  "project_id": 1,
  "report_date": "2026-07-06",
  "summary": "Strong day overall.",
  "created_by": 1,
  "updated_by": 1,
  "created_at": "2026-07-06T18:00:00Z",
  "updated_at": "2026-07-06T18:05:00Z"
}
```

## 3. Authentication API

### POST `/auth/register`

- Public registration is disabled.
- Response: `403 REGISTRATION_DISABLED`.
- Users are created by an authenticated admin through `POST /users`.

### POST `/auth/login`

- Auth: none
- Request:

```json
{
  "email": "jane@example.com",
  "password": "secret123"
}
```

- Validation:
  - `email`: required, email
  - `password`: required
- Response `200`:

```json
{
  "access_token": "plain-text-token",
  "token_type": "Bearer",
  "user": { "id": 1, "email": "jane@example.com", "name": "Jane Doe" }
}
```

### POST `/auth/logout`

- Auth: bearer
- Response: `204`

### GET `/auth/me`

- Auth: bearer
- Response `200`:

```json
{
  "user": { "id": 1, "email": "jane@example.com", "name": "Jane Doe" },
  "profile": { "id": 4, "user_id": 1, "full_name": "Jane Doe", "phone": null, "avatar": null, "email": "jane@example.com" },
  "roles": ["admin"]
}
```

## 4. Reference API

### GET `/projects/default`

- Auth: `any_authenticated`
- Response `200`: `Project`

### GET `/salespeople`

- Auth: `any_authenticated`
- Purpose: dropdown data for assignment fields
- Response `200`:

```json
{
  "data": [
    { "id": 4, "full_name": "Jane Doe", "email": "jane@example.com", "phone": null, "is_active": true, "is_default": true }
  ]
}
```

Admin-only management endpoints:

- `POST /salespeople`
- `PATCH /salespeople/{id}`
- `DELETE /salespeople/{id}` (`409 SALESPERSON_HAS_RELATED_RECORDS` when linked)
- `PUT /salespeople/{id}/default` atomically replaces the only default salesperson

Creating a client or visit without a salesperson uses the active default. If none exists, the API returns `422 DEFAULT_SALESPERSON_REQUIRED`.

## 5. Clients API

### GET `/clients`

- Auth: `any_authenticated`
- Query params:
  - `status`
  - `search`
  - `salesperson_id`
  - `created_from`
  - `created_to`
  - `follow_up=today|overdue|upcoming|none`
  - `page`
  - `per_page`
  - `sort`
  - `order`
- Default sort: `created_at desc`
- Response `200`:

```json
{
  "data": [{}],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
```

### POST `/clients`

- Auth: `can_write`
- Request:

```json
{
  "client_name": "Ali Veli",
  "phone": "+90 555 123 4567",
  "nationality": "Turkish",
  "lead_source": "agency",
  "agency_id": 9,
  "direct_source": null,
  "referral_name": null,
  "budget": 150000,
  "currency": "USD",
  "required_unit": "2+1",
  "payment_method": "cash",
  "purchase_purpose": "investment",
  "visit_date": "2026-07-06",
  "assigned_salesperson_id": 4,
  "presentation_completed": true,
  "objection": null,
  "offer_details": "Unit B-12, 30% down payment, 24 installments",
  "notes": "Warm lead"
}
```

- Validation:
  - `client_name`: required, string, max 255
  - `phone`: nullable, string, max 50
  - `nationality`: nullable, string, max 100
  - `lead_source`: required, enum
  - `agency_id`: required when `lead_source=agency`, exists in `agencies_companies.id`
  - `direct_source`: nullable, string, max 255
  - `referral_name`: nullable, string, max 255
  - `budget`: nullable, numeric, min 0
  - `currency`: required, string, size 3, default `USD`
  - `required_unit`: nullable, string, max 255
  - `payment_method`: nullable, enum
  - `purchase_purpose`: nullable, enum
  - `visit_date`: nullable, date
  - `assigned_salesperson_id`: nullable, exists in `profiles.id`
  - `presentation_completed`: boolean
  - `objection`: nullable, string
  - `offer_details`: nullable, text
  - `notes`: nullable, string
- Behavior:
  - server sets `project_id` to default project
  - server sets `status=new`
  - server sets `created_by`
  - server creates `client_created` activity
  - omitted `assigned_salesperson_id` is replaced by the active default salesperson
- Response `201`: `Client`

### GET `/clients/{id}`

- Auth: `any_authenticated`
- Response `200`: `Client`

### PATCH `/clients/{id}`

- Auth: `can_write`
- Request: same fields as create except:
  - `project_id`, `created_by`, `status`, `not_interested_reason`, `last_activity_at` are not directly writable here
- Validation: same as create, but all fields optional
- Behavior:
  - server sets `updated_by`
- Response `200`: `Client`

### PUT `/clients/{id}/follow-up`

- Auth: `can_write`
- Request:

```json
{
  "follow_up_date": "2026-07-10",
  "note": "Call after document review"
}
```

- Validation:
  - `follow_up_date`: nullable, date, must be today or future when present
  - `note`: nullable, string
  - at least one of `follow_up_date` or `note` must be present and non-empty
- Behavior:
  - update `clients.follow_up_date`
  - create `follow_up` activity
  - update `last_activity_at`
- Response `200`: `Client`

### GET `/clients/{id}/activities`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "data": [ {} ]
}
```

### POST `/clients/{id}/activities`

- Auth: `can_write`
- Request:

```json
{
  "message": "Client requested floor plans."
}
```

- Validation:
  - `message`: required, string
- Behavior:
  - creates `activity_type=note`
  - updates `last_activity_at`
- Response `201`: `ClientActivity`

### GET `/clients/{id}/documents`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "data": [ {} ]
}
```

### POST `/clients/{id}/documents`

- Auth: `can_write`
- Content type: `multipart/form-data`
- Fields:
  - `document_type`
  - `file`
- Validation:
  - `document_type`: required, enum
  - `file`: required, file
  - MIME types and max size: must be finalized before implementation
- Behavior:
  - store file
  - create `client_documents`
  - create `reservation_uploaded` when type is `reservation_form`, else `document_uploaded`
  - update `last_activity_at`
- Response `201`: `ClientDocument`

### GET `/clients/{id}/documents/{docId}/download`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "url": "https://signed-url",
  "expires_in": 60
}
```

### DELETE `/clients/{id}/documents/{docId}`

- Auth: `is_manager`
- Behavior:
  - delete file
  - soft delete document row
- Response: `204`

### POST `/clients/{id}/status`

- Auth: `can_write`
- Request:

```json
{
  "to": "reservation",
  "reason": null
}
```

- Validation:
  - `to`: required, enum
  - `reason`: required when `to=not_interested`
- Business rules:
  - if current status equals target, return unchanged client
  - moving to `reservation` requires at least one `reservation_form`
  - moving to `deal` requires all:
    - `passport_id`
    - `reservation_form`
    - `sales_contract`
    - `payment_receipt`
  - moving to `not_interested` requires `reason`, stored in `not_interested_reason`
  - create `status_changed` activity
  - update `last_activity_at`
- Response `200`: `Client`

## 6. Agencies & Companies API

Read endpoints require authentication. Create/update follow `can_write`; delete requires a manager.

- `POST /agencies`
- `PATCH /agencies/{id}`
- `DELETE /agencies/{id}` (`409 AGENCY_HAS_RELATED_RECORDS` when linked)

Agency records include nullable `email`; normalized names remain unique.

### GET `/agencies`

- Auth: `any_authenticated`
- Query params:
  - `search`
  - `page`
  - `per_page`
  - `sort=name`
  - `order=asc|desc`
- Response `200`:

```json
{
  "data": [ {} ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
```

### GET `/agencies/{id}`

- Auth: `any_authenticated`
- Response `200`: `AgencyCompany`

### GET `/agencies/{id}/summary`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "project_visits": 3,
  "company_visits": 2,
  "clients": 8,
  "reservations": 2,
  "deals": 1
}
```

### GET `/agencies/{id}/clients`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "data": [ {} ]
}
```

### GET `/agencies/{id}/project-visits`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "data": [ {} ]
}
```

### GET `/agencies/{id}/company-visits`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "data": [ {} ]
}
```

## 7. Project Visits API

### GET `/project-visits`

- Auth: `any_authenticated`
- Query params:
  - `from`
  - `to`
  - `sales_rep_id`
  - `search`
  - `page`
  - `per_page`
  - `sort=visit_date`
  - `order=desc|asc`
- Response `200`:

```json
{
  "data": [ {} ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
```

### POST `/project-visits`

- Auth: `can_write`
- Request:

```json
{
  "agency_id": 9,
  "visit_date": "2026-07-06T12:00:00Z",
  "sales_rep_id": 4,
  "feedback": "Interested in inventory",
  "notes": null
}
```

- Validation:
  - `agency_id`: required, exists
  - `visit_date`: required, date
  - `sales_rep_id`: nullable, exists in `profiles.id`
  - `feedback`: nullable, string
  - `notes`: nullable, string
- Behavior:
  - server sets `project_id`
  - server sets `created_by`
  - omitted `sales_rep_id` is replaced by the active default salesperson
  - `contact_person` and `phone` are read live from the related agency and are prohibited in writes
- Response `201`: `ProjectVisit`

### PATCH `/project-visits/{id}`

- Auth: `can_write`
- Validation: same fields, all optional
- Behavior:
  - server sets `updated_by`
- Response `200`: `ProjectVisit`

### DELETE `/project-visits/{id}`

- Auth: `is_manager`
- Response: `204`

## 8. Company Visits API

### GET `/company-visits`

- Auth: `any_authenticated`
- Query params:
  - `from`
  - `to`
  - `sales_rep_id`
  - `category`
  - `search`
  - `page`
  - `per_page`
  - `sort=visit_date`
  - `order=desc|asc`
- Response `200`:

```json
{
  "data": [ {} ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
```

### POST `/company-visits`

- Auth: `can_write`
- Request:

```json
{
  "agency_id": 12,
  "visit_date": "2026-07-06T13:00:00Z",
  "category": "large_company",
  "sales_rep_id": 4,
  "feedback": "Requested follow-up pack",
  "notes": null
}
```

- Validation:
  - `agency_id`: required, exists
  - `visit_date`: required, date
  - `category`: nullable, enum
  - `sales_rep_id`: nullable, exists
  - `feedback`: nullable, string
  - `notes`: nullable, string
- Behavior:
  - server sets `project_id`
  - server sets `created_by`
  - omitted `sales_rep_id` is replaced by the active default salesperson
  - `contact_person` and `address` are read live from the related agency and are prohibited in writes
- Response `201`: `CompanyVisit`

### PATCH `/company-visits/{id}`

- Auth: `can_write`
- Validation: same fields, all optional
- Behavior:
  - server sets `updated_by`
- Response `200`: `CompanyVisit`

### DELETE `/company-visits/{id}`

- Auth: `is_manager`
- Response: `204`

## 9. Reporting API

### GET `/reports/dashboard`

- Auth: `any_authenticated`
- Response `200`:

```json
{
  "today": {
    "project_visits": 0,
    "company_visits": 0,
    "new_clients": 0,
    "presentations": 0
  },
  "pipeline_totals": {
    "follow_up": 0,
    "reservation": 0,
    "deal": 0
  },
  "pipeline_distribution": [
    { "status": "new", "count": 0 }
  ],
  "weekly_activity": [
    {
      "date": "2026-07-06",
      "project_visits": 0,
      "company_visits": 0,
      "new_clients": 0,
      "presentations": 0
    }
  ],
  "salesperson_last_30d": [
    {
      "salesperson_id": 4,
      "name": "Jane Doe",
      "clients": 0,
      "reservations": 0,
      "deals": 0
    }
  ]
}
```

### GET `/reports/daily`

- Auth: `any_authenticated`
- Query params:
  - either `date=YYYY-MM-DD`
  - or `from=YYYY-MM-DD&to=YYYY-MM-DD`
- Response `200`:

```json
{
  "range": { "from": "2026-07-06", "to": "2026-07-06" },
  "project_visits": {
    "total": 0,
    "agencies": ["Acme Agency"],
    "sales_reps": ["Jane Doe"]
  },
  "company_visits": {
    "total": 0,
    "categories": ["large_company"],
    "sales_reps": ["Jane Doe"]
  },
  "presentations": {
    "total": 0,
    "client_names": ["Ali Veli"]
  },
  "clients": [ {} ],
  "summary": {
    "text": "Strong day overall.",
    "editable": true
  }
}
```

### PUT `/reports/daily/summary`

- Auth: `can_write`
- Request:

```json
{
  "report_date": "2026-07-06",
  "summary": "Strong day overall."
}
```

- Validation:
  - `report_date`: required, date
  - `summary`: nullable, string
- Business rules:
  - upsert by `project_id + report_date`
  - only allowed for today
  - server sets `created_by` or `updated_by`
- Response `200`: `DailyReport`

### GET `/reports/performance`

- Auth: `any_authenticated`
- Query params:
  - `from`
  - `to`
  - `sales_rep_id`
- Response `200`:

```json
{
  "totals": {
    "project_visits": 0,
    "company_visits": 0,
    "clients": 0,
    "reservations": 0,
    "deals": 0,
    "client_to_reservation_pct": 0,
    "reservation_to_deal_pct": 0
  },
  "by_salesperson": [
    {
      "salesperson_id": 4,
      "name": "Jane Doe",
      "project_visits": 0,
      "company_visits": 0,
      "clients": 0,
      "reservations": 0,
      "deals": 0,
      "client_to_reservation_pct": 0,
      "reservation_to_deal_pct": 0
    }
  ]
}
```

## 10. User Admin API

### GET `/users`

- Auth: `is_admin`
- Response `200`:

```json
{
  "data": [
    {
      "id": 4,
      "full_name": "Jane Doe",
      "email": "jane@example.com",
      "roles": ["admin", "salesperson"],
      "is_active": true
    }
  ]
}
```

### POST `/users`

- Auth: `is_admin`
- Creates a user, profile, and the requested role assignments.
- Required fields: `full_name`, `email`, `password`, and non-empty `roles`; `is_active` defaults to `true`.
- Response: `201` user summary.

### PATCH `/users/{id}`

- Auth: `is_admin`
- Accepts `full_name`, `email`, `password`, and `is_active`.
- Response: `200` user summary.

### DELETE `/users/{id}`

- Auth: `is_admin`
- Archives the user and disables their profile.
- The current user cannot delete themself (`409 CANNOT_DELETE_CURRENT_USER`), and the last admin is protected.

### POST `/users/{id}/roles`

- Auth: `is_admin`
- Request:

```json
{
  "role": "sales_manager"
}
```

- Validation:
  - `role`: required, enum from `roles.name`
  - prevent duplicate assignment
  - prevent removing last admin via related revoke flow
- Response `201`:

```json
{
  "user_id": 4,
  "roles": ["admin", "sales_manager"]
}
```

### DELETE `/users/{id}/roles/{role}`

- Auth: `is_admin`
- Validation:
  - role must exist on user
  - must not remove last remaining admin
- Response: `204`

## 11. Frontend vs ERD Mismatches

These are the important mismatches I found and they should be resolved before Laravel implementation starts.

### Critical mismatches

1. IDs
- Frontend assumes string/UUID-like IDs in many places.
- ERD and Laravel migrations use `bigint unsigned` numeric IDs everywhere.

2. Client source linkage
- Frontend writes `clients.agency_name`.
- ERD uses `clients.agency_id` foreign key to `agencies_companies.id`.

3. Project visits linkage
- Frontend writes and filters `project_visits.agency_name`.
- ERD uses `project_visits.agency_id` only.

4. Company visits linkage
- Frontend writes and filters `company_visits.company_name`.
- ERD uses `company_visits.agency_id` only.

5. Agency detail queries
- Frontend joins related records by exact agency/company name.
- ERD intentionally replaced that model with foreign keys plus `normalized_name`.

6. User roles schema
- Frontend expects `user_roles.role` as a direct enum/string column.
- ERD and Laravel backend use `user_roles.role_id` plus `roles.name`.

7. Profiles schema
- Frontend reads `profiles.email`.
- ERD `profiles` table has no `email`; email belongs to `users`.
- API should expose profile email via join/transform, not raw table shape.

8. Auth mechanism
- Frontend assumes bearer-token auth.
- Laravel backend currently has only the default `web` session guard and no token package configured yet.

### Medium mismatches

9. Default project handling
- Frontend currently fetches the default project and sends `project_id` on create.
- ERD/business rules are cleaner if backend stamps default project server-side.

10. Agency/company creation flow
- Frontend currently behaves as if arbitrary names can be typed directly into client and visit forms.
- ERD requires a persisted `agencies_companies` row first, or an API-side upsert/lookup step.

11. Reports and agency screens
- Frontend report and agency detail screens depend on name fields such as `agency_name` and `company_name`.
- ERD requires those screens to consume joined `agency.name` values from FK-backed records.

12. Document upload limits
- Frontend allows upload without client-side MIME/size restrictions.
- ERD says Laravel should enforce MIME and max size, but values are not yet specified.

## 12. Recommended API Alignment Decisions

To keep the ERD as source of truth and still support the frontend, the contract should follow these decisions:

1. Public API should use numeric IDs, not UUIDs.
2. Write endpoints should accept `agency_id`, never `agency_name` or `company_name` as canonical fields.
3. Read endpoints should include lightweight joined agency objects so the frontend can display names without denormalized columns.
4. `/salespeople` and `/users` should return transformed data from `profiles + users + roles`, not raw table rows.
5. If the current frontend must keep free-text agency/company entry temporarily, that should be handled by a dedicated lookup/upsert API or server-side normalization step, not by storing names on client/visit rows.

## 13. Backend Readiness Notes

- Laravel backend currently has migrations only; there is no `routes/api.php` yet.
- No controllers, Form Requests, Policies, Resources, or token auth implementation exist yet.
- The schema foundation is mostly aligned with the ERD.
- The biggest contract risk is not missing tables; it is the frontend still targeting the older Supabase/name-based model.

## 14. Approval Gate

This document is ready for review. Laravel code should not be written until the frontend/ERD mismatch decisions above are approved.
