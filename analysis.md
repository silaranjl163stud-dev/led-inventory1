# Codebase Analysis & Vanilla Refactoring Plan: LED Inventory System

## Current State Assessment

The project is a custom-built Single Page Application (SPA) designed for LED inventory management. It utilizes a classic PHP backend (REST API) and a Vanilla JavaScript frontend.

### Architecture Overview
- **Frontend:** Vanilla JS with a single `index.html`. Uses `localStorage` for JWT-like auth tokens. Styling is handled via CSS variables and a glassmorphism design.
- **Backend:** Custom PHP routing in `api/index.php`. Controllers handle business logic. PDO is used for database interactions.
- **Database:** MySQL/MariaDB with a well-defined schema.
- **Features:** QR scanning, stock management, deployment tracking, and repair ticketing.

### Weaknesses / Risks
1.  **Fragile Navigation:** The app uses manual class toggling for views without updating the browser history. This causes the "Back" button to exit the application rather than returning to the previous screen.
2.  **Monolithic Frontend Logic:** `app.js` contains all business logic, DOM manipulation, and API calls (54KB+), making it difficult to maintain and debug.
3.  **Security Vulnerabilities:** Credentials are hardcoded in PHP files, and there is no built-in protection against common attacks like CSRF.
4.  **CDN Dependency:** Relying on external CDNs for core libraries (Ionicons, QR scanning) creates a single point of failure and prevents offline/local-network use.

---

## Vanilla Refactoring Strategy

The goal is to transform the codebase into a robust, professional-grade application using only native web technologies, avoiding the overhead and "dependency hell" of modern frameworks.

### Phase 1: Native Client-Side Routing (The "Back Button" Fix)
To make the SPA behave like a "proper" app, we must integrate the **HTML5 History API**.
- **Implementation:** Modify the `switchView` function to use `history.pushState()`.
- **State Management:** Listen for the `popstate` event to handle user navigation via browser Back/Forward buttons.
- **URL Mapping:** Map views to clean URLs (e.g., `/inventory/stocks`) using the existing Nginx `try_files` configuration.

### Phase 2: Modular Frontend Architecture (ES6 Modules)
Break down the monolithic `app.js` into a clean, modular structure using native **ES6 Modules**.
- **`router.js`**: Manages URL changes and view transitions.
- **`api.js`**: A dedicated service for all fetch requests.
- **`ui-components.js`**: Functions that return HTML template literals or utilize `<template>` tags for rendering cards and lists.
- **`app.js`**: Acts only as the orchestrator/entry point.

### Phase 3: Hardened Vanilla PHP Backend
Improve the backend without moving to a framework by adopting modern PHP standards and security practices.
- **Environment Variables:** Create a `.env` file for database credentials and load it using a simple PHP script.
- **Security Middleware:** Implement a centralized check for CSRF tokens on all POST/PUT/DELETE requests.
- **Response Standardization:** Create a helper class for consistent JSON output and HTTP status codes.
- **Local Assets:** Download all CDN-hosted libraries and serve them locally from the `assets/vendor` directory.

---

## Proposed "Pure" Tech Stack
- **Backend:** PHP 8.3 (Vanilla) + PDO + OpenSSL.
- **Frontend:** HTML5, CSS3 (Variables & Grid), ES6+ JavaScript.
- **Routing:** Native HTML5 History API (`pushState`/`popstate`).
- **Storage:** LocalStorage (for session) + IndexedDB (optional, for offline scan caching).
- **Environment:** Docker (Nginx + PHP-FPM + MariaDB) for local development consistency.

## Conclusion
Refactoring with native technologies ensures maximum longevity and performance. By implementing the History API and modularizing the code, we solve the critical usability issues (Back button support) and maintenance hurdles without introducing the complexity of third-party frameworks.
