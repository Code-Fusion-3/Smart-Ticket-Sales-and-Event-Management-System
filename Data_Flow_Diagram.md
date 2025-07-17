# User Roles and Flows in Smart Ticket Sales and Event Management System

This document provides a simple flowchart for each main role in the system, using the exact role names as defined in the system: `admin`, `event_planner`, `agent`, and `customer`.

---

## admin Flowchart

```mermaid
flowchart TD
    admin["admin"]
    admin --> A1["Manage Users"]
    admin --> A2["Manage Events"]
    admin --> A3["View Reports"]
    admin --> A4["Oversee Finances"]
    admin --> A5["Configure System"]
```

---

## event_planner Flowchart

```mermaid
flowchart TD
    event_planner["event_planner"]
    event_planner --> P1["Create Events"]
    event_planner --> P2["Edit Events"]
    event_planner --> P3["Set Ticket Types"]
    event_planner --> P4["View Sales/Analytics"]
```

---

## agent Flowchart

```mermaid
flowchart TD
    agent["agent"]
    agent --> AG1["Scan Tickets"]
    agent --> AG2["Verify Validity"]
    agent --> AG3["Log Attendance"]
```

---

## customer Flowchart

```mermaid
flowchart TD
    customer["customer"]
    customer --> U1["Register/Login"]
    customer --> U2["Browse Events"]
    customer --> U3["Buy Tickets"]
    customer --> U4["Resell Tickets"]
    customer --> U5["Receive Notifications"]
```

---

**Summary Table**

| Role           | Main Responsibilities                                                                 |
|----------------|--------------------------------------------------------------------------------------|
| admin          | Manage users, events, finances, reports, system settings                             |
| event_planner  | Create/manage events, set ticket types/prices, view sales/analytics                  |
| agent          | Verify tickets at entry, scan QR codes, log attendance                               |
| customer       | Register, buy/resell tickets, manage profile, receive notifications                  | 