# Routes

All routes defined in `routes/web.php`. Scheduled commands in `routes/console.php`.

---

## Guest Routes (no auth required)

```
GET  /login           Login form
POST /login           Handle login
```

---

## Event-Day Role Pages (no auth required — auth via event code)

These pages are intentionally public so staff on tablets can access them without accounts.

```
GET  /{role}/{event}           Role page (intake|scanner|loader|exit)
POST /{role}/{event}/auth      Submit auth code
POST /{role}/{event}/out       Logout from event-day session
GET  /{role}/{event}/data      JSON queue data (AJAX polling)
```

Visit status transitions (protected by event-day session):
```
PATCH  /ed/{event}/visits/{visit}/queued
PATCH  /ed/{event}/visits/{visit}/loaded
PATCH  /ed/{event}/visits/{visit}/exited
POST   /ed/{event}/reorder
```

---

## Public Pages (no auth required)

### Event Pre-Registration
```
GET  /register/                  List of upcoming/current events
GET  /register/{event}           Registration form for an event
POST /register/{event}           Submit registration
GET  /register/{event}/success   Thank-you page
```

### Reviews
```
GET  /review/    Public review form
POST /review/    Submit review
```

---

## Authenticated Routes (middleware: auth)

### Core
```
POST /logout

GET  /              Dashboard (name: dashboard)
GET  /profile
PUT  /profile/info
PUT  /profile/password
```

### Households
```
GET    /households                        index
GET    /households/create                 create form
POST   /households                        store
GET    /households/{household}            show
GET    /households/{household}/edit       edit form
PUT    /households/{household}            update
DELETE /households/{household}            destroy
POST   /households/{household}/regenerate-qr
POST   /households/{household}/attach
DELETE /households/{household}/detach/{represented}
```

### Events
```
GET    /events                                          index
GET    /events/create                                   create
POST   /events                                          store
GET    /events/{event}                                  show
GET    /events/{event}/edit                             edit
PUT    /events/{event}                                  update
DELETE /events/{event}                                  destroy
PATCH  /events/{event}/status                           updateStatus
DELETE /events/{event}/volunteers/{volunteer}           detachVolunteer
POST   /events/{event}/attendees/{attendee}/match       matchAttendee
DELETE /events/{event}/attendees/{attendee}             deleteAttendee
POST   /events/{event}/regenerate-codes                 regenerateCodes
POST   /events/{event}/inventory                        allocate inventory
PATCH  /events/{event}/inventory/{allocation}/distributed
POST   /events/{event}/inventory/{allocation}/return
DELETE /events/{event}/inventory/{allocation}
POST   /events/{event}/media                            upload media
DELETE /events/{event}/media/{media}                    delete media
```

### Check-In
```
GET   /checkin                      index (check-in UI)
GET   /checkin/search               JSON search
GET   /checkin/queue                JSON active queue
GET   /checkin/log                  JSON recent log
POST  /checkin                      store (check in)
POST  /checkin/quick-add
POST  /checkin/quick-create
PATCH /checkin/{visit}/done
PATCH /checkin/households/{household}/vehicle
POST  /checkin/represented/create
POST  /checkin/represented/attach
GET   /checkin/represented/search
```

### Volunteers
```
GET    /volunteers
GET    /volunteers/create
POST   /volunteers
GET    /volunteers/{volunteer}
GET    /volunteers/{volunteer}/edit
PUT    /volunteers/{volunteer}
DELETE /volunteers/{volunteer}
```

### Volunteer Groups
```
GET    /volunteer-groups
GET    /volunteer-groups/create
POST   /volunteer-groups
GET    /volunteer-groups/{group}
GET    /volunteer-groups/{group}/edit
PUT    /volunteer-groups/{group}
DELETE /volunteer-groups/{group}
GET    /volunteer-groups/{group}/members
POST   /volunteer-groups/{group}/members
```

### Inventory
```
GET    /inventory/categories
POST   /inventory/categories
PATCH  /inventory/categories/{category}
DELETE /inventory/categories/{category}

GET    /inventory/items
GET    /inventory/items/create
POST   /inventory/items
GET    /inventory/items/{item}
GET    /inventory/items/{item}/edit
PUT    /inventory/items/{item}
DELETE /inventory/items/{item}

POST   /inventory/items/{item}/movements
```

### Allocation Rulesets
```
GET    /allocation-rulesets
POST   /allocation-rulesets
PUT    /allocation-rulesets/{ruleset}
DELETE /allocation-rulesets/{ruleset}
GET    /allocation-rulesets/{ruleset}/preview
```

### Users & Roles
```
GET    /users
GET    /users/create
POST   /users
GET    /users/{user}
GET    /users/{user}/edit
PUT    /users/{user}
DELETE /users/{user}

GET    /roles
GET    /roles/create
POST   /roles
GET    /roles/{role}
GET    /roles/{role}/edit
PUT    /roles/{role}
DELETE /roles/{role}
```

### Visit Monitor & Log
```
GET  /monitor
GET  /monitor/{event}/data
POST /monitor/{event}/reorder

GET  /visit-log
GET  /visit-log/export
```

### Reviews (admin moderation)
```
GET   /reviews
PATCH /reviews/{review}/toggle-visibility
```

### Finance (prefix: /finance, name prefix: finance.)
```
GET    /finance/                          dashboard
GET    /finance/reports

GET    /finance/categories
POST   /finance/categories
PATCH  /finance/categories/{category}
DELETE /finance/categories/{category}

GET    /finance/transactions
GET    /finance/transactions/create
POST   /finance/transactions
GET    /finance/transactions/{transaction}
GET    /finance/transactions/{transaction}/edit
PUT    /finance/transactions/{transaction}
DELETE /finance/transactions/{transaction}
GET    /finance/transactions/{transaction}/attachment
DELETE /finance/transactions/{transaction}/attachment
```

### Reports (prefix: /reports, name prefix: reports.)
```
GET  /reports/
GET  /reports/events
GET  /reports/trends
GET  /reports/demographics
GET  /reports/lanes
GET  /reports/queue-flow
GET  /reports/volunteers
GET  /reports/reviews
GET  /reports/inventory
GET  /reports/export
GET  /reports/download
```

### Settings (prefix: /settings, name prefix: settings.)
Requires `permission:settings.view` middleware.
```
GET    /settings/                          group list
GET    /settings/{group}                   group settings form
PUT    /settings/{group}                   update group (permission:settings.update)
POST   /settings/branding/{asset}          upload logo/favicon (permission:settings.update)
DELETE /settings/branding/{asset}          delete logo/favicon (permission:settings.update)
```

---

## Scheduled Commands (`routes/console.php`)

```
events:sync-statuses    Runs daily at 00:01
```
Transitions event statuses based on today's date: `upcoming` → `current` → `past`.
