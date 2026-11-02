Recent Updates

Student Masterlist Module

-Added a Student Masterlist tab inside the existing Registrar Dashboard.

-Displays approved students from the current school year.

-Shows student ID, full name, grade level, section, school year, total assessed fees, total paid, outstanding balance, and payment status.

-Added search functionality and filters for grade level, section, and payment category.

-Added two payment categories: Fully Paid and Pending Payments.

-Automatically retrieves student records from the existing enrollment and billing database.

-Students with zero outstanding balance are categorized as Fully Paid, while students with remaining balances are categorized as Pending Payments.

-Payment status updates based on the latest verified payment records.

-Students remain in the Masterlist even if they still have pending payments.

-Uses the existing registrar login and authentication system. No separate registrar account is required.






Adjustable Document Zoom

-Added adjustable zoom controls when viewing uploaded documents in the system.

-Added Zoom In (+) and Zoom Out (−) controls.

-Added a percentage indicator showing the current zoom level.

-Added a Reset button to return the document to its default size.

-Allows the registrar to enlarge or reduce documents when reviewing uploaded student requirements.

-Zoom adjustments only affect the document preview and do not change the overall website zoom.







Files Modified and Added

File

Changes

index.html

Added the Student Masterlist interface and document zoom controls.



styles.css

Added styling for the Masterlist and document preview zoom controls.



script.js

Added Masterlist loading, searching, filtering, and document zoom functionality.



api/registrar/masterlist.php

Added an API endpoint for retrieving approved student records and payment information.



database/migrations/001_add_section_to_applications.sql

Added the section_name column to support student section information.






Database Updates

-Uses the existing students, enrollment_applications, and school_years tables.

-Uses the existing v_application_balance view for billing information.

-Added section_name to enrollment_applications through a database migration.

-Retains existing enrollment, student, and payment records.
