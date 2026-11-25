COMPLETE FRESH-INSTALL BUILD — 12 October 2026
Read START_HERE.md first. For the requested ready-to-demonstrate database, import
`sql/SHMS_COMPLETE_LATEST_WITH_DEMO_DATA.sql` only. It includes the complete
schema, ten named residents, rooms, allocations and two months of mess-menu data.

==============================================================
  SMART HOSTEL MANAGEMENT SYSTEM (SHMS)
  PHP + MySQL + AdminLTE — XAMPP Edition
==============================================================

REQUIREMENTS
------------
- XAMPP (Apache + MySQL + PHP 8.2+ with mysqli/mysqlnd, cURL and mbstring)
- A modern browser (Chrome, Firefox, Edge)

INSTALLATION STEPS
------------------

STEP 1 — Copy project files
  Copy the entire "shms" folder into your XAMPP htdocs directory:
    Windows: C:\xampp\htdocs\shms
    macOS:   /Applications/XAMPP/htdocs/shms
    Linux:   /opt/lampp/htdocs/shms

STEP 2 — Start XAMPP
  Open XAMPP Control Panel and start:
    • Apache
    • MySQL

STEP 3 — Create the database
  a) Open your browser and go to: http://localhost/phpmyadmin
  b) Click "New" in the left panel
  c) Name it: centralized_hostel_db  (or leave it — SQL creates it)
  d) Click the "SQL" tab
  e) Import:
       shms/sql/SHMS_COMPLETE_LATEST_WITH_DEMO_DATA.sql
  f) Click "Go" to run the SQL

  This resets the old database and creates all tables plus required demo data.
  Do not run a separate upgrade or seed SQL after this single-file import.

STEP 4 — Configure database connection (if needed)
  Open: shms/config/db.php
  Edit if your MySQL credentials differ from defaults:
    define('DB_HOST',  'localhost');   // usually localhost
    define('DB_USER',  'root');        // default XAMPP username
    define('DB_PASS',  '');            // default XAMPP password (blank)
    define('DB_NAME',  'centralized_hostel_db');

STEP 5 — Open the application
  http://localhost/shms/

LOGIN CREDENTIALS (default)
----------------------------
  Role          | Email                 | Password
  --------------|----------------------|----------
  Super Admin   | admin@shms.com        | admin123
  Resident      | resident@shms.com     | resident123
  Mess Incharge | mess@shms.com         | mess123

  IMPORTANT: Change all passwords after first login!

==============================================================
PROJECT STRUCTURE
==============================================================

  shms/
  ├── index.php              — Login page
  ├── logout.php             — Logout handler
  ├── config/
  │   ├── db.php             — Database connection
  │   └── config.php         — App constants & settings
  ├── includes/
  │   ├── functions.php      — Helper functions
  │   ├── header.php         — Shared HTML head + navbar
  │   ├── footer.php         — Shared JS + scripts
  │   ├── sidebar_admin.php  — Admin sidebar
  │   ├── sidebar_resident.php
  │   └── sidebar_mess.php
  ├── admin/
  │   ├── dashboard.php      — Admin dashboard with stats
  │   ├── residents.php      — List/search/filter residents
  │   ├── resident_add.php   — Add new resident
  │   ├── resident_edit.php  — Edit resident
  │   ├── resident_view.php  — View resident profile
  │   ├── rooms.php          — Manage rooms
  │   ├── room_add.php       — Add room
  │   ├── room_edit.php      — Edit room
  │   ├── allocations.php    — Allocate/vacate rooms
  │   ├── complaints.php     — View & update complaints
  │   ├── mess_attendance.php— Monitor mess attendance
  │   ├── misc_charges.php   — Add/edit/delete misc charges
  │   ├── bills.php          — View all bills, mark paid/unpaid
  │   ├── generate_bill.php  — Generate monthly bills with one common due date
  │   ├── view_bill.php      — Print-friendly bill view
  │   └── profile.php        — Admin profile
  ├── resident/
  │   ├── dashboard.php      — Resident home
  │   ├── my_room.php        — View allocated room
  │   ├── my_bills.php       — View all bills
  │   ├── view_bill.php      — Print/download bill
  │   ├── complaints.php     — Submit & track complaints
  │   ├── mess_menu.php      — View monthly menu
  │   └── profile.php        — Edit profile
  ├── mess/
  │   ├── dashboard.php      — Mess incharge home
  │   ├── menu.php           — Week-wise dated menu + daily prices
  │   ├── meal_prices.php    — Compatibility redirect to Mess Menu
  │   ├── attendance.php     — Record daily attendance
  │   ├── attendance_history.php — View history
  │   └── profile.php        — Edit profile
  ├── sql/
  │   ├── SHMS_COMPLETE_LATEST_WITH_DEMO_DATA.sql — one-step demo database
  │   ├── shms_database.sql  — schema/default accounts only
  │   └── seed_required_demo_data.sql — separate repeatable demo seed
  └── assets/
      ├── css/custom.css     — Custom styles
      └── js/custom.js       — Custom JavaScript

