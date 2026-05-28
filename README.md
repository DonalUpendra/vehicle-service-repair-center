# Lumina AutoWorks - Vehicle Service & Repair Center Management System

A full-stack web application for managing vehicle service and repair center operations. Built as the final year project for the **Higher National Diploma in Information Technology (HNDIT)** program.

![Tech Stack](https://img.shields.io/badge/Backend-PHP%208-777BB4?logo=php&logoColor=white)
![Database](https://img.shields.io/badge/Database-MySQL-4479A1?logo=mysql&logoColor=white)
![Frontend](https://img.shields.io/badge/Frontend-Vanilla%20JS-F7DF1E?logo=javascript&logoColor=black)
![PWA](https://img.shields.io/badge/PWA-Ready-5A0FC8?logo=pwa&logoColor=white)

## Features

### Authentication & Authorization
- Role-based access control (Admin / Technician)
- JWT session-based authentication
- Demo credentials for quick evaluation

### Vehicle & Visit Management
- Register vehicles with owner details, odometer readings, and reported issues
- Check-in vehicles for service visits
- Track visit status across the full lifecycle: `checked-in → pending_approval → approved → in_progress → completed`

### Billing & Customer Approval
- Create detailed bills with line items (parts/services)
- Auto-calculate totals
- Email bills to customers with secure approval tokens
- Customers can approve/reject estimates directly via email link (no login required)
- Resend bills and track approval status

### Product & Inventory Management
- CRUD operations for service items and parts
- Unit pricing management
- Active/inactive product status

### Technician Job Board
- View assigned jobs grouped by status
- Track pending approvals and completed work
- Job progress updates

### Salary & Payment Tracking
- Record salary payments for technicians
- View technician earnings summary
- Payment history

### Reports & Analytics
- Dashboard with key metrics (today's check-ins, pending approvals, revenue)
- Daily activity reports
- Revenue reports with date range filtering

### Email Notifications
- Integrated mail service (SMTP)
- Configurable email settings via admin panel
- Test email functionality

### Notification System
- In-app notification bell with unread count
- Real-time alerts for bill submissions, approvals, and status changes
- Mark as read / mark all read

### PWA Support
- Installable as a Progressive Web App
- Offline-ready with service worker
- Custom icons and manifest

### Shop Settings
- Configurable shop name, address, phone, email
- SMTP mail server configuration
- Print layout customization

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8 (vanilla, no framework) |
| **Database** | MySQL with InnoDB |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript (SPA) |
| **Icons** | Font Awesome 6 |
| **Mail** | PHPMailer (SMTP) |
| **PWA** | Service Worker + Web App Manifest |

## Architecture

```
project/
├── api/
│   ├── config/          # Database config & app initialization
│   ├── controllers/     # REST API controllers (Auth, Vehicle, Bill, etc.)
│   ├── middleware/       # Auth middleware
│   ├── services/        # Mail service (SMTP)
│   └── index.php        # API router (single entry point)
├── css/
│   └── styles.css       # Complete application styles
├── database/
│   ├── schema.sql       # Full database schema with relationships
│   ├── seed.sql         # Sample data
│   └── setup.php        # One-click DB setup script
├── js/
│   ├── app.js           # SPA router & navigation
│   ├── auth.js          # Login/logout/session
│   ├── dashboard.js     # Admin dashboard
│   ├── vehicle.js       # Vehicle check-in & management
│   ├── billing.js       # Bill creation & approval workflow
│   ├── customers.js     # Customer management
│   ├── products.js      # Product/parts inventory
│   ├── technicians.js   # User management
│   ├── job-management.js # Technician job board
│   ├── salary.js        # Salary & payments
│   ├── reports.js       # Reports & analytics
│   ├── notifications.js # In-app notifications
│   ├── admin-settings.js# Shop & email settings
│   └── utils.js         # Shared utilities
├── icons/               # PWA icons (192x192, 512x512)
├── index.html           # Main application shell (SPA entry point)
├── manifest.json        # PWA manifest
└── sw.js                # Service worker
```

## Database Schema

- **users** - Admin & technician accounts with role-based access
- **vehicles** - Registered vehicles with owner details
- **visits** - Service visits with status lifecycle
- **products** - Service items & parts catalog
- **bills** - Customer bills with totals and status
- **bill_items** - Line items for each bill
- **approval_tokens** - Secure tokens for customer email approval
- **notifications** - In-app notification system
- **settings** - Key-value store for shop configuration

## API Endpoints

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/login` | No | User login |
| `POST` | `/api/logout` | No | User logout |
| `GET` | `/api/me` | No | Current user session |
| `GET` | `/api/vehicles` | Yes | List all vehicles |
| `POST` | `/api/vehicles` | Yes | Register new vehicle |
| `POST` | `/api/vehicles/check-in` | Yes | Check-in vehicle for service |
| `PUT` | `/api/vehicles/{id}` | Yes | Update vehicle |
| `DELETE` | `/api/vehicles/{id}` | Admin | Delete vehicle |
| `GET` | `/api/visits/{id}` | Yes | Get visit details |
| `GET` | `/api/products` | Yes | List products |
| `POST` | `/api/products` | Admin | Create product |
| `PUT` | `/api/products/{id}` | Admin | Update product |
| `DELETE` | `/api/products/{id}` | Admin | Delete product |
| `GET` | `/api/bills` | Yes | List all bills |
| `GET` | `/api/bills/{id}` | Yes | Get bill with items |
| `POST` | `/api/bills` | Yes | Create bill |
| `POST` | `/api/bills/{id}/submit` | Yes | Submit bill for approval |
| `PUT` | `/api/bills/{id}/status` | Yes | Update bill status |
| `POST` | `/api/bills/{id}/resend` | Admin | Resend approval email |
| `POST` | `/api/bills/{id}/job-done` | Yes | Mark job as done |
| `GET` | `/api/jobs` | Yes | Technician job list |
| `GET` | `/api/reports/dashboard` | Yes | Dashboard metrics |
| `GET` | `/api/reports/today` | Yes | Today's activity |
| `GET` | `/api/reports/pending` | Yes | Pending approvals |
| `GET` | `/api/reports/revenue` | Yes | Revenue report |
| `GET` | `/api/users` | Admin | List users |
| `POST` | `/api/users` | Admin | Create user |
| `PUT` | `/api/users/{id}` | Admin | Update user |
| `DELETE` | `/api/users/{id}` | Admin | Delete user |
| `GET` | `/api/customers` | Yes | Customer list |
| `POST` | `/api/customers/{id}/send-email` | Yes | Send email to customer |
| `GET` | `/api/salaries` | Yes | Salary payments |
| `POST` | `/api/salaries` | Admin | Record payment |
| `DELETE` | `/api/salaries/{id}` | Admin | Delete payment |
| `GET` | `/api/salaries/{id}/earnings` | Yes | Technician earnings |
| `GET` | `/api/settings` | Admin | Get settings |
| `PUT` | `/api/settings` | Admin | Update settings |
| `POST` | `/api/settings/test-email` | Admin | Test email config |
| `GET` | `/api/notifications` | Yes | Notifications |
| `POST` | `/api/notifications/read-all` | Yes | Mark all read |
| `GET` | `/api/public/quotation` | No | Public quote view |
| `POST` | `/api/public/approve` | No | Customer approval |

## Setup Instructions

### Prerequisites
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- XAMPP / MAMP / LAMP stack
- SMTP server credentials (for email features)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/DonalUpendra/vehicle-service-repair-center.git
   ```

2. **Move to web server directory** (XAMPP example)
   ```bash
   cp -r vehicle-service-repair-center /Applications/XAMPP/xamppfiles/htdocs/
   ```

3. **Create the database**
   ```bash
   mysql -u root < database/schema.sql
   mysql -u root < database/seed.sql
   ```
   Or run `database/setup.php` in your browser.

4. **Configure database connection**
   Edit `api/config/database.php`:
   ```php
   private $host = 'localhost';
   private $dbname = 'vsr_center';
   private $user = 'root';
   private $pass = '';
   ```

5. **Configure email settings** via the admin panel (Settings page) after login.

6. **Demo Login**
   - **Admin:** `admin@garage.lk` / `admin123`
   - **Technician:** `tech@garage.lk` / `tech123`

## Future Enhancements
- [ ] PDF invoice generation
- [ ] SMS notifications
- [ ] Online appointment booking
- [ ] Parts inventory with stock tracking
- [ ] REST API documentation (OpenAPI/Swagger)

---

*Submitted as the final year project for the Higher National Diploma in Information Technology (HNDIT).*
