# LED Inventory Management System - Use Cases

This document outlines the primary use cases and actors for the LED Inventory Management System, completing the Software Requirements Specification (SRS) deliverables.

## Actors & Stakeholders

- **Admin**: Full system access, including user management, role assignment, and global operational oversight.
- **Manager**: Oversees projects/deployments, approves stock movements, reviews audit logs, and generates reports.
- **Warehouse Staff**: Responsible for receiving new stock, organizing physical warehouse locations, and assembling/disassembling LED cabinets.
- **Technician**: Handles on-site deployment mapping, utilizes mobile QR code scanning, and executes physical hardware repairs.

---

## Use Case 1: Register New LED Inventory
**Actor:** Warehouse Staff
**Goal:** To add newly purchased LED components (e.g., modules, power supplies) into the unified system registry.

**Main Success Scenario:**
1. The Warehouse Staff receives a new physical shipment of LED hardware.
2. The actor logs into the system via a warehouse kiosk.
3. The actor navigates to the Inventory registration interface.
4. The actor inputs the manufacturer, model, specifications, and registers the items by scanning their pre-printed QR codes.
5. The system validates that the QR codes and serial numbers are unique.
6. The system creates the new inventory records with a default status of `available`.
7. The system logs the creation event in the `inventory_logs` with the actor's timestamp and ID.

**Alternative Paths:**
- *Duplicate QR Code (Step 5):* The system detects an existing QR code in the database, alerts the actor via a UI error toast, and prevents the creation of the duplicate record.

---

## Use Case 2: Scan and Deploy Equipment to an Event
**Actor:** Technician, Manager
**Goal:** To assign available LED cabinets and internal components to a specific event deployment and update their physical status.

**Main Success Scenario:**
1. A Manager creates a new Deployment record (e.g., "Summer Concert 2026") in the system.
2. A Technician uses a mobile device to access the system's universal hybrid scanning interface.
3. The Technician scans the physical QR code on an LED cabinet.
4. The system instantly queries the database, retrieves the cabinet's details, and confirms its status is `available`.
5. The Technician assigns the scanned cabinet to the active deployment and inputs its physical screen positioning (e.g., Grid X:1, Y:2).
6. The system updates the cabinet's status from `available` to `deployed`.
7. The system records the physical movement in `stock_movements` and the scan event in `qr_scan_logs`.

**Alternative Paths:**
- *Item Not Available (Step 4):* The system identifies the item as `defective` or `under_repair`. It blocks the deployment assignment and shows an alert to the Technician preventing them from deploying broken hardware.

---

## Use Case 3: Report Defective Hardware and Initiate Repair
**Actor:** Technician
**Goal:** To accurately log a broken LED module during an event or testing, and queue it for maintenance.

**Main Success Scenario:**
1. During a deployment breakdown, a Technician identifies an LED module with dead pixels.
2. The Technician scans the module's QR code using their mobile device.
3. The system displays the item's current profile, including its parent cabinet.
4. The Technician clicks "Update Status" and selects `defective`, adding a required textual note ("Dead pixels in bottom left quadrant").
5. The system updates the status and logs the transition in `inventory_logs`.
6. The system automatically surfaces the item in the "Repairs" queue for warehouse processing.

**Alternative Paths:**
- *Invalid Status Transition (Step 4):* If the item was previously marked as `retired`, the system's `status_transitions` rules block the change to `defective`, ensuring data integrity and notifying the user.

---

## Use Case 4: Review Dashboard and Audit Logs
**Actor:** Admin, Manager
**Goal:** To monitor overall inventory health, track movements, and audit user accountability.

**Main Success Scenario:**
1. The Admin logs into the system dashboard.
2. The system dynamically loads the global statistics grid, displaying total active cabinets, items currently in repair, and active deployments.
3. The Admin navigates to the Audit Logs section to investigate a missing power supply.
4. The Admin filters the logs by the missing item's QR code.
5. The system returns the complete lifecycle history of the item, including the exact timestamp and Technician ID of the last known QR scan.
6. The Admin identifies the last user who handled the item and follows up with them directly based on the irrefutable log data.

---

## Use Case 5: Submit and Review a Repair Ticket
**Actors:** Registered User, Staff, Admin
**Goal:** To report an equipment issue and have staff decide whether it should enter the repair workflow.

**Main Success Scenario:**
1. A user opens the Repairs page and enters a ticket title, equipment QR code, reason, and description.
2. The system verifies the equipment exists and creates a ticket with `pending` status, recording who submitted it.
3. The item's inventory status remains unchanged while the ticket awaits review.
4. Staff or an Admin reviews the ticket and accepts or declines it.
5. On acceptance, the ticket records who reviewed it and the item moves to `under_repair`; on decline, the item's status is unchanged.
6. Accepted tickets can then move through `in_progress` to `resolved`, with the repair activity retained in the ticket history.