==============================================================
FEATURES IMPLEMENTED
==============================================================

  SUPER ADMIN
  ✓ Secure login with PHP sessions + CSRF protection
  ✓ Dashboard with live statistics
  ✓ Full CRUD for Residents (add/edit/view/delete/toggle status)
  ✓ Duplicate email, phone and CNIC/ID prevention
  ✓ Full CRUD for Rooms (add/edit/delete/change status)
  ✓ Room Allocation — assign rooms, vacate rooms
  ✓ View & update Complaint status with admin remark
  ✓ Upload resolution proof/fixed-work photo for residents to view
  ✓ Monitor and correct Present/Absent Mess Attendance as Admin
  ✓ Regenerate an existing monthly bill after attendance correction
  ✓ Date-wise meal prices remain locked after attendance is saved
  ✓ Full Mess control: edit date-wise menu/prices, record attendance and view history
  ✓ Enable only the meals offered on each date; unavailable meals stay hidden and unbilled
  ✓ Add/edit/delete Miscellaneous Charges per resident/month
  ✓ Auto-generate Monthly Bills (Room Rent + Previous Dues + Misc + Mess)
  ✓ Select one common Due Date while generating all monthly bills
  ✓ View all bills with filter; mark Paid/Unpaid
  ✓ Shared Admin/Resident bill design with direct PDF download
  ✓ Two side-by-side PDF vouchers: Hostel Copy + Resident Copy
  ✓ Search box inside form dropdowns
  ✓ ID proof format/length validation
  ✓ Resident ID types limited to CNIC and Passport
  ✓ Current-password verification before password changes

  RESIDENT
  ✓ Secure login
  ✓ View personal profile
  ✓ View allocated room details
  ✓ View all monthly bills
  ✓ Print/download bill (browser print)
  ✓ Submit new complaint, track complaint status
  ✓ View date-wise monthly menu grouped by week with prices
  ✓ See only meals actually enabled for each date

  MESS INCHARGE
  ✓ Secure login
  ✓ Update actual calendar-date menu grouped by Week 1, Week 2, etc.
  ✓ Set Breakfast/Lunch/Snacks/Dinner price separately for every date
  ✓ Enable or disable each meal independently for every date
  ✓ Mess Menu is the single screen for date-wise meal prices
  ✓ Record attendance only for enabled meals, with the rate captured at consumption time
  ✓ Lock consumed prices; view attendance history with charged rates

==============================================================
BILLING FORMULA
==============================================================

  Total Bill = Room Rent + Previous Unpaid Dues + Miscellaneous Charges + Mess Charges

  Previous Unpaid Dues = Total of the resident's immediately previous bill
  only when that previous bill is still marked Unpaid; otherwise Rs. 0.00.

  Mess Charges = Sum of the frozen rate captured for each attended, enabled meal

  Generated bills freeze their individual mess line-items. Meal prices cannot be
  edited after attendance is saved. Admin can correct Present/Absent attendance;
  if a bill already exists, Admin regenerates that bill to apply the correction.

  Payment Status: Paid | Unpaid
  No online payment gateway.

==============================================================
COMPLAINT STATUS
==============================================================
  Pending → In Progress → Resolved

==============================================================
ROOM TYPES
==============================================================
  Type A — 1 Single Bed (Highest Price)
  Type B — 2 Single Beds (Lower Price)
  Type C — 3 Single Beds (Lowest Price)

==============================================================
SECURITY FEATURES
==============================================================
  ✓ Bcrypt password hashing
  ✓ CSRF tokens on all forms
  ✓ PHP session-based authentication
  ✓ Prepared writes; SQL-escaped filters; HTML escaping on output
  ✓ Role-based access control (3 roles)
  ✓ Session HttpOnly cookies

==============================================================
TECHNOLOGIES USED
==============================================================
  Frontend  : HTML5, CSS3, JavaScript, AdminLTE 3.2
  Backend   : PHP (Core PHP, no framework)
  Database  : MySQL (one centralized database)
  Libraries : jQuery 3.6, Bootstrap 4.6, Font Awesome 5,
              DataTables 1.13
  Environment: XAMPP

==============================================================
SUPPORT
==============================================================
  This is a standalone offline system — no internet required
  after initial page load (CDN libraries need internet).

  For production use, download AdminLTE and include locally.
==============================================================
